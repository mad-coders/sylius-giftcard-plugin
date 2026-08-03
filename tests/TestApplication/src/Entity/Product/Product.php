<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface as GiftCardProductInterface;
use Madcoders\SyliusGiftCardPlugin\Model\ProductTrait as GiftCardProductTrait;
use Sylius\Component\Core\Model\Product as BaseProduct;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product')]
class Product extends BaseProduct implements GiftCardProductInterface
{
    use GiftCardProductTrait;
}
