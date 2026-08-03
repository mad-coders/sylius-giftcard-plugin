<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

/**
 * How a gift card came into existence.
 *
 * This is not a lifecycle state - it never changes after creation. It exists so the admin can tell
 * a card someone paid for from one the shop handed out, and so cancelling the originating order can
 * find the cards it created.
 */
enum GiftCardOrigin: string
{
    /** Created by hand in the admin panel; has no originating order. */
    case Admin = 'admin';

    /** Generated for a purchased gift card product unit. */
    case Order = 'order';
}
