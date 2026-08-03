<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;

/**
 * Applied to the host application's Order together with {@see OrderTrait}.
 *
 * These are the cards being *spent on* this order. The cards *bought on* this order are reached
 * through the order's item units, not through here.
 */
interface OrderInterface extends BaseOrderInterface
{
    /** @return Collection<array-key, GiftCardInterface> */
    public function getGiftCards(): Collection;

    public function hasGiftCards(): bool;

    public function hasGiftCard(GiftCardInterface $giftCard): bool;

    public function addGiftCard(GiftCardInterface $giftCard): void;

    public function removeGiftCard(GiftCardInterface $giftCard): void;
}
