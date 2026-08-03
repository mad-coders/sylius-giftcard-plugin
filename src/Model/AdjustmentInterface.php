<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\AdjustmentInterface as BaseAdjustmentInterface;

interface AdjustmentInterface extends BaseAdjustmentInterface
{
    /**
     * The adjustment type a redeemed gift card contributes to an order.
     *
     * Adjustments of this type are always negative, carry the gift card's code as their origin
     * code, and are owned by the plugin's order processor - see
     * docs/adr-log/0004-gift-card-redemption-as-order-adjustment.md.
     */
    public const ORDER_GIFT_CARD_ADJUSTMENT = 'madcoders_gift_card';
}
