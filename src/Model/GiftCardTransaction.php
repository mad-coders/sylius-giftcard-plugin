<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Madcoders\SyliusGiftCardPlugin\Exception\InvalidGiftCardAmountException;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;

/**
 * @see GiftCardTransactionInterface
 */
class GiftCardTransaction implements GiftCardTransactionInterface
{
    protected ?int $id = null;

    protected ?GiftCardInterface $giftCard = null;

    protected ?BaseOrderInterface $order = null;

    protected GiftCardTransactionType $type = GiftCardTransactionType::Debit;

    protected int $amount = 0;

    protected int $balanceAfter = 0;

    protected ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGiftCard(): ?GiftCardInterface
    {
        return $this->giftCard;
    }

    public function setGiftCard(?GiftCardInterface $giftCard): void
    {
        $this->giftCard = $giftCard;
    }

    public function getOrder(): ?BaseOrderInterface
    {
        return $this->order;
    }

    public function setOrder(?BaseOrderInterface $order): void
    {
        $this->order = $order;
    }

    public function getType(): GiftCardTransactionType
    {
        return $this->type;
    }

    public function setType(GiftCardTransactionType $type): void
    {
        $this->type = $type;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        // The direction lives in the type, so a ledger row never carries a negative amount - that
        // would make "debit of -5" mean the opposite of what it says.
        if ($amount <= 0) {
            throw InvalidGiftCardAmountException::notPositive($amount);
        }

        $this->amount = $amount;
    }

    public function getBalanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(int $balanceAfter): void
    {
        $this->balanceAfter = $balanceAfter;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
