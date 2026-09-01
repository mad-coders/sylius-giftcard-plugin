<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * @see OrderItemInterface
 *
 * Carries Doctrine attributes because it is applied to the host application's OrderItem entity - see
 * docs/adr-log/0002-doctrine-xml-mapped-superclasses.md.
 */
trait OrderItemTrait
{
    #[ORM\Column(name: 'gift_card_amount', type: 'integer', nullable: true)]
    protected ?int $giftCardAmount = null;

    #[ORM\Column(name: 'gift_card_message', type: 'string', length: 255, nullable: true)]
    protected ?string $giftCardMessage = null;

    public function getGiftCardAmount(): ?int
    {
        return $this->giftCardAmount;
    }

    public function setGiftCardAmount(?int $giftCardAmount): void
    {
        $this->giftCardAmount = $giftCardAmount;
    }

    public function getGiftCardMessage(): ?string
    {
        return $this->giftCardMessage;
    }

    public function setGiftCardMessage(?string $giftCardMessage): void
    {
        // An empty textarea arrives as an empty string; a card with a message of "" is not a card
        // with a message, and storing null keeps every reader from having to know the difference.
        $this->giftCardMessage = null === $giftCardMessage || '' === trim($giftCardMessage)
            ? null
            : $giftCardMessage;
    }
}
