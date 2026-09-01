<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Refuses an add-to-cart of a gift card product in a channel that issues cards from the back office
 * only.
 *
 * A class constraint on Sylius' AddToCartCommand, alongside its own availability checks, so the
 * refusal happens where the customer is - as a form error on the add-to-cart form - rather than as
 * a surprise later in checkout.
 */
#[\Attribute]
final class GiftCardPurchaseAllowed extends Constraint
{
    public string $message = 'madcoders_sylius_gift_card.cart_item.gift_card_not_sold_in_channel';

    #[\Override]
    public function validatedBy(): string
    {
        return 'madcoders_sylius_gift_card_purchase_allowed';
    }

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
