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

    /**
     * How much of this order the applied gift cards cover, as a positive amount in minor units.
     *
     * Not derivable from getAdjustmentsTotal(): the gift card adjustments are neutral - they record
     * coverage without moving the order total - and Sylius excludes neutral adjustments from that
     * sum by design.
     */
    public function getGiftCardTotal(): int;

    /**
     * What the customer actually has to pay: the order total less what the gift cards cover.
     *
     * The order total is untouched by gift cards, because a card is money rather than a discount.
     */
    public function getAmountToPay(): int;
}
