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
    /**
     * Used when the request carries no client address at all, which in practice means a console or
     * a test kernel. One shared bucket is the safe reading: an unidentifiable client gets the same
     * allowance as any other, rather than an unlimited one.
     */
    private const string UNIDENTIFIED_CLIENT = 'unidentified';

    public function __construct(
        private RateLimiterFactory $limiterFactory,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function isBlocked(Request $request): bool
    {
        $key = $this->keyFor($request);

        // consume(0) reads the window without spending from it - only a failed apply costs a token.
        // isAccepted() is no use here: a zero-token consume is always accepted, even on an exhausted
        // window, so the remaining tokens are what has to be looked at.
        $rateLimit = $this->limiterFactory->create($key)->consume(0);

        if ($rateLimit->getRemainingTokens() > 0) {
            return false;
        }

        // Warning rather than info: a client that has spent its whole allowance on codes that do
        // not work is either a customer in real trouble or somebody guessing at bearer money, and a
        // shop wants an alert on both. Every refused request is logged, not just the first, so the
        // rate of the log line measures the pressure.
        $this->logger?->warning('Gift card redemption refused: too many failed attempts from one client.', [
            'key' => $key,
            'failed_attempts' => $rateLimit->getLimit() - $rateLimit->getRemainingTokens(),
            'retry_after' => $rateLimit->getRetryAfter()->format(\DateTimeInterface::ATOM),
        ]);

        return true;
    }

    public function recordFailure(Request $request): void
    {
        $this->limiterFactory->create($this->keyFor($request))->consume();
    }

    public function clear(Request $request): void
    {
        $this->limiterFactory->create($this->keyFor($request))->reset();
    }

    /**
     * The client address, as Symfony resolves it - so a shop behind a load balancer gets the real
     * client rather than its own proxy, provided the host has configured trusted proxies.
     */
    private function keyFor(Request $request): string
    {
        return $request->getClientIp() ?? self::UNIDENTIFIED_CLIENT;
    }
}
