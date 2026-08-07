<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\AdjustmentInterface as GiftCardAdjustmentInterface;
use Sylius\Component\Core\Model\Order;

/**
 * @see OrderInterface
 *
 * @mixin Order
 *
 * Unlike the plugin's own models - which are XML mapped superclasses - this trait carries Doctrine
 * attributes, because it is applied to the *host application's* Order entity and therefore has to
 * be picked up by that application's own mapping driver. See
 * docs/adr-log/0002-doctrine-xml-mapped-superclasses.md.
 *
 * The host application's Order already has a constructor, so it must call
 * {@see self::initializeGiftCards()} from it - see docs/INSTALLATION.md for the exact snippet.
 */
trait OrderTrait
{
    /**
     * Owning side of the association; the inverse side is GiftCard::$appliedOrders.
     *
     * @var Collection<array-key, GiftCardInterface>
     */
    #[ORM\ManyToMany(targetEntity: GiftCardInterface::class, inversedBy: 'appliedOrders')]
    #[ORM\JoinTable(name: 'madcoders_gift_card__order_gift_cards')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'gift_card_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    protected Collection $giftCards;

    /** @return Collection<array-key, GiftCardInterface> */
    public function getGiftCards(): Collection
    {
        return $this->giftCards;
    }

    public function hasGiftCards(): bool
    {
        return !$this->giftCards->isEmpty();
    }

    public function hasGiftCard(GiftCardInterface $giftCard): bool
    {
        return $this->giftCards->contains($giftCard);
    }

    public function addGiftCard(GiftCardInterface $giftCard): void
    {
        if ($this->hasGiftCard($giftCard)) {
            return;
        }

        $this->giftCards->add($giftCard);
        $giftCard->addAppliedOrder($this);
    }

    public function removeGiftCard(GiftCardInterface $giftCard): void
    {
        if (!$this->hasGiftCard($giftCard)) {
            return;
        }

        $this->giftCards->removeElement($giftCard);
        $giftCard->removeAppliedOrder($this);
    }

    public function getGiftCardTotal(): int
    {
        $total = 0;

        foreach ($this->getAdjustments(GiftCardAdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT) as $adjustment) {
            $total += abs($adjustment->getAmount());
        }

        return $total;
    }

    public function getAmountToPay(): int
    {
        return max(0, $this->getTotal() - $this->getGiftCardTotal());
    }

    protected function initializeGiftCards(): void
    {
        $this->giftCards = new ArrayCollection();
    }
}
