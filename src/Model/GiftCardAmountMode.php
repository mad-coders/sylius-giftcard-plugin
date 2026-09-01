<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

/**
 * How a channel decides what a gift card bought in the shop is worth.
 *
 * Stored as its string value, so a new case can be added without renumbering anything already in
 * the database.
 */
enum GiftCardAmountMode: string
{
    /** The product's channel price, exactly as before this setting existed. The customer chooses nothing. */
    case Fixed = 'fixed';

    /** One of the amounts the channel offers, and nothing else. */
    case Presets = 'presets';

    /** Any amount between the channel's minimum and maximum. */
    case Range = 'range';

    /** The channel's presets, or any amount between the minimum and maximum. */
    case PresetsAndRange = 'presets_and_range';

    /** Whether the customer picks the amount at all. False only for {@see self::Fixed}. */
    public function allowsCustomerChosenAmount(): bool
    {
        return self::Fixed !== $this;
    }

    public function offersPresets(): bool
    {
        return self::Presets === $this || self::PresetsAndRange === $this;
    }

    public function offersFreeAmount(): bool
    {
        return self::Range === $this || self::PresetsAndRange === $this;
    }
}
