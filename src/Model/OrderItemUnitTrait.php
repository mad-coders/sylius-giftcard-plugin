<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * @see OrderItemUnitInterface
 *
 * Carries Doctrine attributes because it is applied to the host application's OrderItemUnit entity
 * - see docs/adr-log/0002-doctrine-xml-mapped-superclasses.md.
 */
trait OrderItemUnitTrait
{
    /** Inverse side; the owning side (and the join column) is GiftCard::$orderItemUnit. */
    #[ORM\OneToOne(targetEntity: GiftCardInterface::class, mappedBy: 'orderItemUnit')]
    protected ?GiftCardInterface $giftCard = null;

    public function getGiftCard(): ?GiftCardInterface
    {
        return $this->giftCard;
    }

    public function setGiftCard(?GiftCardInterface $giftCard): void
    {
        $this->giftCard = $giftCard;
    }
}
