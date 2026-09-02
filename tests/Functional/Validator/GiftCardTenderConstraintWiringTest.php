<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Functional\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTenderMode;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\GiftCardRedemptionAllowed;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderItemUnit;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\GiftCard\GiftCard;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\GiftCard\GiftCardConfiguration;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\Order;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\OrderItem;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Product\Product;

/**
 * Proves the checkout half of "a gift card does not buy a gift card" is wired into a booted
 * container, and that it leaves ordinary orders alone.
 *
 * The "exactly once" assertion matters as much as the refusal: `config/validation/Order.xml` now
 * carries two class constraints, and a mapping file loaded twice raises two identical violations for
 * one mistake. See docs/adr-log/0016-a-gift-card-does-not-buy-a-gift-card.md.
 */
final class GiftCardTenderConstraintWiringTest extends KernelTestCase
{
    private const string CHANNEL_CODE = 'GIFT_CARD_TENDER_WIRING_TEST';

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

    public function testTheRedemptionConstraintIsRegisteredExactlyOnceInTheCheckoutCompleteGroup(): void
    {
        $metadata = $this->validator->getMetadataFor(Order::class);
        self::assertInstanceOf(ClassMetadata::class, $metadata);

        $constraints = array_filter(
            $metadata->findConstraints('sylius_checkout_complete'),
            static fn (Constraint $constraint): bool => $constraint instanceof GiftCardRedemptionAllowed,
        );

        self::assertCount(1, $constraints);
    }

    public function testAnOrderOfNothingButGiftCardsCannotBeCompletedWithACardApplied(): void
    {
        $order = $this->createOrder($this->persistChannel(GiftCardTenderMode::GoodsOnly), giftCards: 1);
        $order->addGiftCard($this->createGiftCard());

        $violations = $this->validator->validate($order, null, ['sylius_checkout_complete']);

        self::assertSame(
            ['madcoders_sylius_gift_card.order.gift_card_cannot_pay_for_gift_card'],
            $this->pluginMessagesOf($violations),
        );
    }

    public function testAMixedBasketWithACardAppliedCompletesNormally(): void
    {
        // The decision in ADR 0016 criterion 5: the goods are still payable with the card.
        $order = $this->createOrder($this->persistChannel(GiftCardTenderMode::GoodsOnly), giftCards: 1, goods: 1);
        $order->addGiftCard($this->createGiftCard());

        $violations = $this->validator->validate($order, null, ['sylius_checkout_complete']);

        self::assertSame([], $this->pluginMessagesOf($violations));
    }

    public function testAnOrdinaryOrderWithACardAppliedIsUntouched(): void
    {
        // This constraint runs on every checkout in the shop. An over-eager violation here would
        // stop the shop taking any order at all.
        $order = $this->createOrder($this->persistChannel(GiftCardTenderMode::GoodsOnly), goods: 2);
        $order->addGiftCard($this->createGiftCard());

        $violations = $this->validator->validate($order, null, ['sylius_checkout_complete']);

        self::assertSame([], $this->pluginMessagesOf($violations));
    }

    public function testAChannelThatAllowsItCompletesAGiftCardOnlyOrder(): void
    {
        $order = $this->createOrder($this->persistChannel(GiftCardTenderMode::Anything), giftCards: 1);
        $order->addGiftCard($this->createGiftCard());

        $violations = $this->validator->validate($order, null, ['sylius_checkout_complete']);

        self::assertSame([], $this->pluginMessagesOf($violations));
    }

    /** @return list<string> */
    private function pluginMessagesOf(ConstraintViolationListInterface $violations): array
    {
        $messages = [];

        foreach ($violations as $violation) {
            $message = (string) $violation->getMessageTemplate();

            if (str_starts_with($message, 'madcoders_sylius_gift_card.')) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    private function persistChannel(GiftCardTenderMode $tenderMode): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode(self::CHANNEL_CODE);
        $channel->setName('Gift card tender wiring test');
        $channel->setBaseCurrency($this->currency());
        $channel->setDefaultLocale($this->locale());
        $channel->setTaxCalculationStrategy('order_items_based');

        $configuration = new GiftCardConfiguration();
        $configuration->setChannel($channel);
        $configuration->setTenderMode($tenderMode);

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

    private function createOrder(ChannelInterface $channel, int $giftCards = 0, int $goods = 0): Order
    {
        $order = new Order();
        $order->setChannel($channel);
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');

        for ($i = 0; $i < $giftCards; ++$i) {
            $order->addItem($this->createItem(isGiftCard: true, suffix: (string) $i));
        }

        for ($i = 0; $i < $goods; ++$i) {
            $order->addItem($this->createItem(isGiftCard: false, suffix: (string) $i));
        }

        return $order;
    }

    private function createItem(bool $isGiftCard, string $suffix): OrderItem
    {
        $product = new Product();
        $product->setCode('GIFT_CARD_TENDER_' . ($isGiftCard ? 'GC' : 'ORD') . '_' . $suffix);
        $product->setGiftCard($isGiftCard);

        // Sylius' own OrderProductEligibility runs in this group and reads the product's name,
        // which is translatable - without a locale it throws before the plugin's constraint runs.
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setName('Gift card tender wiring product ' . $suffix);

        // Sylius resolves an item's product through its variant.
        $variant = new ProductVariant();
        $variant->setCode($product->getCode() . '_VARIANT');
        $product->addVariant($variant);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(5_000);

        // A line with no units is worth nothing, and Sylius never produces one - a real order item
        // always has a unit per quantity. Without this the fixture's order total is zero, and every
        // assertion about how much of it a gift card may settle is measuring an order that could
        // not exist.
        new OrderItemUnit($item);

        return $item;
    }

    private function createGiftCard(): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('GIFT-TENDER-WIRING');
        $giftCard->setInitialAmount(50_000);
        $giftCard->setExpiresAt(new \DateTime('+1 year'));

        return $giftCard;
    }
}
