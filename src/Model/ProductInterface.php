<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\ProductInterface as BaseProductInterface;

/**
 * Applied to the host application's Product together with {@see ProductTrait}.
 *
 * Marking a product as a gift card is what makes buying it generate a card: the plugin looks for
 * this flag when an order is paid.
 */
interface ProductInterface extends BaseProductInterface
{
    public function isGiftCard(): bool;

    public function setGiftCard(bool $giftCard): void;
}
