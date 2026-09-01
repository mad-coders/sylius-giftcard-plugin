<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\RateLimiter;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * @see GiftCardRedemptionLimiterInterface
 */
final readonly class GiftCardRedemptionLimiter implements GiftCardRedemptionLimiterInterface
{
    /** The whole shop shares one bucket for the wide limiter, so the key is a constant. */
    private const string SHOP_KEY = 'shop';

    /** Keys for the throttle that stops a misconfiguration warning being written once per request. */
    private const string UNTRUSTED_PROXY_NOTICE = 'notice.untrusted-proxy';

    private const string UNIDENTIFIED_CLIENT_NOTICE = 'notice.unidentified-client';

    public function __construct(
        // Typed against the concrete factory, not RateLimiterFactoryInterface: that interface does
        // not exist before Symfony 7.3, and the plugin supports 6.4. The concrete class is what
        // framework.rate_limiter builds on every supported version.
        private RateLimiterFactory $clientLimiterFactory,
        /**
         * How often a success may wipe a client's failures. Capped, because applying a card is free,
         * repeatable and does not debit it - see clear().
         */
        private RateLimiterFactory $resetLimiterFactory,
        /**
         * One bucket for the whole shop, so guessing spread across many addresses is still visible
         * and, if the host asks for it, still bounded.
         */
        private RateLimiterFactory $shopLimiterFactory,
        /**
         * Whether exhausting the shop-wide window refuses redemption for everybody, or only raises an
         * alert. Off by default: a shop-wide block is a kill switch on the money path that anybody
         * with a botnet can pull.
         */
        private bool $shopLimitBlocks = false,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function isBlocked(Request $request): bool
    {
        $key = $this->keyFor($request);

        if (null === $key) {
            return false;
        }

        if ($this->shopLimitBlocks && $this->shopLimiterFactory->create(self::SHOP_KEY)->consume(0)->getRemainingTokens() < 1) {
            return true;
        }

        // consume(0) reads the window without spending from it - only a failed apply costs a token.
        // isAccepted() is no use here: a zero-token consume is always accepted, even on an exhausted
        // window, so the remaining tokens are what has to be looked at.
        //
        // Nothing is logged here. The refusal is logged once, where it happens - in recordFailure() -
        // because a blocked client that keeps posting would otherwise write one warning per request,
        // and an attacker choosing how fast to hammer a shop should not also be choosing how fast it
        // writes to disk.
        return $this->clientLimiterFactory->create($key)->consume(0)->getRemainingTokens() < 1;
    }

    public function recordFailure(Request $request): void
    {
        $key = $this->keyFor($request);

        if (null === $key) {
            return;
        }

        $clientLimit = $this->clientLimiterFactory->create($key)->consume();
        $shopLimit = $this->shopLimiterFactory->create(self::SHOP_KEY)->consume();

        // `accepted and nothing left` is the attempt that exhausted the window, and only that one:
        // consuming from an already-empty window is refused, so this cannot fire twice. It is also
        // the only place a warning is written, which makes the log one line per client per window.
        if ($clientLimit->isAccepted() && $clientLimit->getRemainingTokens() < 1) {
            $this->logger?->warning('Gift card redemption refused: a client has used up its failed attempts.', [
                'key' => $key,
                'limit' => $clientLimit->getLimit(),
                'retry_after' => $clientLimit->getRetryAfter()->format(\DateTimeInterface::ATOM),
                // Shop-wide failures in this window. Unlike the per-client count - which is always
                // exactly the limit by the time we get here, and so says nothing - this varies, and
                // is what separates one customer fumbling from a wave of guessing.
                'shop_failed_attempts' => $shopLimit->getLimit() - $shopLimit->getRemainingTokens(),
            ]);
        }

        if ($shopLimit->isAccepted() && $shopLimit->getRemainingTokens() < 1) {
            $this->logger?->error('Gift card redemption: the shop-wide failed attempt limit has been reached.', [
                'limit' => $shopLimit->getLimit(),
                'retry_after' => $shopLimit->getRetryAfter()->format(\DateTimeInterface::ATOM),
                'blocking' => $this->shopLimitBlocks,
            ]);
        }
    }

    public function clear(Request $request): void
    {
        $key = $this->keyFor($request);

        if (null === $key) {
            return;
        }

        // Capped, and this cap is load-bearing. Applying a card neither debits it nor prevents the
        // same card being applied again after a removal, so a success is something an attacker can
        // manufacture at will with one cheap card. An uncapped reset would therefore have sold
        // unlimited guessing for the price of the smallest gift card in the shop. Allowing one reset
        // per window bounds a client to twice its allowance and still forgives the customer who
        // fumbled a code before getting it right.
        if (!$this->resetLimiterFactory->create($key)->consume()->isAccepted()) {
            return;
        }

        $this->clientLimiterFactory->create($key)->reset();
    }

    /**
     * How this client is identified, or null when it cannot be identified safely.
     *
     * Null means the limiter stands down for that request. That is the deliberate direction to fail:
     * the alternative - lumping unidentifiable requests into one shared bucket - hands any one of
     * them the power to lock out all the others, and refusing to redeem gift cards for a whole shop
     * is a worse outcome than not throttling a request that should not occur in a served request.
     */
    private function keyFor(Request $request): ?string
    {
        // Behind a CDN or load balancer with no trusted proxies configured, getClientIp() returns the
        // edge address, so the entire shop collapses into a handful of buckets and eleven bad codes
        // lock everybody out. That is a worse failure than not limiting, and it is silent, so refuse
        // to arm rather than leave the host to read the documentation.
        if ($this->isBehindAnUntrustedProxy($request)) {
            $this->noticeOnce(
                self::UNTRUSTED_PROXY_NOTICE,
                'Gift card redemption is not being rate limited: the request carries forwarding headers but framework.trusted_proxies is not configured, so every customer would share the proxy\'s address. Configure trusted proxies - see docs/INSTALLATION.md.',
            );

            return null;
        }

        $clientIp = $request->getClientIp();

        if (null === $clientIp) {
            $this->noticeOnce(
                self::UNIDENTIFIED_CLIENT_NOTICE,
                'Gift card redemption is not being rate limited: the request carries no client address.',
            );

            return null;
        }

        return $this->aggregate($clientIp);
    }

    private function isBehindAnUntrustedProxy(Request $request): bool
    {
        if ([] !== Request::getTrustedProxies()) {
            return false;
        }

        return $request->headers->has('x-forwarded-for') || $request->headers->has('forwarded');
    }

    /**
     * Narrows an address to the smallest block one party is assumed to control.
     *
     * A bare IPv6 address is not a client, it is one of the 2^64 a single cheap VPS is handed, so
     * keying on it would let an attacker rotate through free buckets - which is the very objection
     * this plugin's ADR raises against composite keys. /64 is the standard end-site allocation, so
     * that is the unit. IPv4 is left alone: addresses there are scarce enough to be the unit already,
     * and aggregating further would sweep unrelated customers into one bucket.
     */
    private function aggregate(string $clientIp): string
    {
        $packed = @inet_pton($clientIp);

        if (false === $packed || 16 !== \strlen($packed)) {
            return $clientIp;
        }

        $network = inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8));

        return false === $network ? $clientIp : $network . '/64';
    }

    /**
     * Writes a warning at most once per window, so a misconfiguration is reported rather than used as
     * a way to make the shop write a log line per request.
     */
    private function noticeOnce(string $key, string $message): void
    {
        if (null === $this->logger) {
            return;
        }

        if ($this->resetLimiterFactory->create($key)->consume()->isAccepted()) {
            $this->logger->warning($message);
        }
    }
}
