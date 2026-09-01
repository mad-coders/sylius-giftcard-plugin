<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\RateLimiter;

use Madcoders\SyliusGiftCardPlugin\RateLimiter\GiftCardRedemptionLimiter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * How much guessing one client gets before the redeem field stops answering.
 */
final class GiftCardRedemptionLimiterTest extends TestCase
{
    private const int LIMIT = 3;

    public function testAClientWithNoHistoryIsNotBlocked(): void
    {
        self::assertFalse($this->createLimiter()->isBlocked($this->requestFrom('203.0.113.5')));
    }

    public function testItBlocksOnceTheFailedAttemptsAreUsedUp(): void
    {
        $limiter = $this->createLimiter();
        $request = $this->requestFrom('203.0.113.5');

        for ($attempt = 0; $attempt < self::LIMIT; ++$attempt) {
            self::assertFalse($limiter->isBlocked($request), 'The limiter refused before the allowance ran out.');

            $limiter->recordFailure($request);
        }

        self::assertTrue($limiter->isBlocked($request));
    }

    public function testAskingWhetherAClientIsBlockedDoesNotCostItAnAttempt(): void
    {
        // isBlocked() is asked before every attempt, including the ones that turn out to succeed.
        // If the question itself consumed an attempt the allowance would be half what it says.
        $limiter = $this->createLimiter();
        $request = $this->requestFrom('203.0.113.5');

        for ($ask = 0; $ask < 50; ++$ask) {
            self::assertFalse($limiter->isBlocked($request));
        }
    }

    public function testASuccessfulRedemptionForgetsTheFailedAttemptsBeforeIt(): void
    {
        $limiter = $this->createLimiter();
        $request = $this->requestFrom('203.0.113.5');

        for ($attempt = 0; $attempt < self::LIMIT; ++$attempt) {
            $limiter->recordFailure($request);
        }

        $limiter->clear($request);

        self::assertFalse($limiter->isBlocked($request));
    }

    public function testOneClientRunningOutDoesNotBlockAnother(): void
    {
        $limiter = $this->createLimiter();
        $guesser = $this->requestFrom('203.0.113.5');

        for ($attempt = 0; $attempt < self::LIMIT; ++$attempt) {
            $limiter->recordFailure($guesser);
        }

        self::assertTrue($limiter->isBlocked($guesser));
        self::assertFalse($limiter->isBlocked($this->requestFrom('198.51.100.7')));
    }

    public function testARequestWithNoClientAddressStillGetsALimitRatherThanAFreePass(): void
    {
        $limiter = $this->createLimiter();
        $request = new Request();
        $request->server->remove('REMOTE_ADDR');

        for ($attempt = 0; $attempt < self::LIMIT; ++$attempt) {
            $limiter->recordFailure($request);
        }

        self::assertTrue($limiter->isBlocked($request));
    }

    public function testItLogsARefusalWithTheClientAndTheNumberOfAttempts(): void
    {
        $context = [];

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->willReturnCallback(function (string|\Stringable $message, array $logContext) use (&$context): void {
                $context = $logContext;
            })
        ;

        $limiter = $this->createLimiter($logger);
        $request = $this->requestFrom('203.0.113.5');

        for ($attempt = 0; $attempt < self::LIMIT; ++$attempt) {
            $limiter->recordFailure($request);
        }

        $limiter->isBlocked($request);

        // The key and the count are what a shop alerts on. The submitted code is not here and cannot
        // be: the limiter is never given it.
        self::assertSame('203.0.113.5', $context['key']);
        self::assertSame(self::LIMIT, $context['failed_attempts']);
    }

    public function testItLogsNothingWhileTheClientIsStillWithinItsAllowance(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $this->createLimiter($logger)->isBlocked($this->requestFrom('203.0.113.5'));
    }

    private function createLimiter(?LoggerInterface $logger = null): GiftCardRedemptionLimiter
    {
        // The real factory over in-memory storage rather than a mock: the interesting behaviour -
        // that a zero-token read does not spend one - belongs to the component, so faking it would
        // only assert that the test author remembered how it works.
        $factory = new RateLimiterFactory(
            [
                'id' => 'madcoders_sylius_gift_card_redemption',
                'policy' => 'fixed_window',
                'limit' => self::LIMIT,
                'interval' => '15 minutes',
            ],
            new InMemoryStorage(),
        );

        return new GiftCardRedemptionLimiter($factory, $logger);
    }

    private function requestFrom(string $clientIp): Request
    {
        return new Request(server: ['REMOTE_ADDR' => $clientIp]);
    }
}
