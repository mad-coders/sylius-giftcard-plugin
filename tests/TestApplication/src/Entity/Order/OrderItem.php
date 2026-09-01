<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\OrderItemInterface as GiftCardOrderItemInterface;
use Madcoders\SyliusGiftCardPlugin\Model\OrderItemTrait as GiftCardOrderItemTrait;
use Sylius\Component\Core\Model\OrderItem as BaseOrderItem;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order_item')]
class OrderItem extends BaseOrderItem implements GiftCardOrderItemInterface
{
    use GiftCardOrderItemTrait;
}
