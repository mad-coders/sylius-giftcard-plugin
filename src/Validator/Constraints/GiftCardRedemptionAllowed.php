<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Refuses to complete an order that still has gift cards applied to it after the basket stopped
 * having anything a gift card may pay for.
 *
 * The applicator refuses the same thing at the moment a card is offered, and that is not enough on
 * its own for the reason ADR 0013 gives about the sale mode: a cart outlives the setting. A customer
 * can apply a card to a basket of shoes and a gift card, then remove the shoes - or an operator can
 * change the channel's tender mode between the two. Either way the card is already on the order and
 * nothing would ask again.
 *
 * The order processor caps the coverage regardless, so the shop cannot lose money here. What this
 * constraint adds is the customer being told, rather than reaching the pay button and quietly
 * finding their card covered nothing. It sits in `sylius_checkout_complete`, alongside Sylius'
 * OrderProductEligibility, so that happens before they are charged.
 */
#[\Attribute]
final class GiftCardRedemptionAllowed extends Constraint
{
    public string $message = 'madcoders_sylius_gift_card.order.gift_card_cannot_pay_for_gift_card';

    #[\Override]
    public function validatedBy(): string
    {
        return 'madcoders_sylius_gift_card_redemption_allowed';
    }

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
