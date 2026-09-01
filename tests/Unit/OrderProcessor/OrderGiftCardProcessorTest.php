<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\OrderProcessor;

use Madcoders\SyliusGiftCardPlugin\Checker\GiftCardTenderCheckerInterface;
use Madcoders\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCard;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\OrderProcessor\OrderGiftCardProcessor;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemUnit;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Symfony\Component\Translation\IdentityTranslator;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\Order;

/**
 * The order-total behaviour, which is the part of the plugin most likely to cost real money if it
 * is wrong. Uses real Sylius order objects rather than mocks, because the thing under test is how
 * the adjustments interact with Sylius' own total recalculation.
 */
final class OrderGiftCardProcessorTest extends TestCase
{
    public function testItDoesNothingToAnOrderWithoutGiftCards(): void
    {
        $order = $this->createOrder(10_000);

        $this->createProcessor()->process($order);

        self::assertSame(10_000, $order->getTotal());
        self::assertCount(0, $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT));
    }

    public function testItLeavesTheOrderTotalAloneAndSettlesThePaymentInstead(): void
    {
        // The heart of the tender model: the goods still cost what they cost. A gift card changes
        // who pays, not the price - so the total is untouched and the payment is what drops.
        $order = $this->createOrder(10_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 3_000));

        $this->createProcessor()->process($order);

        self::assertSame(10_000, $order->getTotal(), 'a gift card must not discount the order');
        self::assertSame(7_000, self::paymentAmountOf($order));
    }

    public function testTheAdjustmentCarriesTheGiftCardCodeSoItCanBeTracedBack(): void
    {
        $order = $this->createOrder(10_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 3_000));

        $this->createProcessor()->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT);
        self::assertCount(1, $adjustments);

        $adjustment = $adjustments->first();
        self::assertNotFalse($adjustment);
        self::assertSame('GIFT-A', $adjustment->getOriginCode());
        self::assertSame(-3_000, $adjustment->getAmount());
    }

    public function testSeveralGiftCardsStack(): void
    {
        $order = $this->createOrder(10_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 3_000));
        $order->addGiftCard($this->createGiftCard('GIFT-B', 2_500));

        $this->createProcessor()->process($order);

        self::assertSame(10_000, $order->getTotal());
        self::assertSame(4_500, self::paymentAmountOf($order));
        self::assertCount(2, $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT));
    }

    public function testAGiftCardWorthMoreThanTheOrderOnlyCoversWhatIsOwed(): void
    {
        // Otherwise the card would lose more balance than the customer actually spent, and the
        // payment would go negative.
        $order = $this->createOrder(4_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 10_000));

        $this->createProcessor()->process($order);

        self::assertSame(4_000, $order->getTotal());
        self::assertSame(0, self::paymentAmountOf($order), 'there is nothing left to pay');

        $adjustment = $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT)->first();
        self::assertNotFalse($adjustment);
        self::assertSame(-4_000, $adjustment->getAmount(), 'only what was owed is taken from the card');
        self::assertTrue($adjustment->isNeutral(), 'the record must not move the order total');
    }

    public function testOnceTheOrderIsCoveredNoFurtherCardIsCharged(): void
    {
        $order = $this->createOrder(3_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 5_000));
        $order->addGiftCard($this->createGiftCard('GIFT-B', 5_000));

        $this->createProcessor()->process($order);

        self::assertSame(0, self::paymentAmountOf($order));
        self::assertCount(
            1,
            $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT),
            'the second card should be left untouched rather than charged for nothing',
        );
    }

    public function testItSkipsCardsThatCannotBeRedeemed(): void
    {
        $order = $this->createOrder(10_000);

        $disabled = $this->createGiftCard('GIFT-DISABLED', 3_000);
        $disabled->disable();

        $expired = $this->createGiftCard('GIFT-EXPIRED', 3_000);
        $expired->setExpiresAt(new \DateTime('2000-01-01'));

        $order->addGiftCard($disabled);
        $order->addGiftCard($expired);

        $this->createProcessor()->process($order);

        self::assertSame(10_000, self::paymentAmountOf($order), 'nothing was covered');
        self::assertCount(0, $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT));
    }

    public function testItLeavesAnEmptyOrderAlone(): void
    {
        $order = new Order();
        $order->addGiftCard($this->createGiftCard('GIFT-A', 3_000));

        $this->createProcessor()->process($order);

        self::assertCount(0, $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT));
    }

    public function testItLeavesAPlacedOrderAlone(): void
    {
        // canBeProcessed() is false once the order leaves the cart. Without that guard, reprocessing
        // a placed or cancelled order would rebuild its coverage and re-settle a payment that has
        // already been taken - corrupting an order nobody is looking at any more.
        $order = $this->createOrder(10_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 3_000));
        $order->setState(Order::STATE_NEW);

        $this->createProcessor()->process($order);

        self::assertCount(0, $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT));
        self::assertSame(10_000, self::paymentAmountOf($order), 'the payment must not be re-settled');
    }

    public function testItSettlesThePaymentThatCheckoutIsUsing(): void
    {
        // The processor only ever looks at a cart-state payment. Anything else belongs to an order
        // that has already been placed.
        $order = $this->createOrder(10_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 3_000));

        $payment = $order->getLastPayment();
        self::assertNotNull($payment);
        self::assertSame(Payment::STATE_CART, $payment->getState());

        $this->createProcessor()->process($order);

        self::assertSame(7_000, $payment->getAmount());
    }

    public function testItNeverLetsAGiftCardPayForAGiftCard(): void
    {
        // The cap from #41. The customer's shoes are settled, the gift card next to them is not,
        // and the payment still asks for the full difference - capping the coverage must never
        // shrink what the shop is paid, or it would hand over a card nobody paid for.
        $order = $this->createOrder(10_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 10_000));

        $this->createProcessor(settleable: 6_000)->process($order);

        self::assertSame(10_000, $order->getTotal());
        self::assertSame(4_000, self::paymentAmountOf($order), 'the gift card line is still payable in cash');

        $adjustment = $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT)->first();
        self::assertNotFalse($adjustment);
        self::assertSame(-6_000, $adjustment->getAmount());
    }

    public function testAnOrderWithNothingSettleableChargesTheFullAmount(): void
    {
        // The gift-card-only basket. Nothing is covered, nothing is recorded, and the customer pays
        // in full - even though a card is still attached, because the checkout constraint (not this
        // processor) is what tells them to take it off.
        $order = $this->createOrder(10_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 10_000));

        $this->createProcessor(settleable: 0)->process($order);

        self::assertSame(10_000, self::paymentAmountOf($order));
        self::assertCount(0, $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT));
    }

    private function createProcessor(?int $settleable = null): OrderGiftCardProcessor
    {
        $tenderChecker = $this->createMock(GiftCardTenderCheckerInterface::class);
        $tenderChecker
            ->method('settleableTotalOf')
            // Null means "no gift card lines here", which is every test above: the whole order is
            // settleable, exactly as it was before the tender rule existed.
            ->willReturnCallback(static fn (BaseOrderInterface $order): int => $settleable ?? $order->getTotal())
        ;

        return new OrderGiftCardProcessor(
            $this->createAdjustmentFactory(),
            new IdentityTranslator(),
            $tenderChecker,
        );
    }

    private function createAdjustmentFactory(): AdjustmentFactoryInterface
    {
        $factory = $this->createMock(AdjustmentFactoryInterface::class);
        $factory
            ->method('createWithData')
            // Honours $neutral: the whole point of the tender model is that the adjustment records
            // what a card covers WITHOUT moving the order total, and a mock that drops the flag
            // would let a regression pass.
            ->willReturnCallback(static function (
                string $type,
                string $label,
                int $amount,
                bool $neutral = false,
            ): Adjustment {
                $adjustment = new Adjustment();
                $adjustment->setType($type);
                $adjustment->setLabel($label);
                $adjustment->setNeutral($neutral);
                $adjustment->setAmount($amount);

                return $adjustment;
            })
        ;

        return $factory;
    }

    private function createOrder(int $unitPrice): Order
    {
        $order = new Order();

        $item = new OrderItem();
        $item->setUnitPrice($unitPrice);

        $unit = new OrderItemUnit($item);
        self::assertNotNull($unit->getOrderItem());

        $order->addItem($item);

        self::assertSame($unitPrice, $order->getTotal(), 'the fixture order should cost what the test expects');

        // Sylius' payment processor runs before this one and has already sized the payment from the
        // order total; the gift card processor settles that payment.
        $payment = new Payment();
        $payment->setCurrencyCode('USD');
        $payment->setAmount($order->getTotal());
        $order->addPayment($payment);

        return $order;
    }

    private static function paymentAmountOf(Order $order): int
    {
        $payment = $order->getLastPayment();
        self::assertNotNull($payment, 'the fixture order should carry a payment');

        return $payment->getAmount();
    }

    private function createGiftCard(string $code, int $amount): GiftCardInterface
    {
        $giftCard = new GiftCard();
        $giftCard->setCode($code);
        $giftCard->setInitialAmount($amount);

        return $giftCard;
    }
}
