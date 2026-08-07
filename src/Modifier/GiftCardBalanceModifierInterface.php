<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Modifier;

use Madcoders\SyliusGiftCardPlugin\Exception\InvalidGiftCardAmountException;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;

/**
 * The single place a gift card's balance is allowed to change.
 *
 * Every method here moves the balance *and* writes the matching ledger entry, which is what makes
 * the invariant in docs/adr-log/0005-two-customer-links-and-transaction-ledger.md structural rather
 * than a convention every caller has to remember.
 */
interface GiftCardBalanceModifierInterface
{
    /**
     * @param BaseOrderInterface|null $order the order that caused the change; null for a manual
     *                                       admin adjustment
     *
     * @throws InvalidGiftCardAmountException
     */
    public function debit(GiftCardInterface $giftCard, int $amount, ?BaseOrderInterface $order = null): void;

    /**
     * Puts money on a card that it did not previously hold, capped at its face value.
     *
     * @throws InvalidGiftCardAmountException
     */
    public function credit(GiftCardInterface $giftCard, int $amount, ?BaseOrderInterface $order = null): void;

    /**
     * Gives back money an order took off a card, which the face-value cap does not apply to.
     *
     * Only ever called with an amount the ledger shows the order debited; see
     * {@see GiftCardInterface::refund()} for why the two are separate.
     *
     * @throws InvalidGiftCardAmountException
     */
    public function refund(GiftCardInterface $giftCard, int $amount, ?BaseOrderInterface $order = null): void;
}
