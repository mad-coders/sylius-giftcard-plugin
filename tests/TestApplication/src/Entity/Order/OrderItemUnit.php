<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\OrderItemUnitInterface as GiftCardOrderItemUnitInterface;
use Madcoders\SyliusGiftCardPlugin\Model\OrderItemUnitTrait as GiftCardOrderItemUnitTrait;
use Sylius\Component\Core\Model\OrderItemUnit as BaseOrderItemUnit;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order_item_unit')]
class OrderItemUnit extends BaseOrderItemUnit implements GiftCardOrderItemUnitInterface
{
    use GiftCardOrderItemUnitTrait;
}
