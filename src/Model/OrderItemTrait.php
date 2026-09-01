<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Order\Model\OrderItemInterface as BaseOrderItemInterface;
use Symfony\Component\Validator\Constraints as Assert;

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

    /**
     * A `text` column, like the gift card's own message column, rather than a varchar sized to the
     * limit. The limit is a rule about what a customer may ask for, and it is enforced by the
     * constraint below and by the shop form; making the column the enforcer as well means any writer
     * that skips validation - Sylius' own API, an import, a host application's code - either crashes
     * on a strict MySQL or has its text cut mid-character on a lenient one, and the truncated
     * message is then copied onto every card the line issues.
     */
    #[ORM\Column(name: 'gift_card_message', type: 'text', nullable: true)]
    #[Assert\Length(
        max: GiftCardInterface::CUSTOM_MESSAGE_MAX_LENGTH,
        maxMessage: 'madcoders_sylius_gift_card.cart_item.message.too_long',
        groups: ['Default', 'sylius'],
    )]
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

    /**
     * Two gift cards bought for different amounts, or with different messages, are different lines.
     *
     * Sylius merges a new cart line into an existing one when {@see self::equals()} says they are the
     * same, and its answer for a core order item is "the variants match". `OrderModifier` then bumps
     * the existing line's quantity and *discards the new item entirely* - so without this override,
     * adding a $25 card saying "For Ann" and then a $100 card saying "For Bob" leaves one line of
     * two $25 cards both saying "For Ann". The customer is charged the wrong total and receives
     * cards they did not ask for.
     *
     * Only the gift card fields are added to the question. For any other product both are null on
     * both items, so this reduces to Sylius' own answer and nothing else in the shop changes. Two
     * gift cards that *are* alike still merge into a quantity of two, which is what a customer
     * buying two identical cards means.
     */
    public function equals(BaseOrderItemInterface $orderItem): bool
    {
        if (!parent::equals($orderItem)) {
            return false;
        }

        // The same object is always itself, whatever it carries.
        if ($this === $orderItem) {
            return true;
        }

        if (!$orderItem instanceof OrderItemInterface) {
            // A host that applied the interface to one order item class and not another is
            // misconfigured; refusing to merge is the answer that cannot lose a customer's choice.
            return null === $this->giftCardAmount && null === $this->giftCardMessage;
        }

        return $this->giftCardAmount === $orderItem->getGiftCardAmount() &&
            $this->giftCardMessage === $orderItem->getGiftCardMessage();
    }
}
