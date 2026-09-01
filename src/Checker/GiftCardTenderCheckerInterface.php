<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Checker;

use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;

/**
 * The single answer to "how much of this order may gift cards settle?".
 *
 * It exists as its own service because the answer is needed in three places that are nowhere near
 * each other - the applicator, the checkout constraint and the order processor - and three copies
 * of the rule would drift, which is how the loophole in #41 survived being written down in ADR 0014
 * without being closed.
 *
 * See docs/adr-log/0016-a-gift-card-does-not-buy-a-gift-card.md.
 */
interface GiftCardTenderCheckerInterface
{
    /**
     * The largest amount, in minor units, that applied gift cards may cover on this order.
     *
     * Never negative, and never above the order total. Under the default per-channel rule it is the
     * order total less what the gift card lines on it are worth, so a gift card can pay for the
     * shoes in a basket but never for the gift card next to them.
     */
    public function settleableTotalOf(BaseOrderInterface $order): int;

    /**
     * Whether a gift card may be redeemed against this order at all.
     *
     * False only when the order's own gift card lines have eaten the whole settleable total, which
     * is the gift-card-only basket the rule exists to refuse. An order with nothing on it, or one
     * carrying no gift card products, is never refused here - it simply has nothing to settle, and
     * saying "gift cards cannot pay for gift cards" to somebody buying shoes would be a lie.
     */
    public function allowsRedemptionOn(BaseOrderInterface $order): bool;
}
