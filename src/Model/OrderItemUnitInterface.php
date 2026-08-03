<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\OrderItemUnitInterface as BaseOrderItemUnitInterface;

/**
 * Applied to the host application's OrderItemUnit together with {@see OrderItemUnitTrait}.
 *
 * A gift card product sold in quantity 3 produces three units and therefore three separate cards,
 * which is why the card hangs off the unit rather than the order item.
 */
interface OrderItemUnitInterface extends BaseOrderItemUnitInterface
{
    public function getGiftCard(): ?GiftCardInterface;

    public function setGiftCard(?GiftCardInterface $giftCard): void;
}
