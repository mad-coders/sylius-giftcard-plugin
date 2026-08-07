<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\StateResolver;

use Madcoders\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;

/**
 * Decides an order's payment state when part of it was settled with a gift card.
 *
 * Sylius' own resolver compares completed payments against `Order::getTotal()`. Under the tender
 * model the total stays at the full value of the goods while the payment carries only what the
 * customer owes, so an order part-paid by a card would sit at `partially_paid` for ever, and one
 * covered entirely by a card would never leave `awaiting_payment`.
 *
 * That is not cosmetic. The `pay` transition is what issues purchased gift cards and emails their
 * codes, and `paid` is what lets an order be fulfilled - so without this, a customer who buys a
 * gift card and pays for it partly with another one is charged, receives nothing, and the order
 * can never be shipped.
 *
 * Only the "is it settled" question is answered here; everything else - refunds, authorizations,
 * partial payments with no gift card involved - is left to Sylius.
 */
final readonly class GiftCardAwareOrderPaymentStateResolver implements StateResolverInterface
{
    public function __construct(
        private StateResolverInterface $decorated,
        private StateMachineInterface $stateMachine,
    ) {
    }

    public function resolve(BaseOrderInterface $order): void
    {
        if (!$order instanceof OrderInterface || 0 === $order->getGiftCardTotal()) {
            $this->decorated->resolve($order);

            return;
        }

        if (!$this->isSettled($order)) {
            // Genuinely short - let Sylius decide between partially_paid, awaiting_payment and the
            // rest. Its comparison against the full total is pessimistic here rather than wrong.
            $this->decorated->resolve($order);

            return;
        }

        if ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_PAY)) {
            $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_PAY);
        }
    }

    /**
     * Whether the customer has settled what they actually owed, gift cards included.
     */
    private function isSettled(OrderInterface $order): bool
    {
        $amountToPay = $order->getAmountToPay();

        // Nothing left to pay: the cards covered the order outright, so there is no gateway payment
        // to wait for.
        if ($amountToPay <= 0) {
            return true;
        }

        $completed = 0;

        foreach ($order->getPayments() as $payment) {
            if ($payment instanceof PaymentInterface && PaymentInterface::STATE_COMPLETED === $payment->getState()) {
                $completed += $payment->getAmount() ?? 0;
            }
        }

        return $completed >= $amountToPay;
    }
}
