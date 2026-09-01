<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

/**
 * Whether a channel lets customers buy gift cards, or only lets an administrator issue them.
 *
 * An enum rather than a boolean because "who may obtain a card" is a policy with more than two
 * plausible answers - "sellable to logged-in customers only" is the obvious next one - and a
 * boolean would need a second migration and a second column to say it.
 *
 * This gates *selling* only. Redeeming a card is never gated: a card an administrator handed out
 * as compensation is worthless if the shop it was issued for refuses to take it.
 */
enum GiftCardSaleMode: string
{
    /** Customers may buy gift card products, and paying for one issues a card. */
    case Sellable = 'sellable';

    /** Only an administrator issues cards; the shop refuses to sell them. */
    case AdminOnly = 'admin_only';
}
