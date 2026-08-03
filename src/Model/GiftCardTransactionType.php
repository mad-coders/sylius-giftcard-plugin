<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

/**
 * The direction of a balance change, from the gift card's point of view.
 */
enum GiftCardTransactionType: string
{
    /** Money taken off the card - the card was redeemed against an order. */
    case Debit = 'debit';

    /** Money put back on the card - a redeeming order was cancelled, or an admin topped it up. */
    case Credit = 'credit';
}
