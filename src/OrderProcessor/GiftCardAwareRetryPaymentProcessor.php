<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\OrderProcessor;

use Madcoders\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

/**
 * Keeps a retried payment at what the customer still owes after their gift cards.
 *
 * When a payment fails or is cancelled, Sylius reacts by running its after-checkout payment
 * processor, which creates a replacement payment for `Order::getTotal()`. Under the tender model the
 * total is the full value of the goods, and the gift cards were already debited when the order was
 * placed - so the replacement would charge the customer for money they had already handed over.
 *
 * OrderGiftCardProcessor cannot cover this: it only runs while the order is still a cart, and by the
 * time a payment can fail the order has long since been placed.
 *
 * Only the amount is corrected here. Whether a replacement payment exists at all, and everything
 * about which method it uses, stays Sylius' decision.
 */
final readonly class GiftCardAwareRetryPaymentProcessor implements OrderProcessorInterface
{
    public function __construct(private OrderProcessorInterface $decorated)
    {
    }

    public function process(BaseOrderInterface $order): void
    {
        $this->decorated->process($order);

        // No cards on this order means nothing was covered, so Sylius' amount is already right.
        if (!$order instanceof OrderInterface || 0 === $order->getGiftCardTotal()) {
            return;
        }

        // Sylius creates the replacement in the `new` state. If it decided not to create one - the
        // order is cancelled or fulfilled, or the existing payments were removed - there is nothing
        // to correct.
        $payment = $order->getLastPayment(PaymentInterface::STATE_NEW);

        if (null === $payment) {
            return;
        }

        $payment->setAmount(max(0, $order->getAmountToPay() - $this->alreadyPaid($order)));
    }

    /**
     * What the customer has already successfully paid by card or transfer.
     *
     * A first payment can complete and a later one fail - a split payment, or a partial capture
     * followed by a retry - and that money must not be asked for twice either.
     */
    private function alreadyPaid(OrderInterface $order): int
    {
        $paid = 0;

        foreach ($order->getPayments() as $payment) {
            if ($payment instanceof PaymentInterface && PaymentInterface::STATE_COMPLETED === $payment->getState()) {
                $paid += $payment->getAmount() ?? 0;
            }
        }

        return $paid;
    }
}
