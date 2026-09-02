<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Provider;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;

/**
 * The expiry date a gift card still has in the database, ignoring whatever has been set on it since.
 *
 * Exists so a validator can tell an edit from a creation, and a change from a re-save. Without it
 * "the expiry is in the past" cannot be told apart from "this card expired two years ago and
 * somebody is editing its message", and refusing the second would make every legacy card
 * uneditable - including the ones the #31 migration deliberately dated into the past.
 */
interface StoredGiftCardExpiryProviderInterface
{
    /**
     * Null when the card has never been stored, or when nothing is known about it - a card being
     * created, or one detached from its manager. Callers must read null as "no previous date to
     * compare against", never as "the card had no expiry".
     */
    public function getStoredExpiryDate(GiftCardInterface $giftCard): ?\DateTimeInterface;
}
