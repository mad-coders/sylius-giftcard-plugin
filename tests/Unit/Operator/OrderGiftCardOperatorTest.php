<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Operator;

use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Checker\GiftCardPurchaseCheckerInterface;
use Madcoders\SyliusGiftCardPlugin\Factory\GiftCardFactory;
use Madcoders\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCard;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardOrigin;
use Madcoders\SyliusGiftCardPlugin\Operator\OrderGiftCardOperator;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Resource\Factory\FactoryInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\Order;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\OrderItem;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\OrderItemUnit;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Product\Product;

/**
 * Issuing gift cards for a paid order. Every mistake here is money given away, so the edge cases
 * carry more weight than the happy path.
 */
final class OrderGiftCardOperatorTest extends TestCase
{
    public function testItIssuesOneCardPerPurchasedUnit(): void
    {
        // Three gift cards bought means three separate codes, not one card worth three times as
        // much - which is why a card belongs to a unit rather than an order item.
        $order = $this->createOrder(giftCardUnits: 3, unitPrice: 5000);

        $this->createOperator()->generate($order);

        $giftCards = $this->createOperator()->giftCardsBoughtOn($order);
        self::assertCount(3, $giftCards);
    }

    public function testAnIssuedCardCarriesWhatWasPaidForTheUnit(): void
    {
        $order = $this->createOrder(giftCardUnits: 1, unitPrice: 5000);

        $operator = $this->createOperator();
        $operator->generate($order);

        $giftCard = $operator->giftCardsBoughtOn($order)[0];
        self::assertSame(5000, $giftCard->getInitialAmount());
        self::assertSame(5000, $giftCard->getAmount());
        self::assertSame(GiftCardOrigin::Order, $giftCard->getOrigin());
    }

    public function testAnIssuedCardGetsACodeAndTheBuyer(): void
    {
        $customer = new Customer();
        $order = $this->createOrder(giftCardUnits: 1, unitPrice: 5000);
        $order->setCustomer($customer);

        $operator = $this->createOperator();
        $operator->generate($order);

        $giftCard = $operator->giftCardsBoughtOn($order)[0];
        self::assertNotNull($giftCard->getCode());
        self::assertSame($customer, $giftCard->getPurchaser());
        self::assertNull($giftCard->getRedeemer(), 'nobody has spent it yet');
    }

    public function testItIgnoresUnitsOfOrdinaryProducts(): void
    {
        $order = $this->createOrder(giftCardUnits: 0, unitPrice: 5000, ordinaryUnits: 2);

        $operator = $this->createOperator();
        $operator->generate($order);

        self::assertCount(0, $operator->giftCardsBoughtOn($order));
    }

    public function testRunningTwiceDoesNotIssueASecondCardForTheSameUnit(): void
    {
        // Nothing guarantees a state machine transition fires exactly once, and a second run would
        // hand out a duplicate card for a single purchase.
        $order = $this->createOrder(giftCardUnits: 2, unitPrice: 5000);

        $operator = $this->createOperator();
        $operator->generate($order);
        $operator->generate($order);

        self::assertCount(2, $operator->giftCardsBoughtOn($order));
    }

    public function testItDoesNotIssueACardForAUnitThatCostNothing(): void
    {
        // A fully discounted unit means the shop was not paid, so there is nothing to put on a card.
        $order = $this->createOrder(giftCardUnits: 1, unitPrice: 0);

        $operator = $this->createOperator();
        $operator->generate($order);

        self::assertCount(0, $operator->giftCardsBoughtOn($order));
    }

    public function testCancellingTheOrderTakesTheCardsOutOfCirculation(): void
    {
        $order = $this->createOrder(giftCardUnits: 2, unitPrice: 5000);

        $operator = $this->createOperator();
        $operator->generate($order);
        $operator->enable($order);
        $operator->disable($order);

        foreach ($operator->giftCardsBoughtOn($order) as $giftCard) {
            self::assertFalse($giftCard->isEnabled());
            self::assertFalse($giftCard->isRedeemable());
        }
    }

    public function testACardIsWorthWhatWasPaidForTheUnitAfterAPromotion(): void
    {
        // A discounted gift card must not be worth more than the customer paid for it, or any
        // promotion on a gift card product becomes an arbitrage.
        $order = $this->createOrder(giftCardUnits: 1, unitPrice: 5000);
        $this->adjustEveryUnit($order, AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT, -1000, neutral: false);

        $operator = $this->createOperator();
        $operator->generate($order);

        self::assertSame(4000, $operator->giftCardsBoughtOn($order)[0]->getInitialAmount());
    }

    public function testACardIsWorthItsPriceWithoutTheTaxChargedOnTopOfIt(): void
    {
        // A tax-exclusive shop adds the tax to the unit as a non-neutral adjustment. Leaving it in
        // would hand a customer who asked for a 50 card a 55 one - the card is worth the goods, not
        // the invoice line.
        $order = $this->createOrder(giftCardUnits: 1, unitPrice: 5000);
        $this->adjustEveryUnit($order, AdjustmentInterface::TAX_ADJUSTMENT, 500, neutral: false);

        $operator = $this->createOperator();
        $operator->generate($order);

        self::assertSame(5000, $operator->giftCardsBoughtOn($order)[0]->getInitialAmount());
    }

    public function testATaxInclusiveShopIssuesTheSameCardAsATaxExclusiveOne(): void
    {
        // Included tax is a neutral adjustment, so the unit total already excludes it. The two shops
        // must not disagree about what the same choice is worth.
        $order = $this->createOrder(giftCardUnits: 1, unitPrice: 5000);
        $this->adjustEveryUnit($order, AdjustmentInterface::TAX_ADJUSTMENT, 500, neutral: true);

        $operator = $this->createOperator();
        $operator->generate($order);

        self::assertSame(5000, $operator->giftCardsBoughtOn($order)[0]->getInitialAmount());
    }

    public function testTheCustomerMessageIsCarriedOntoEveryCardOfThatLine(): void
    {
        $order = $this->createOrder(giftCardUnits: 2, unitPrice: 5000, message: 'Happy birthday!');

        $operator = $this->createOperator();
        $operator->generate($order);

        foreach ($operator->giftCardsBoughtOn($order) as $giftCard) {
            self::assertSame('Happy birthday!', $giftCard->getCustomMessage());
        }
    }

    public function testACardBoughtWithoutAMessageCarriesNone(): void
    {
        $order = $this->createOrder(giftCardUnits: 1, unitPrice: 5000);

        $operator = $this->createOperator();
        $operator->generate($order);

        self::assertNull($operator->giftCardsBoughtOn($order)[0]->getCustomMessage());
    }

    public function testTwoLinesInOneOrderKeepTheirOwnMessagesAndAmounts(): void
    {
        // Criterion 6 of #34 and criterion 7 of #35 in one: the choice belongs to the line, and two
        // lines must not bleed into each other.
        $order = new Order();
        $order->setChannel(self::createChannel());
        $order->addItem($this->createItem($this->createProduct(isGiftCard: true), 5000, 1, 'For Ann'));
        $order->addItem($this->createItem($this->createProduct(isGiftCard: true), 12000, 1, 'For Bob'));

        $operator = $this->createOperator();
        $operator->generate($order);

        $giftCards = $operator->giftCardsBoughtOn($order);
        self::assertCount(2, $giftCards);
        self::assertSame(5000, $giftCards[0]->getInitialAmount());
        self::assertSame('For Ann', $giftCards[0]->getCustomMessage());
        self::assertSame(12000, $giftCards[1]->getInitialAmount());
        self::assertSame('For Bob', $giftCards[1]->getCustomMessage());
    }

    public function testAnOrderWithoutAChannelIssuesNothing(): void
    {
        // Sylius rejects setChannel(null), so the order is built without one - the state a cart
        // is in before the channel context has resolved.
        $order = $this->createOrder(giftCardUnits: 1, unitPrice: 5000, withChannel: false);

        $operator = $this->createOperator();
        $operator->generate($order);

        self::assertCount(0, $operator->giftCardsBoughtOn($order));
    }

    public function testAChannelThatIssuesCardsByAdministratorOnlyIssuesNothingWhenAnOrderIsPaid(): void
    {
        // The cart is refused a gift card product in this mode, but a cart filled before the
        // channel changed mode is already sitting there - and paying it must not hand out a card.
        $order = $this->createOrder(giftCardUnits: 2, unitPrice: 5000);

        $operator = $this->createOperator(sellable: false);
        $operator->generate($order);
        $operator->enable($order);

        self::assertCount(0, $operator->giftCardsBoughtOn($order));
    }

    public function testRefusingToIssueOnAPaidOrderIsLoggedLoudly(): void
    {
        // The customer has been charged and gets no card. If this is silent, the only way anyone
        // finds out is a complaint - so the refusal has to be discoverable in production.
        $order = $this->createOrder(giftCardUnits: 2, unitPrice: 5000);

        $context = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->willReturnCallback(static function (string $message, array $loggedContext) use (&$context): void {
                $context = $loggedContext;
            })
        ;

        $this->createOperator(sellable: false, logger: $logger)->generate($order);

        self::assertSame(2, $context['gift_card_units']);
        self::assertSame('WEB', $context['channel_code']);
    }

    public function testAnOrderWithoutGiftCardsIsNotLoggedInAChannelThatDoesNotSellThem(): void
    {
        // Every ordinary order in an admin-only channel passes through here. Warning on each one
        // would bury the case that matters under noise.
        $order = $this->createOrder(giftCardUnits: 0, unitPrice: 5000, ordinaryUnits: 3);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $this->createOperator(sellable: false, logger: $logger)->generate($order);
    }

    private function createOperator(bool $sellable = true, ?LoggerInterface $logger = null): OrderGiftCardOperator
    {
        /** @var FactoryInterface<GiftCardInterface> $inner */
        $inner = $this->createMock(FactoryInterface::class);
        $inner->method('createNew')->willReturnCallback(static fn (): GiftCard => new GiftCard());

        $codeGenerator = $this->createMock(GiftCardCodeGeneratorInterface::class);
        $codeGenerator->method('generate')->willReturnCallback(
            static fn (?GiftCardConfigurationInterface $c = null): string => 'GIFT-' . bin2hex(random_bytes(4)),
        );

        $configurationProvider = $this->createMock(GiftCardConfigurationProviderInterface::class);
        $configurationProvider->method('getForChannel')->willReturn(null);

        $purchaseChecker = $this->createMock(GiftCardPurchaseCheckerInterface::class);
        $purchaseChecker->method('canBeBoughtIn')->willReturn($sellable);

        return new OrderGiftCardOperator(
            new GiftCardFactory($inner),
            $codeGenerator,
            $configurationProvider,
            $this->createMock(ObjectManager::class),
            $purchaseChecker,
            $logger,
        );
    }

    private function createOrder(
        int $giftCardUnits,
        int $unitPrice,
        int $ordinaryUnits = 0,
        bool $withChannel = true,
        ?string $message = null,
    ): Order {
        $order = new Order();

        if ($withChannel) {
            $order->setChannel(self::createChannel());
        }

        if ($giftCardUnits > 0) {
            $order->addItem($this->createItem($this->createProduct(isGiftCard: true), $unitPrice, $giftCardUnits, $message));
        }

        if ($ordinaryUnits > 0) {
            $order->addItem($this->createItem($this->createProduct(isGiftCard: false), $unitPrice, $ordinaryUnits));
        }

        return $order;
    }

    private function createItem(Product $product, int $unitPrice, int $units, ?string $message = null): OrderItem
    {
        // Sylius resolves an item's product through its variant, so the fixture has to build the
        // variant rather than setting the product directly.
        $variant = new ProductVariant();
        $variant->setCode($product->getCode() . '-VARIANT');
        $product->addVariant($variant);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice($unitPrice);
        $item->setGiftCardMessage($message);

        for ($i = 0; $i < $units; ++$i) {
            new OrderItemUnit($item);
        }

        return $item;
    }

    /** Puts the same adjustment on every unit of the order, as Sylius' tax and promotion processors do. */
    private function adjustEveryUnit(Order $order, string $type, int $amount, bool $neutral): void
    {
        foreach ($order->getItems() as $item) {
            foreach ($item->getUnits() as $unit) {
                $adjustment = new Adjustment();
                $adjustment->setType($type);
                $adjustment->setAmount($amount);
                $adjustment->setNeutral($neutral);

                $unit->addAdjustment($adjustment);
            }
        }
    }

    private static function createChannel(): Channel
    {
        $currency = new Currency();
        $currency->setCode('USD');

        $channel = new Channel();
        $channel->setCode('WEB');
        $channel->setBaseCurrency($currency);

        return $channel;
    }

    private function createProduct(bool $isGiftCard): Product
    {
        $product = new Product();
        $product->setCode('PRODUCT-' . ($isGiftCard ? 'GC' : 'ORD'));
        $product->setGiftCard($isGiftCard);

        return $product;
    }
}
