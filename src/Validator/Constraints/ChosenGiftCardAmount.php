<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * The amount a customer chose for a gift card must be one the channel offers.
 *
 * Applied to the amount field of the shop's add-to-cart form. It is the friendly half of the
 * refusal; the binding half is
 * {@see \Madcoders\SyliusGiftCardPlugin\OrderProcessor\GiftCardChosenAmountProcessor}, which
 * re-checks the same rule on every order recalculation - because a form is not a security boundary.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ChosenGiftCardAmount extends Constraint
{
    /** Nothing was chosen at all, in a channel where something must be. */
    public string $missingMessage = 'madcoders_sylius_gift_card.cart_item.amount.required';

    /** The amount is not one the channel offers, and the channel offers a fixed list of them. */
    public string $notAPresetMessage = 'madcoders_sylius_gift_card.cart_item.amount.not_a_preset';

    /**
     * A free amount is allowed but this one is outside the bounds. Names both bounds, because
     * "invalid amount" leaves the customer guessing what to type instead.
     */
    public string $outOfRangeMessage = 'madcoders_sylius_gift_card.cart_item.amount.out_of_range';
}
