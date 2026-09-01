<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Calculator;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;

/**
 * The single source of a gift card's expiry date.
 *
 * Every card expires. This is the only thing in the plugin that decides when, and it cannot say
 * "never": the return type is not nullable, so no caller has a null branch to get wrong. See
 * docs/adr-log/0015-every-gift-card-expires.md.
 */
interface GiftCardExpiryCalculatorInterface
{
    /**
     * The expiry date a card issued now - or at $from - gets under this channel configuration.
     *
     * A null configuration, a blank validity period, one that cannot be parsed, and one that does
     * not move the date forward all fall back to the plugin's default period. None of them yields a
     * card without an expiry date, and none yields one that is already expired.
     */
    public function calculate(?GiftCardConfigurationInterface $configuration, ?\DateTimeImmutable $from = null): \DateTimeImmutable;

    /**
     * Whether this validity period can be honoured as written - that is, whether it parses and
     * moves a date forward.
     *
     * Here rather than in the form so there is exactly one parser: a second one would eventually
     * disagree, and the disagreement would look like a channel that saves cleanly and then quietly
     * issues cards on the default period. False for null and blank too, which is what makes
     * {@see self::calculate()} fall back.
     */
    public function understands(?string $validityPeriod): bool;
}
