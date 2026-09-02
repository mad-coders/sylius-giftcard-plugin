<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Calculator;

use Madcoders\SyliusGiftCardPlugin\Calculator\GiftCardExpiryCalculator;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use PHPUnit\Framework\TestCase;

final class GiftCardExpiryCalculatorTest extends TestCase
{
    private const string FROM = '2026-08-03 10:00:00';

    public function testItUsesTheChannelsValidityPeriod(): void
    {
        $expiresAt = $this->calculate('18 months');

        self::assertSame('2028-02-03 10:00:00', $expiresAt->format('Y-m-d H:i:s'));
    }

    public function testAChannelWithNoConfigurationAtAllStillExpiresItsCards(): void
    {
        // The case issue #31 exists for: nothing configured used to mean nothing expires, which is
        // an indefinite liability nobody chose.
        $expiresAt = (new GiftCardExpiryCalculator())->calculate(null, new \DateTimeImmutable(self::FROM));

        self::assertSame('2027-08-03 10:00:00', $expiresAt->format('Y-m-d H:i:s'));
    }

    /**
     * Every way a channel can fail to give a usable period, and the one answer to all of them: the
     * default. None of these may produce a card that never expires, and none may produce one that
     * is already expired.
     *
     * @return iterable<string, array{string|null}>
     */
    public static function unusablePeriods(): iterable
    {
        yield 'not set' => [null];
        yield 'blank' => [''];
        yield 'whitespace' => ['   '];
        yield 'not a period at all' => ['not a period'];
        yield 'a typo' => ['1 yaer'];
        yield 'moves nothing' => ['0 days'];
        yield 'moves backwards' => ['-1 year'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unusablePeriods')]
    public function testAnUnusablePeriodFallsBackToTheDefault(?string $validityPeriod): void
    {
        $expiresAt = $this->calculate($validityPeriod);

        self::assertSame('2027-08-03 10:00:00', $expiresAt->format('Y-m-d H:i:s'));
        self::assertGreaterThan(new \DateTimeImmutable(self::FROM), $expiresAt);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unusablePeriods')]
    public function testItDoesNotUnderstandAnUnusablePeriod(?string $validityPeriod): void
    {
        self::assertFalse((new GiftCardExpiryCalculator())->understands($validityPeriod));
    }

    public function testItUnderstandsAPeriodItCanHonour(): void
    {
        $calculator = new GiftCardExpiryCalculator();

        self::assertTrue($calculator->understands('1 year'));
        self::assertTrue($calculator->understands('30 days'));
        self::assertTrue($calculator->understands('18 months'));
    }

    public function testTheDefaultPeriodItAdvertisesIsTheOneItActuallyApplies(): void
    {
        // The class holds the default twice - once as words for the operator and the docs, once as
        // an interval spec PHP cannot fail to parse. If they ever drift, a shop would be told one
        // thing and given another, and the unparseable-fallback path would be silently wrong.
        $from = new \DateTimeImmutable(self::FROM);

        self::assertEquals(
            $from->add(\DateInterval::createFromDateString(GiftCardExpiryCalculator::DEFAULT_VALIDITY_PERIOD)),
            (new GiftCardExpiryCalculator())->calculate(null, $from),
        );
    }

    public function testItDefaultsToNowWhenGivenNoStartingPoint(): void
    {
        $before = new \DateTimeImmutable();

        $expiresAt = (new GiftCardExpiryCalculator())->calculate(null);

        self::assertGreaterThan($before, $expiresAt);
    }

    private function calculate(?string $validityPeriod): \DateTimeImmutable
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setValidityPeriod($validityPeriod);

        return (new GiftCardExpiryCalculator())->calculate($configuration, new \DateTimeImmutable(self::FROM));
    }
}
