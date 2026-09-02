<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Refuses an expiry date that would kill a spendable balance.
 *
 * A class constraint rather than a field one, because the question it asks cannot be answered from
 * the submitted value alone: a date in the past is only a problem when the card was *not* already
 * expired before the write. Issuing a card dated last year, or editing a live card's date backwards,
 * makes a balance unspendable with nothing in the card's history to explain it. Re-saving a card
 * that expired years ago - to disable it, or to correct its message - is not that, and must keep
 * working. See docs/adr-log/0018-an-expiry-date-cannot-be-moved-into-the-past.md.
 *
 * Shortening an expiry to a date still in the future is deliberately untouched. This is about the
 * past, not about all reductions.
 */
#[\Attribute]
final class GiftCardExpiryNotInThePast extends Constraint
{
    public string $message = 'madcoders_sylius_gift_card.gift_card.expires_at.not_in_the_past';

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    #[\Override]
    public function validatedBy(): string
    {
        return 'madcoders_sylius_gift_card_expiry_not_in_the_past';
    }
}
