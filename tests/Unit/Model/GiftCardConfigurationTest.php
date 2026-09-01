<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Model;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardAmountMode;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardSaleMode;
use PHPUnit\Framework\TestCase;

final class GiftCardConfigurationTest extends TestCase
{
    public function testANewConfigurationSellsGiftCardsInTheShop(): void
    {
        // The upgrade contract: adding a mode must not stop an existing shop selling gift cards.
        self::assertSame(GiftCardSaleMode::Sellable, (new GiftCardConfiguration())->getSaleMode());
    }

    public function testAChannelCanBeSetToIssueGiftCardsByAdministratorOnly(): void
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setSaleMode(GiftCardSaleMode::AdminOnly);

        self::assertSame(GiftCardSaleMode::AdminOnly, $configuration->getSaleMode());
    }

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

    public function testAChannelSellsGiftCardsAtTheProductPriceUntilItIsToldOtherwise(): void
    {
        $configuration = new GiftCardConfiguration();

        self::assertSame(GiftCardAmountMode::Fixed, $configuration->getAmountMode());
        self::assertFalse($configuration->allowsCustomerChosenAmount());
    }

    public function testAFixedChannelAllowsNoChosenAmountAtAll(): void
    {
        // Not "any amount is fine because nothing is configured": a channel that does not offer a
        // choice must refuse every amount, or a forged one would sail through the processor.
        $configuration = self::configuration(GiftCardAmountMode::Fixed, presets: [5000]);

        self::assertFalse($configuration->isAllowedAmount(5000));
        self::assertFalse($configuration->isAllowedAmount(1));
    }

    public function testAPresetsChannelAllowsOnlyItsPresets(): void
    {
        $configuration = self::configuration(GiftCardAmountMode::Presets, presets: [2500, 5000, 10000]);

        self::assertTrue($configuration->isAllowedAmount(2500));
        self::assertTrue($configuration->isAllowedAmount(10000));
        self::assertFalse($configuration->isAllowedAmount(2501), 'a near miss is still not on offer');
        self::assertFalse($configuration->isAllowedAmount(99999));
    }

    public function testAPresetsChannelIgnoresItsBounds(): void
    {
        // The bounds belong to the free-amount modes. Leaving them set after switching a channel to
        // presets must not quietly reopen the free amount.
        $configuration = self::configuration(
            GiftCardAmountMode::Presets,
            presets: [5000],
            minimum: 1000,
            maximum: 50000,
        );

        self::assertFalse($configuration->isAllowedAmount(2000));
    }

    public function testARangeChannelAllowsAnythingBetweenItsBoundsInclusive(): void
    {
        $configuration = self::configuration(GiftCardAmountMode::Range, minimum: 1000, maximum: 50000);

        self::assertTrue($configuration->isAllowedAmount(1000));
        self::assertTrue($configuration->isAllowedAmount(12345));
        self::assertTrue($configuration->isAllowedAmount(50000));
        self::assertFalse($configuration->isAllowedAmount(999));
        self::assertFalse($configuration->isAllowedAmount(50001));
    }

    public function testARangeChannelIgnoresPresetsItWasLeftWith(): void
    {
        $configuration = self::configuration(
            GiftCardAmountMode::Range,
            presets: [77777],
            minimum: 1000,
            maximum: 50000,
        );

        self::assertFalse($configuration->isAllowedAmount(77777));
    }

    public function testAPresetsAndRangeChannelAllowsBoth(): void
    {
        $configuration = self::configuration(
            GiftCardAmountMode::PresetsAndRange,
            presets: [100000],
            minimum: 1000,
            maximum: 50000,
        );

        self::assertTrue($configuration->isAllowedAmount(100000), 'a preset outside the range is still offered');
        self::assertTrue($configuration->isAllowedAmount(2500));
        self::assertFalse($configuration->isAllowedAmount(999));
        self::assertFalse($configuration->isAllowedAmount(60000));
    }

    public function testAHalfConfiguredRangeOffersNothingRatherThanEverything(): void
    {
        // "Between unspecified and unspecified" has to mean nothing. Reading it as "anything" would
        // turn a half-finished channel into an open till.
        $configuration = self::configuration(GiftCardAmountMode::Range, minimum: 1000);

        self::assertFalse($configuration->isAllowedAmount(1000));
        self::assertFalse($configuration->isAllowedAmount(5000));
    }

    public function testNoChannelEverAllowsAWorthlessCard(): void
    {
        $configuration = self::configuration(GiftCardAmountMode::Range, minimum: 0, maximum: 50000);

        self::assertFalse($configuration->isAllowedAmount(0));
        self::assertFalse($configuration->isAllowedAmount(-5000));
    }

    public function testPresetsAreStoredSortedAndDeduplicated(): void
    {
        // Everything that reads presets - the shop form, the validator, the admin - sees one list in
        // one order, so none of them has to sort it again.
        $configuration = new GiftCardConfiguration();
        $configuration->setAmountPresets([10000, 2500, 5000, 2500]);

        self::assertSame([2500, 5000, 10000], $configuration->getAmountPresets());
    }

    public function testAWorthlessPresetIsDropped(): void
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setAmountPresets([0, -100, 5000]);

        self::assertSame([5000], $configuration->getAmountPresets());
    }

    /** @param list<int> $presets */
    private static function configuration(
        GiftCardAmountMode $mode,
        array $presets = [],
        ?int $minimum = null,
        ?int $maximum = null,
    ): GiftCardConfiguration {
        $configuration = new GiftCardConfiguration();
        $configuration->setAmountMode($mode);
        $configuration->setAmountPresets($presets);
        $configuration->setMinimumAmount($minimum);
        $configuration->setMaximumAmount($maximum);

        return $configuration;
    }
}
