<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Modifier;

use Madcoders\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
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

        foreach ($this->giftCardAmountsFor($order) as $code => $amount) {
            $giftCard = $this->findGiftCard($order, $code);
            if (null === $giftCard) {
                continue;
            }

            $initialAmount = $giftCard->getInitialAmount() ?? 0;

            // Never give back more than the card can hold. A cancellation can only undo what this
            // order took, and inflating a card beyond its face value would be free money.
            $amount = min($amount, $initialAmount - $giftCard->getAmount());
            if ($amount <= 0) {
                continue;
            }

            $this->giftCardBalanceModifier->credit($giftCard, $amount, $order);
        }
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
