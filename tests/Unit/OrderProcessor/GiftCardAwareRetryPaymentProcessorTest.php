<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\OrderProcessor;

use Madcoders\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Madcoders\SyliusGiftCardPlugin\OrderProcessor\GiftCardAwareRetryPaymentProcessor;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemUnit;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\Order;

/**
 * A failed payment is the one place a customer could be charged twice for the same money: the card
 * was debited when the order was placed, and Sylius replaces a failed payment with one for the full
 * order total.
 */
final class GiftCardAwareRetryPaymentProcessorTest extends TestCase
{
    public function testARetriedPaymentAsksOnlyForWhatTheCardsDidNotCover(): void
    {
        $order = $this->createPlacedOrder(10_000, giftCardCoverage: 4_000);
        $this->addFailedPayment($order, 6_000);
        $retry = $this->addRetryPayment($order);

        $this->createProcessor()->process($order);

        self::assertSame(6_000, $retry->getAmount(), 'the customer must not be asked for the gift card money again');
        self::assertSame(10_000, $order->getTotal(), 'the goods still cost what they cost');
    }

    public function testARetryOnAnOrderWithoutGiftCardsIsLeftToSylius(): void
    {
        $order = $this->createPlacedOrder(10_000, giftCardCoverage: 0);
        $retry = $this->addRetryPayment($order);

        $this->createProcessor()->process($order);

        self::assertSame(10_000, $retry->getAmount());
    }

    public function testMoneyAlreadyTakenIsNotAskedForAgain(): void
    {
        // A split payment, or a partial capture followed by a retry: 10000 of goods, 4000 on a card,
        // 2000 already captured, so 4000 is genuinely still owed.
        $order = $this->createPlacedOrder(10_000, giftCardCoverage: 4_000);
        $this->addCompletedPayment($order, 2_000);
        $retry = $this->addRetryPayment($order);

        $this->createProcessor()->process($order);

        self::assertSame(4_000, $retry->getAmount());
    }

    public function testAnOrderTheCardsCoveredOutrightAsksForNothing(): void
    {
        $order = $this->createPlacedOrder(10_000, giftCardCoverage: 10_000);
        $retry = $this->addRetryPayment($order);

        $this->createProcessor()->process($order);

        self::assertSame(0, $retry->getAmount(), 'a fully covered order has nothing left to charge');
    }

    public function testItDoesNotInventAPaymentSyliusDecidedNotToCreate(): void
    {
        // Sylius declines to replace the payment on a cancelled or fulfilled order, and removes
        // payments outright in some cases. That decision is left alone.
        $order = $this->createPlacedOrder(10_000, giftCardCoverage: 4_000);
        $this->addFailedPayment($order, 6_000);

        $this->createProcessor()->process($order);

        self::assertCount(1, $order->getPayments());
        self::assertNull($order->getLastPayment(PaymentInterface::STATE_NEW));
    }

    private function createProcessor(): GiftCardAwareRetryPaymentProcessor
    {
        // Stands in for Sylius' after-checkout processor, which sizes the replacement payment from
        // the order total - the behaviour this decorator exists to correct.
        $decorated = new class() implements OrderProcessorInterface {
            public function process(BaseOrderInterface $order): void
            {
                $payment = $order->getLastPayment(PaymentInterface::STATE_NEW);

                $payment?->setAmount($order->getTotal());
            }
        };

        return new GiftCardAwareRetryPaymentProcessor($decorated);
    }

    private function createPlacedOrder(int $unitPrice, int $giftCardCoverage): Order
    {
        $order = new Order();

        $item = new OrderItem();
        $item->setUnitPrice($unitPrice);
        new OrderItemUnit($item);
        $order->addItem($item);

        if (0 !== $giftCardCoverage) {
            // The neutral adjustment the order carries away from checkout: it records what the card
            // covered without moving the total.
            $adjustment = new Adjustment();
            $adjustment->setType(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT);
            $adjustment->setAmount(-$giftCardCoverage);
            $adjustment->setNeutral(true);
            $adjustment->setOriginCode('GIFT-A');
            $order->addAdjustment($adjustment);
        }

        self::assertSame($unitPrice, $order->getTotal(), 'a gift card must not move the order total');

        return $order;
    }

    private function addFailedPayment(Order $order, int $amount): void
    {
        $order->addPayment($this->createPayment($amount, PaymentInterface::STATE_FAILED));
    }

    private function addCompletedPayment(Order $order, int $amount): void
    {
        $order->addPayment($this->createPayment($amount, PaymentInterface::STATE_COMPLETED));
    }

    private function addRetryPayment(Order $order): PaymentInterface
    {
        $payment = $this->createPayment(0, PaymentInterface::STATE_NEW);
        $order->addPayment($payment);

        return $payment;
    }

    private function createPayment(int $amount, string $state): PaymentInterface
    {
        $payment = new Payment();
        $payment->setCurrencyCode('USD');
        $payment->setAmount($amount);
        $payment->setState($state);

        return $payment;
    }
}
