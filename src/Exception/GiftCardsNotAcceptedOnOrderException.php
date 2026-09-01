<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Exception;

/**
 * Thrown when the order itself refuses gift cards, whatever card is offered.
 *
 * Today that means one thing: everything on the order is a gift card, and the channel does not let
 * a gift card pay for a gift card. It is deliberately about the *order*, not the card - the check
 * runs before any code is looked up, so refusing this way cannot tell an anonymous caller whether
 * the code they typed exists. See docs/adr-log/0016-a-gift-card-does-not-buy-a-gift-card.md.
 */
final class GiftCardsNotAcceptedOnOrderException extends GiftCardException
{
    public function __construct()
    {
        parent::__construct(
            'Gift cards cannot be redeemed against this order: it contains nothing but gift card products, '
            . 'and this channel does not let a gift card pay for a gift card.',
        );
    }
}
