<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Checker;

use Madcoders\SyliusGiftCardPlugin\Checker\GiftCardTenderChecker;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTenderMode;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\OrderItemUnit;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\Order;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\OrderItem;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Product\Product;

/**
 * How much of an order gift cards may settle. This is the rule that closes the rollover in #41, so
 * the interesting cases are the mixed basket - which must still work - and the gift-card-only one,
 * which must not.
 *
 * Real Sylius order objects rather than mocks: the arithmetic under test is "order total minus the
 * gift card lines", and Sylius' own total calculation is half of it.
 */
final class GiftCardTenderCheckerTest extends TestCase
{
    public function testAnOrderOfOrdinaryGoodsIsFullySettleable(): void
    {
        $order = $this->createOrder(goods: 10_000);

        self::assertSame(10_000, $this->createChecker()->settleableTotalOf($order));
        self::assertTrue($this->createChecker()->allowsRedemptionOn($order));
    }

    public function testAGiftCardOnlyOrderIsNotSettleableAtAll(): void
    {
        // The rollover from #41: a holder buying a new card for exactly their remaining balance and
        // paying nothing. There is nothing here a gift card may pay for.
        $order = $this->createOrder(giftCards: 41_237);

        self::assertSame(0, $this->createChecker()->settleableTotalOf($order));
        self::assertFalse($this->createChecker()->allowsRedemptionOn($order));
    }

    public function testAMixedBasketIsSettleableDownToItsGiftCardLines(): void
    {
        // The decision recorded in ADR 0016: the shoes are still payable with a card, the gift card
        // next to them is not. Refusing the whole basket would punish a customer who did nothing
        // wrong, and it would not close anything the cap does not already close.
        $order = $this->createOrder(goods: 18_000, giftCards: 2_500);

        self::assertSame(20_500, $order->getTotal(), 'the fixture order should cost what the test expects');
        self::assertSame(18_000, $this->createChecker()->settleableTotalOf($order));
        self::assertTrue($this->createChecker()->allowsRedemptionOn($order));
    }

    public function testAChannelCanChooseToLetAGiftCardPayForAGiftCard(): void
    {
        $checker = $this->createChecker($this->createConfiguration(GiftCardTenderMode::Anything));
        $order = $this->createOrder(goods: 18_000, giftCards: 2_500);

        self::assertSame(20_500, $checker->settleableTotalOf($order));
        self::assertTrue($checker->allowsRedemptionOn($order));
    }

    public function testAChannelThatAllowsItStillTakesCardsOnAGiftCardOnlyOrder(): void
    {
        $checker = $this->createChecker($this->createConfiguration(GiftCardTenderMode::Anything));
        $order = $this->createOrder(giftCards: 41_237);

        self::assertSame(41_237, $checker->settleableTotalOf($order));
        self::assertTrue($checker->allowsRedemptionOn($order));
    }

    public function testAChannelWithNoConfigurationGetsTheSafeRule(): void
    {
        // Deliberately NOT "keep behaving as before". Before was the hole, so an unconfigured
        // channel is protected rather than left in it.
        $checker = $this->createChecker(null);

        self::assertFalse($checker->allowsRedemptionOn($this->createOrder(giftCards: 5_000)));
    }

    public function testAnOrdinaryProductThatIsNotAGiftCardCountsAsGoods(): void
    {
        // The flag, not the product type: a shop's ordinary products must never be mistaken for
        // gift cards, or a card would stop settling anything at all.
        $order = new Order();
        $order->setChannel(new Channel());
        $this->addLine($order, 10_000, isGiftCard: false);

        self::assertSame(10_000, $this->createChecker()->settleableTotalOf($order));
    }

    public function testAGiftCardOnlyOrderDoesNotEvenLetACardPayThePostage(): void
    {
        // Shipping is an order-level adjustment, not a line, so subtracting the gift card lines
        // leaves it settleable - which is right when there are goods being posted and wrong when
        // the only thing on the order is the gift cards. Without this the cart refuses redemption
        // and the checkout, two clicks later, lets a card cover the postage on the same basket.
        $order = $this->createOrder(giftCards: 41_237);
        $order->addAdjustment($this->createShippingCharge(1_000));

        self::assertSame(42_237, $order->getTotal());
        self::assertSame(0, $this->createChecker()->settleableTotalOf($order));
        self::assertFalse($this->createChecker()->allowsRedemptionOn($order));
    }

    public function testAMixedBasketStillLetsACardPayThePostage(): void
    {
        // The other side of it: there are goods here, and the postage is for them.
        $order = $this->createOrder(goods: 18_000, giftCards: 2_500);
        $order->addAdjustment($this->createShippingCharge(1_000));

        self::assertSame(21_500, $order->getTotal());
        self::assertSame(19_000, $this->createChecker()->settleableTotalOf($order));
    }

    public function testAGiftCardLinePricedAtZeroStillCountsAsAGiftCardLine(): void
    {
        // The rule keys on the *presence* of a gift card product, not on what it is worth. Reading
        // it the other way would let a line priced at zero switch the rule off for the whole order,
        // which is harmless today and exactly the kind of edge that becomes an exploit after the
        // next pricing feature.
        $order = new Order();
        $order->setChannel(new Channel());
        $this->addLine($order, 0, isGiftCard: true);

        self::assertSame(0, $this->createChecker()->settleableTotalOf($order));
        self::assertFalse($this->createChecker()->allowsRedemptionOn($order));
    }

    public function testAnEmptyOrderIsNotRefusedForAReasonThatDoesNotApply(): void
    {
        // Nothing to settle, but nothing about gift cards either. Saying "a gift card cannot pay
        // for a gift card" to somebody with an empty basket would be a lie, and the checkout
        // constraint that reads this runs on every order in the shop.
        $order = new Order();
        $order->setChannel(new Channel());

        self::assertSame(0, $this->createChecker()->settleableTotalOf($order));
        self::assertTrue($this->createChecker()->allowsRedemptionOn($order));
    }

    public function testATotalSmallerThanItsGiftCardLinesStillNeverGoesNegative(): void
    {
        // An order-level discount lands on the order, not on the lines, so the total can fall below
        // what the gift card lines are worth. The settleable amount is money; it has no negative,
        // and a negative one would be handed straight to min() in the processor and charge a card
        // for less than nothing.
        $order = $this->createOrder(giftCards: 5_000);
        $order->addAdjustment($this->createOrderDiscount(-3_000));

        self::assertSame(2_000, $order->getTotal());
        self::assertSame(0, $this->createChecker()->settleableTotalOf($order));
    }

    private function createChecker(?GiftCardConfigurationInterface $configuration = null): GiftCardTenderChecker
    {
        if (0 === func_num_args()) {
            $configuration = $this->createConfiguration(GiftCardTenderMode::GoodsOnly);
        }

        $provider = $this->createMock(GiftCardConfigurationProviderInterface::class);
        $provider->method('getForChannel')->willReturn($configuration);

        return new GiftCardTenderChecker($provider);
    }

    private function createConfiguration(GiftCardTenderMode $mode): GiftCardConfigurationInterface
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setTenderMode($mode);

        return $configuration;
    }

    private function createOrder(int $goods = 0, int $giftCards = 0): Order
    {
        $order = new Order();
        $order->setChannel(new Channel());

        if ($goods > 0) {
            $this->addLine($order, $goods, isGiftCard: false);
        }

        if ($giftCards > 0) {
            $this->addLine($order, $giftCards, isGiftCard: true);
        }

        return $order;
    }

    private function addLine(Order $order, int $unitPrice, bool $isGiftCard): void
    {
        $product = new Product();
        $product->setGiftCard($isGiftCard);

        $item = new OrderItem();
        $item->setVariant($this->createVariantOf($product));
        $item->setUnitPrice($unitPrice);

        new OrderItemUnit($item);

        $order->addItem($item);
    }

    private function createVariantOf(Product $product): \Sylius\Component\Core\Model\ProductVariantInterface
    {
        $variant = new \Sylius\Component\Core\Model\ProductVariant();
        $variant->setProduct($product);

        return $variant;
    }

    private function createShippingCharge(int $amount): \Sylius\Component\Core\Model\Adjustment
    {
        $adjustment = new \Sylius\Component\Core\Model\Adjustment();
        $adjustment->setType(\Sylius\Component\Core\Model\AdjustmentInterface::SHIPPING_ADJUSTMENT);
        $adjustment->setLabel('Shipping');
        $adjustment->setAmount($amount);

        return $adjustment;
    }

    private function createOrderDiscount(int $amount): \Sylius\Component\Core\Model\Adjustment
    {
        $adjustment = new \Sylius\Component\Core\Model\Adjustment();
        $adjustment->setType('order_promotion');
        $adjustment->setLabel('Discount');
        $adjustment->setAmount($amount);

        return $adjustment;
    }
}
