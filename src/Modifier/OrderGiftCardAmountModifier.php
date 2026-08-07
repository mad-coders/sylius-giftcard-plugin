<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Modifier;

use Madcoders\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTransactionType;
use Madcoders\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;

/**
 * Moves money on and off gift cards when an order is placed or cancelled.
 *
 * It works from the order's gift card *adjustments*, not from the cards attached to the order, so
 * the amount put back on cancellation is exactly the amount that was charged - including the case
 * where a card was only partially used because the order total was smaller than its balance.
 *
 * The balance itself moves through {@see GiftCardBalanceModifierInterface}, the single write path
 * that always records a ledger entry alongside the change. See
 * docs/adr-log/0005-two-customer-links-and-transaction-ledger.md.
 */
final readonly class OrderGiftCardAmountModifier implements OrderGiftCardAmountModifierInterface
{
    public function __construct(private GiftCardBalanceModifierInterface $giftCardBalanceModifier)
    {
    }

    public function debit(BaseOrderInterface $order): void
    {
        if (!$order instanceof OrderInterface) {
            return;
        }

        $customer = $order->getCustomer();

        foreach ($this->giftCardAmountsFor($order) as $code => $amount) {
            $giftCard = $this->findGiftCard($order, $code);
            if (null === $giftCard) {
                continue;
            }

            // The adjustment was capped at the card's balance when it was created, but the card may
            // have been spent elsewhere in the meantime. Take what is actually there rather than
            // letting debit() throw and abort placing the order.
            $amount = min($amount, $giftCard->getAmount());
            if ($amount <= 0) {
                continue;
            }

            $this->giftCardBalanceModifier->debit($giftCard, $amount, $order);

            if ($customer instanceof CustomerInterface) {
                // First redemption decides whose card this is from now on.
                $giftCard->assignRedeemer($customer);
            }
        }
    }

    public function credit(BaseOrderInterface $order): void
    {
        if (!$order instanceof OrderInterface) {
            return;
        }

        foreach (array_keys($this->giftCardAmountsFor($order)) as $code) {
            $giftCard = $this->findGiftCard($order, $code);
            if (null === $giftCard) {
                continue;
            }

            // Give back what the ledger says this order actually took, not what the adjustment says
            // it intended to take. The two differ whenever the debit was clamped because the card
            // had been spent elsewhere in the meantime - crediting the adjustment would then hand
            // back more than was ever charged and inflate the card.
            //
            // Credits already recorded for this order are netted off, so a cancellation that fires
            // twice is a no-op rather than paying out twice.
            $amount = $this->outstandingDebitFor($giftCard, $order);
            if ($amount <= 0) {
                continue;
            }

            // refund(), not credit(): the face-value cap guards admin top-ups against a typo, and
            // applying it here would make a card that was topped up after being spent unable to
            // take its own money back - cancelling the order would fail outright.
            $this->giftCardBalanceModifier->refund($giftCard, $amount, $order);
        }
    }

    /**
     * What this order still has taken from the card: its debits less any credits already given
     * back for the same order.
     */
    private function outstandingDebitFor(GiftCardInterface $giftCard, BaseOrderInterface $order): int
    {
        $outstanding = 0;

        foreach ($giftCard->getTransactions() as $transaction) {
            if (!self::belongsToOrder($transaction, $order)) {
                continue;
            }

            $outstanding += match ($transaction->getType()) {
                GiftCardTransactionType::Debit => $transaction->getAmount(),
                GiftCardTransactionType::Credit => -$transaction->getAmount(),
            };
        }

        return $outstanding;
    }

    private static function belongsToOrder(GiftCardTransactionInterface $transaction, BaseOrderInterface $order): bool
    {
        $transactionOrder = $transaction->getOrder();
        if (null === $transactionOrder) {
            return false;
        }

        // An order placed and cancelled inside one unit of work has no id yet, so fall back to
        // identity - two different unsaved orders are still two different objects.
        return null !== $order->getId() && null !== $transactionOrder->getId()
            ? $transactionOrder->getId() === $order->getId()
            : $transactionOrder === $order;
    }

    /**
     * The amount charged per gift card code on this order.
     *
     * Amounts are summed rather than overwritten: nothing stops an order carrying more than one
     * adjustment for the same card, and silently dropping one would leak balance.
     *
     * @return array<string, int> positive amounts, keyed by gift card code
     */
    private function giftCardAmountsFor(OrderInterface $order): array
    {
        $amounts = [];

        foreach ($order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT) as $adjustment) {
            $code = $adjustment->getOriginCode();
            if (null === $code) {
                continue;
            }

            $amounts[$code] = ($amounts[$code] ?? 0) + abs($adjustment->getAmount());
        }

        return $amounts;
    }

    private function findGiftCard(OrderInterface $order, string $code): ?GiftCardInterface
    {
        foreach ($order->getGiftCards() as $giftCard) {
            if ($giftCard->getCode() === $code) {
                return $giftCard;
            }
        }

        return null;
    }
}
