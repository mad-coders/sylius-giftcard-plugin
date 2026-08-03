<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Sylius\Resource\Model\ResourceInterface;

/**
 * One entry in a gift card's balance ledger.
 *
 * The ledger is append-only: a transaction is written when the balance changes and is never updated
 * or deleted afterwards. It explains the balance; it is not the source of truth for it - that is
 * {@see GiftCardInterface::getAmount()}. See
 * docs/adr-log/0005-two-customer-links-and-transaction-ledger.md.
 */
interface GiftCardTransactionInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getGiftCard(): ?GiftCardInterface;

    public function setGiftCard(?GiftCardInterface $giftCard): void;

    /** The order that caused this change. Null for a manual admin adjustment. */
    public function getOrder(): ?BaseOrderInterface;

    public function setOrder(?BaseOrderInterface $order): void;

    public function getType(): GiftCardTransactionType;

    public function setType(GiftCardTransactionType $type): void;

    /** Always positive; the direction is carried by the type. */
    public function getAmount(): int;

    public function setAmount(int $amount): void;

    /** The card's balance immediately after this transaction. */
    public function getBalanceAfter(): int;

    public function setBalanceAfter(int $balanceAfter): void;

    public function getCreatedAt(): ?\DateTimeInterface;

    public function setCreatedAt(?\DateTimeInterface $createdAt): void;
}
