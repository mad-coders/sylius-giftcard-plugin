<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Refuses to complete an order that carries a gift card product in a channel that issues cards from
 * the back office only.
 *
 * The add-to-cart constraint alone is not enough, for two reasons. A cart outlives the setting - a
 * customer can fill it while the channel still sells gift cards and pay days after it stops. And
 * adding is not the only way in: Sylius' cart summary changes quantities through CartType, which
 * never builds an AddToCartCommand, so a customer could take one gift card to ten without the
 * add-to-cart constraint ever running.
 *
 * This sits in the `sylius_checkout_complete` group, where Sylius puts OrderProductEligibility, so
 * the customer is stopped before they are charged rather than after. The guard in
 * OrderGiftCardOperator stays as the last line, but by then the money has moved.
 */
#[\Attribute]
final class OrderGiftCardPurchaseAllowed extends Constraint
{
    public string $message = 'madcoders_sylius_gift_card.order.gift_card_not_sold_in_channel';

    #[\Override]
    public function validatedBy(): string
    {
        return 'madcoders_sylius_gift_card_order_purchase_allowed';
    }

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
