<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Hook;

use Behat\Behat\Context\Context;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Gives every scenario a fresh redemption allowance.
 *
 * The limiter is keyed on the client address, and every scenario in the suite arrives from the same
 * one. Its storage is a cache pool, which the Doctrine hook that rolls the database back knows
 * nothing about - so without this, failed attempts pile up across scenarios and the first one that
 * happens to run late gets refused for something an earlier scenario did.
 */
final readonly class GiftCardRateLimitContext implements Context
{
    public function __construct(private CacheItemPoolInterface $rateLimiterCache)
    {
    }

    /**
     * @BeforeScenario
     */
    public function forgetPreviousAttempts(): void
    {
        $this->rateLimiterCache->clear();
    }
}
