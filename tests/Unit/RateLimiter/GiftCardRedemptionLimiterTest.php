<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\RateLimiter;

use Madcoders\SyliusGiftCardPlugin\RateLimiter\GiftCardRedemptionLimiter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * How much guessing one client gets before the redeem field stops answering.
 */
final class GiftCardRedemptionLimiterTest extends TestCase
{
    private const int LIMIT = 3;

    private const int SHOP_LIMIT = 8;

    protected function tearDown(): void
    {
        // Trusted proxies are global to the Request class, so a test that sets them would otherwise
        // decide the outcome of every test that runs after it.
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
    }

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

        $this->exhaust($limiter, $request);
        $limiter->clear($request);

        self::assertFalse($limiter->isBlocked($request));
    }

    public function testOneRealCardDoesNotBuyUnlimitedGuessing(): void
    {
        // The attack this cap exists for: applying a card does not debit it and can be repeated for
        // ever - remove it from the cart, apply it again - so a success is something anybody holding
        // one cheap card can manufacture on demand. If every success wiped the window, the limit
        // would be "ten wrong codes between each of your own", which is no limit at all.
        $limiter = $this->createLimiter();
        $request = $this->requestFrom('203.0.113.5');

        $this->exhaust($limiter, $request);
        $limiter->clear($request);
        self::assertFalse($limiter->isBlocked($request), 'The first success should forgive the failures before it.');

        $this->exhaust($limiter, $request);
        $limiter->clear($request);

        self::assertTrue($limiter->isBlocked($request), 'A second success in the same window bought more guesses.');
    }

    public function testOneClientRunningOutDoesNotBlockAnother(): void
    {
        $limiter = $this->createLimiter();
        $guesser = $this->requestFrom('203.0.113.5');

        $this->exhaust($limiter, $guesser);

        self::assertTrue($limiter->isBlocked($guesser));
        self::assertFalse($limiter->isBlocked($this->requestFrom('198.51.100.7')));
    }

    public function testAddressesInOneIpv6AllocationShareAnAllowance(): void
    {
        // A routed /64 comes free with any cheap VPS, so treating each address in one as a separate
        // client would hand an attacker 2^64 allowances for the price of a single machine.
        $limiter = $this->createLimiter();

        $this->exhaust($limiter, $this->requestFrom('2001:db8:1:1::1'));

        self::assertTrue($limiter->isBlocked($this->requestFrom('2001:db8:1:1:ffff:ffff:ffff:ffff')));
    }

    public function testSeparateIpv6AllocationsDoNotShareAnAllowance(): void
    {
        $limiter = $this->createLimiter();

        $this->exhaust($limiter, $this->requestFrom('2001:db8:1:1::1'));

        self::assertFalse($limiter->isBlocked($this->requestFrom('2001:db8:1:2::1')));
    }

    public function testItStandsDownRatherThanLockOutAShopWhoseProxiesAreNotTrusted(): void
    {
        // getClientIp() falls back to REMOTE_ADDR when trusted proxies are unset, so behind a CDN
        // every customer in the world shares the edge's address. Limiting on that would let eleven
        // wrong codes stop redemption for everybody - a worse outcome, and a silent one - so the
        // limiter refuses to arm and says so instead.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $limiter = $this->createLimiter($logger);
        $request = $this->requestFrom('10.0.0.1', ['HTTP_X_FORWARDED_FOR' => '203.0.113.5']);

        for ($attempt = 0; $attempt < self::LIMIT * 5; ++$attempt) {
            $limiter->recordFailure($request);
        }

        self::assertFalse($limiter->isBlocked($request));
    }

    public function testItLimitsTheForwardedClientOnceTheProxiesAreTrusted(): void
    {
        Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR);

        $limiter = $this->createLimiter();
        $customer = $this->requestFrom('10.0.0.1', ['HTTP_X_FORWARDED_FOR' => '203.0.113.5']);

        $this->exhaust($limiter, $customer);

        self::assertTrue($limiter->isBlocked($customer));
        self::assertFalse(
            $limiter->isBlocked($this->requestFrom('10.0.0.1', ['HTTP_X_FORWARDED_FOR' => '198.51.100.7'])),
            'Both customers arrived through the same proxy, so limiting the proxy would block the shop.',
        );
    }

    public function testARequestWithNoClientAddressIsNotLimitedRatherThanSharedWithEveryOther(): void
    {
        $limiter = $this->createLimiter();
        $request = new Request();
        $request->server->remove('REMOTE_ADDR');

        for ($attempt = 0; $attempt < self::LIMIT * 5; ++$attempt) {
            $limiter->recordFailure($request);
        }

        // One shared bucket for every unidentifiable request would let any one of them lock out all
        // the rest. In a served request there is always an address, so this is the console and tests.
        self::assertFalse($limiter->isBlocked($request));
    }

    public function testTheRefusalIsLoggedOnceWithSomethingThatVaries(): void
    {
        $warnings = [];

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->method('warning')
            ->willReturnCallback(function (string|\Stringable $message, array $context) use (&$warnings): void {
                $warnings[] = $context;
            })
        ;

        $limiter = $this->createLimiter($logger);
        $guesser = $this->requestFrom('203.0.113.5');

        // Somebody else has been failing too, so the shop-wide count is not a function of the limit.
        $limiter->recordFailure($this->requestFrom('198.51.100.7'));
        $limiter->recordFailure($this->requestFrom('198.51.100.7'));

        $this->exhaust($limiter, $guesser);

        // A blocked client that keeps posting must not keep writing. Whoever is hammering the shop
        // should not also be choosing how fast it fills its disk.
        for ($ask = 0; $ask < 20; ++$ask) {
            $limiter->isBlocked($guesser);
        }

        self::assertCount(1, $warnings, 'The refusal should be logged where it happens, once per window.');
        self::assertSame('203.0.113.5', $warnings[0]['key']);
        self::assertSame(self::LIMIT, $warnings[0]['limit']);
        self::assertSame(2 + self::LIMIT, $warnings[0]['shop_failed_attempts']);
    }

    public function testItLogsNothingWhileTheClientIsStillWithinItsAllowance(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $this->createLimiter($logger)->isBlocked($this->requestFrom('203.0.113.5'));
    }

    public function testGuessingSpreadOverManyAddressesIsStillReported(): void
    {
        // No single client comes close to its own limit here: the point of the shop-wide window is
        // that this is exactly what a botnet looks like, and it would otherwise be invisible.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $limiter = $this->createLimiter($logger);

        for ($client = 0; $client < self::SHOP_LIMIT; ++$client) {
            $limiter->recordFailure($this->requestFrom(sprintf('203.0.113.%d', $client + 1)));
        }
    }

    public function testTheShopWideLimitOnlyAlertsUnlessTheHostAsksForMore(): void
    {
        // Both limiters read the same windows, so they differ only in what they do about them.
        $storage = new InMemoryStorage();
        $alerting = $this->createLimiter(storage: $storage);
        $blocking = $this->createLimiter(shopBlocks: true, storage: $storage);

        for ($client = 0; $client < self::SHOP_LIMIT; ++$client) {
            $alerting->recordFailure($this->requestFrom(sprintf('203.0.113.%d', $client + 1)));
        }

        // Refusing every redemption in the shop is a kill switch, and one that anybody with a botnet
        // could pull deliberately. A shop that would rather take that risk can turn it on.
        $innocent = $this->requestFrom('198.51.100.7');

        self::assertFalse($alerting->isBlocked($innocent));
        self::assertTrue($blocking->isBlocked($innocent));
    }

    private function exhaust(GiftCardRedemptionLimiter $limiter, Request $request): void
    {
        for ($attempt = 0; $attempt < self::LIMIT; ++$attempt) {
            $limiter->recordFailure($request);
        }
    }

    private function createLimiter(
        ?LoggerInterface $logger = null,
        bool $shopBlocks = false,
        ?StorageInterface $storage = null,
    ): GiftCardRedemptionLimiter {
        // The real factory over in-memory storage rather than a mock: the interesting behaviour -
        // that a zero-token read does not spend one - belongs to the component, so faking it would
        // only assert that the test author remembered how it works.
        $storage ??= new InMemoryStorage();

        return new GiftCardRedemptionLimiter(
            $this->createFactory('madcoders_sylius_gift_card_redemption', self::LIMIT, $storage),
            $this->createFactory('madcoders_sylius_gift_card_redemption_reset', 1, $storage),
            $this->createFactory('madcoders_sylius_gift_card_redemption_shop', self::SHOP_LIMIT, $storage),
            $shopBlocks,
            $logger,
        );
    }

    private function createFactory(string $id, int $limit, StorageInterface $storage): RateLimiterFactory
    {
        return new RateLimiterFactory(
            [
                'id' => $id,
                'policy' => 'fixed_window',
                'limit' => $limit,
                'interval' => '15 minutes',
            ],
            $storage,
        );
    }

    /** @param array<string, string> $server */
    private function requestFrom(string $clientIp, array $server = []): Request
    {
        return new Request(server: ['REMOTE_ADDR' => $clientIp] + $server);
    }
}
