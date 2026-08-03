<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Model;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use PHPUnit\Framework\TestCase;

final class GiftCardConfigurationTest extends TestCase
{
    public function testItCalculatesAnExpiryDateFromItsValidityPeriod(): void
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setValidityPeriod('1 year');

        $expiresAt = $configuration->calculateExpiryDate(new \DateTimeImmutable('2026-08-03 10:00:00'));

        self::assertNotNull($expiresAt);
        self::assertSame('2027-08-03 10:00:00', $expiresAt->format('Y-m-d H:i:s'));
    }

    public function testCardsDoNotExpireWhenNoValidityPeriodIsSet(): void
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setValidityPeriod(null);

        self::assertNull($configuration->calculateExpiryDate());
    }

    public function testCardsDoNotExpireWhenTheValidityPeriodIsBlank(): void
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setValidityPeriod('   ');

        self::assertNull($configuration->calculateExpiryDate());
    }

    public function testAnUnparseableValidityPeriodDoesNotIssueAnAlreadyExpiredCard(): void
    {
        // DateInterval::createFromDateString() yields an all-zero interval for input it cannot
        // parse, which would otherwise expire the card the instant it is created.
        $configuration = new GiftCardConfiguration();
        $configuration->setValidityPeriod('not a period');

        self::assertNull($configuration->calculateExpiryDate(new \DateTimeImmutable('2026-08-03 10:00:00')));
    }

    public function testItDefaultsToOneYearOfValidity(): void
    {
        $configuration = new GiftCardConfiguration();

        $expiresAt = $configuration->calculateExpiryDate(new \DateTimeImmutable('2026-08-03 10:00:00'));

        self::assertNotNull($expiresAt);
        self::assertSame('2027-08-03', $expiresAt->format('Y-m-d'));
    }
}
