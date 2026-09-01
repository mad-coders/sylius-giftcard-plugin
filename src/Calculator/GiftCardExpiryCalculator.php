<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Calculator;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;

/**
 * @see GiftCardExpiryCalculatorInterface
 */
final readonly class GiftCardExpiryCalculator implements GiftCardExpiryCalculatorInterface
{
    /**
     * The period a card gets when its channel has no usable one of its own.
     *
     * The human form, as an operator would type it into the configuration form and as the
     * documentation quotes it. {@see self::DEFAULT_INTERVAL_SPEC} is the same period in the form
     * PHP cannot fail to parse, and a unit test asserts the two agree - a fallback that could
     * itself fail to parse would put back the null this class exists to eliminate.
     */
    public const string DEFAULT_VALIDITY_PERIOD = '1 year';

    private const string DEFAULT_INTERVAL_SPEC = 'P1Y';

    public function calculate(?GiftCardConfigurationInterface $configuration, ?\DateTimeImmutable $from = null): \DateTimeImmutable
    {
        $from ??= new \DateTimeImmutable();

        return $this->apply($configuration?->getValidityPeriod(), $from)
            ?? $from->add(new \DateInterval(self::DEFAULT_INTERVAL_SPEC));
    }

    public function understands(?string $validityPeriod): bool
    {
        return null !== $this->apply($validityPeriod, new \DateTimeImmutable());
    }

    /**
     * The period applied to $from, or null when the channel has not given us one we can honour -
     * which is the caller's cue to fall back rather than to issue a card without an expiry.
     */
    private function apply(?string $validityPeriod, \DateTimeImmutable $from): ?\DateTimeImmutable
    {
        if (null === $validityPeriod || '' === trim($validityPeriod)) {
            return null;
        }

        try {
            $expiresAt = $from->add(\DateInterval::createFromDateString($validityPeriod));
        } catch (\Throwable) {
            // PHP reports an unparseable period differently across versions - a
            // DateMalformedIntervalStringException on 8.4, a false return (and so a TypeError
            // here) on 8.3 - so the failure is caught, not the class.
            //
            // The configuration form refuses such a period outright, so reaching this means a row
            // that predates the rule or was written around the form. Falling back is the only safe
            // answer: issuing a card that is already expired hands the customer something dead, and
            // issuing one with no expiry is exactly what this class exists to prevent.
            return null;
        }

        // A period that parses but moves nothing ("0 days") or backwards ("-1 year") would expire
        // the card on the day it is issued.
        return $expiresAt > $from ? $expiresAt : null;
    }
}
