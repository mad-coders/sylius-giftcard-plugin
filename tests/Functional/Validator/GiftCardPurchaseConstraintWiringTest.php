<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Functional\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardSaleMode;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\GiftCardPurchaseAllowed;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\OrderGiftCardPurchaseAllowed;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommand;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\GiftCard\GiftCardConfiguration;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Product\Product;

/**
 * Proves the two gift card sale constraints are actually wired into a booted container.
 *
 * The unit tests around these validators construct them by hand, so they say nothing about whether
 * the constraints ever run in a real application. Everything that carries them is implicit:
 * FrameworkBundle finds `config/validation/*.xml` only because the bundle's getPath() returns the
 * repository root, and the validators are found only by the alias on their `validator.constraint_validator`
 * tag matching the constraint's validatedBy(). Rename the directory, drop the getPath() override or
 * typo an alias and the entire refusal vanishes - with every unit test still green, and every
 * customer reaching the last-resort guard in OrderGiftCardOperator instead of a form error.
 *
 * The "exactly once" assertions matter as much as the "at least once" ones: a mapping file loaded
 * twice raises two identical violations for one mistake.
 */
final class GiftCardPurchaseConstraintWiringTest extends KernelTestCase
{
    private const CHANNEL_CODE = 'GIFT_CARD_CONSTRAINT_WIRING_TEST';

    private EntityManagerInterface $manager;

    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();

        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        $this->manager = $manager;

        $validator = self::getContainer()->get('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $this->validator = $validator;

        // Everything this test writes is rolled back, so it leaves no channel behind for the Behat
        // suite that runs after it.
        $this->manager->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->manager->getConnection()->isTransactionActive()) {
            $this->manager->rollback();
        }

        parent::tearDown();
    }

    public function testTheAddToCartConstraintIsRegisteredExactlyOnceInTheSyliusGroup(): void
    {
        $constraints = $this->classConstraints(AddToCartCommand::class, 'sylius', GiftCardPurchaseAllowed::class);

        self::assertCount(1, $constraints);
    }

    public function testTheOrderConstraintIsRegisteredExactlyOnceInTheCheckoutCompleteGroup(): void
    {
        // The same group Sylius validates the order with on the final checkout step.
        $constraints = $this->classConstraints(Order::class, 'sylius_checkout_complete', OrderGiftCardPurchaseAllowed::class);

        self::assertCount(1, $constraints);
    }

    public function testAddingAGiftCardToTheCartIsRefusedInAChannelThatDoesNotSellThem(): void
    {
        $channel = $this->persistChannel(GiftCardSaleMode::AdminOnly);
        $cart = $this->createCart($channel);
        $cartItem = $this->createItem(isGiftCard: true);

        $violations = $this->validator->validate(new AddToCartCommand($cart, $cartItem), null, ['sylius']);

        self::assertSame(
            ['madcoders_sylius_gift_card.cart_item.gift_card_not_sold_in_channel'],
            $this->messagesOf($violations, 'madcoders_sylius_gift_card.'),
        );
    }

    public function testCompletingAnOrderCarryingAGiftCardIsRefusedInAChannelThatDoesNotSellThem(): void
    {
        // This is the one that stops the customer before they are charged, and the one that covers
        // a quantity raised on the cart summary - which never builds an AddToCartCommand.
        $channel = $this->persistChannel(GiftCardSaleMode::AdminOnly);
        $order = $this->createCart($channel);
        $order->addItem($this->createItem(isGiftCard: true));

        $violations = $this->validator->validate($order, null, ['sylius_checkout_complete']);

        self::assertSame(
            ['madcoders_sylius_gift_card.order.gift_card_not_sold_in_channel'],
            $this->messagesOf($violations, 'madcoders_sylius_gift_card.'),
        );
    }

    public function testNeitherConstraintFiresInAChannelThatSellsGiftCards(): void
    {
        // The mirror image. Without it, a constraint that refused unconditionally would pass every
        // other test in this class.
        $channel = $this->persistChannel(GiftCardSaleMode::Sellable);
        $cart = $this->createCart($channel);
        $cartItem = $this->createItem(isGiftCard: true);
        $cart->addItem($cartItem);

        $addToCart = $this->validator->validate(new AddToCartCommand($cart, $cartItem), null, ['sylius']);
        $checkout = $this->validator->validate($cart, null, ['sylius_checkout_complete']);

        self::assertSame([], $this->messagesOf($addToCart, 'madcoders_sylius_gift_card.'));
        self::assertSame([], $this->messagesOf($checkout, 'madcoders_sylius_gift_card.'));
    }

    /**
     * @param class-string $class
     * @param class-string<Constraint> $constraintClass
     *
     * @return list<Constraint>
     */
    private function classConstraints(string $class, string $group, string $constraintClass): array
    {
        $metadata = $this->validator->getMetadataFor($class);
        self::assertInstanceOf(ClassMetadata::class, $metadata);

        return array_values(array_filter(
            $metadata->findConstraints($group),
            static fn (Constraint $constraint): bool => $constraint instanceof $constraintClass,
        ));
    }

    /**
     * The plugin's own violations, in order. Sylius raises its own on these objects - product
     * availability, shipping and payment method eligibility - and asserting a total count would
     * make this test a hostage to those.
     *
     * @return list<string>
     */
    private function messagesOf(ConstraintViolationListInterface $violations, string $prefix): array
    {
        $messages = [];

        foreach ($violations as $violation) {
            $message = (string) $violation->getMessageTemplate();

            if (str_starts_with($message, $prefix)) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    private function persistChannel(GiftCardSaleMode $saleMode): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode(self::CHANNEL_CODE);
        $channel->setName('Gift card constraint wiring test');
        $channel->setBaseCurrency($this->currency());
        $channel->setDefaultLocale($this->locale());
        $channel->setTaxCalculationStrategy('order_items_based');

        $configuration = new GiftCardConfiguration();
        $configuration->setChannel($channel);
        $configuration->setSaleMode($saleMode);

        $this->manager->persist($channel);
        $this->manager->persist($configuration);
        $this->manager->flush();

        return $channel;
    }

    private function currency(): Currency
    {
        $currency = $this->manager->getRepository(Currency::class)->findOneBy(['code' => 'USD']);

        if (!$currency instanceof Currency) {
            $currency = new Currency();
            $currency->setCode('USD');
            $this->manager->persist($currency);
        }

        return $currency;
    }

    private function locale(): Locale
    {
        $locale = $this->manager->getRepository(Locale::class)->findOneBy(['code' => 'en_US']);

        if (!$locale instanceof Locale) {
            $locale = new Locale();
            $locale->setCode('en_US');
            $this->manager->persist($locale);
        }

        return $locale;
    }

    private function createCart(ChannelInterface $channel): Order
    {
        $order = new Order();
        $order->setChannel($channel);
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');

        return $order;
    }

    private function createItem(bool $isGiftCard): OrderItem
    {
        $product = new Product();
        $product->setCode('GIFT_CARD_WIRING_PRODUCT');
        $product->setGiftCard($isGiftCard);

        // Sylius' own OrderProductEligibility runs in the checkout-complete group and reads the
        // product's name, which is translatable - without a locale it throws before this plugin's
        // constraint is reached.
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setName('Gift card constraint wiring product');

        // Sylius resolves an item's product through its variant, so the fixture has to build the
        // variant rather than setting the product directly.
        $variant = new ProductVariant();
        $variant->setCode('GIFT_CARD_WIRING_VARIANT');
        $product->addVariant($variant);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(5000);

        return $item;
    }
}
