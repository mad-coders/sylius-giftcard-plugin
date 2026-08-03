<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\OrderInterface as GiftCardOrderInterface;
use Madcoders\SyliusGiftCardPlugin\Model\OrderTrait as GiftCardOrderTrait;
use Sylius\Component\Core\Model\Order as BaseOrder;

/**
 * Shows what a host application has to do to its own Order: apply the interface and the trait, and
 * initialise the gift card collection from the constructor.
 */
#[ORM\Entity]
#[ORM\Table(name: 'sylius_order')]
class Order extends BaseOrder implements GiftCardOrderInterface
{
    use GiftCardOrderTrait;

    public function __construct()
    {
        parent::__construct();

        $this->initializeGiftCards();
    }
}
