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
     * Null when the card has never been stored, and null again when nothing can be seen about it -
     * a card detached from its manager, or one mapped to a manager the implementation cannot read.
     * The two are indistinguishable here on purpose: telling them apart would need the caller's
     * knowledge of what it is doing, not the store's.
     *
     * So null means "no previous date to compare against" and never "the card had no expiry". A
     * caller that treats it as a fact about the card, rather than as the absence of an answer, will
     * quietly punish every batch job that clears its entity manager. `GiftCardExpiryNotInThePast`
     * falls back to the card's identity, which is the honest tiebreak: no identity, no history.
     */
    public function getStoredExpiryDate(GiftCardInterface $giftCard): ?\DateTimeInterface;
}
