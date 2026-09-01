<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\RateLimiter;

use Symfony\Component\HttpFoundation\Request;

/**
 * Throttles how often one client may fail to redeem a gift card.
 *
 * A gift card code is bearer money: whoever types it can spend it, so an endpoint that accepts
 * unlimited attempts is a brute-force oracle. Only *failed* attempts are counted, and a successful
 * one forgets them, so a customer who types their own code correctly is never affected.
 *
 * The submitted code is deliberately not part of any of these signatures. The limiter logs, and a
 * code must never reach a log - keeping it out of the interface makes that structural rather than a
 * rule someone has to remember.
 *
 * See docs/adr-log/0012-rate-limiting-gift-card-redemption.md for how the client is identified and
 * what that costs.
 */
interface GiftCardRedemptionLimiterInterface
{
    /**
     * Whether this client has already used up its failed attempts for the current window.
     *
     * Answering this must not consume an attempt: it is asked before we know whether the attempt
     * about to be made will succeed.
     */
    public function isBlocked(Request $request): bool;

    /**
     * Counts one failed attempt against this client.
     */
    public function recordFailure(Request $request): void;

    /**
     * Forgets this client's failed attempts, after one of them turned out to be a real code.
     *
     * Call this only when a card was **newly** redeemed. Re-submitting a code that is already on the
     * cart succeeds without changing anything and without debiting the card, so treating that as a
     * redemption would let one cheap card refill the allowance for ever.
     *
     * Implementations are expected to cap how often this can take effect, because even a genuinely
     * new redemption is repeatable - remove the card, apply it again - and so is not, on its own,
     * evidence that the caller is not guessing.
     */
    public function clear(Request $request): void;
}
