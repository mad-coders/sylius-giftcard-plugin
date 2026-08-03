<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * @see ProductInterface
 *
 * Carries Doctrine attributes because it is applied to the host application's Product entity - see
 * docs/adr-log/0002-doctrine-xml-mapped-superclasses.md.
 */
trait ProductTrait
{
    #[ORM\Column(name: 'gift_card', type: 'boolean', options: ['default' => false])]
    protected bool $giftCard = false;

    public function isGiftCard(): bool
    {
        return $this->giftCard;
    }

    public function setGiftCard(bool $giftCard): void
    {
        $this->giftCard = $giftCard;
    }
}
