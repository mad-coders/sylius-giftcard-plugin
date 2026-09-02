<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Refuses a channel validity period that cannot produce a future expiry date.
 *
 * A period is a free-text relative date expression, so "1 yaer" and "0 days" both look plausible in
 * a form field and neither can expire a card. Before this, either one silently issued cards that
 * never expired; now the calculator falls back to the plugin's default and this stops the operator
 * walking away believing they configured something they did not. See
 * docs/adr-log/0015-every-gift-card-expires.md.
 */
#[\Attribute]
final class ValidityPeriod extends Constraint
{
    public string $message = 'madcoders_sylius_gift_card.gift_card_configuration.validity_period.unparseable';

    #[\Override]
    public function validatedBy(): string
    {
        return 'madcoders_sylius_gift_card_validity_period';
    }
}
