<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Madcoders\SyliusGiftCardPlugin\Exception\InvalidGiftCardAmountException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;
use Sylius\Resource\Model\ToggleableInterface;

/**
 * A gift card: a code carrying a balance that can be redeemed against orders in one channel.
 *
 * All amounts are integer minor units, as everywhere else in Sylius.
 *
 * The balance can only be moved through {@see self::debit()} and {@see self::credit()}; there is
 * deliberately no `setAmount()`, so no caller can put the card into a state the invariants forbid.
 * Every balance change must be accompanied by a {@see GiftCardTransactionInterface} recorded with
 * {@see self::addTransaction()} - see docs/adr-log/0005-two-customer-links-and-transaction-ledger.md.
 */
interface GiftCardInterface extends ResourceInterface, TimestampableInterface, ToggleableInterface
{
    /**
     * How long a custom message may be.
     *
     * A card carries a greeting, not a letter, and the value is a constant rather than a per-channel
     * setting so that the shop form, the validator and the database column can never disagree about
     * it. The column is a `text`, so raising this needs no migration.
     */
    public const int CUSTOM_MESSAGE_MAX_LENGTH = 255;

    public function getId(): ?int;

    public function getCode(): ?string;

    public function setCode(?string $code): void;

    public function getChannel(): ?ChannelInterface;

    public function setChannel(?ChannelInterface $channel): void;

    public function getCurrencyCode(): ?string;

    public function setCurrencyCode(?string $currencyCode): void;

    /** The amount the card was issued with. Null until set, and immutable once set. */
    public function getInitialAmount(): ?int;

    /**
     * @throws InvalidGiftCardAmountException if the
     *         amount is not positive, or the initial amount has already been set
     */
    public function setInitialAmount(int $initialAmount): void;

    /** The amount still available on the card. */
    public function getAmount(): int;

    /** How much of the card has been spent. */
    public function getSpentAmount(): int;

    /**
     * Takes money off the card.
     *
     * @throws InvalidGiftCardAmountException if the
     *         amount is not positive or exceeds the remaining balance
     */
    public function debit(int $amount): void;

    /**
     * Puts money on the card that it did not previously hold - a goodwill top-up.
     *
     * Capped at the card's face value, which is what stops a mistyped admin adjustment turning a
     * $50 card into a $50,000 one. Returning money the card actually spent is {@see self::refund()}
     * instead, and is deliberately not capped.
     *
     * @throws InvalidGiftCardAmountException if the
     *         amount is not positive or would take the balance above the initial amount
     */
    public function credit(int $amount): void;

    /**
     * Gives back money that was taken off this card.
     *
     * Unlike {@see self::credit()} this is not capped at the face value, because the cap describes
     * how much may be *given* to a card, not how much may be *returned* to it. A card topped up by
     * an admin after being spent would otherwise be unable to take its own refund, and cancelling
     * that order would fail outright.
     *
     * Callers must have proof the money was taken - the plugin only ever refunds what the ledger
     * records this order as having debited.
     *
     * @throws InvalidGiftCardAmountException if the amount is not positive
     */
    public function refund(int $amount): void;

    public function getExpiresAt(): ?\DateTimeInterface;

    public function setExpiresAt(?\DateTimeInterface $expiresAt): void;

    public function isExpired(?\DateTimeInterface $at = null): bool;

    /**
     * Whether the card may be applied to an order: enabled, not expired, and with something left on
     * it. Channel compatibility is a property of the pairing, not the card, and is checked by the
     * applicator.
     */
    public function isRedeemable(?\DateTimeInterface $at = null): bool;

    public function getOrigin(): GiftCardOrigin;

    public function setOrigin(GiftCardOrigin $origin): void;

    public function getCustomMessage(): ?string;

    public function setCustomMessage(?string $customMessage): void;

    /** The customer who bought the card. Null for a card an admin created by hand. */
    public function getPurchaser(): ?CustomerInterface;

    public function setPurchaser(?CustomerInterface $purchaser): void;

    /** The customer who redeems the card. Null until the card is first used. */
    public function getRedeemer(): ?CustomerInterface;

    /**
     * Records who redeems this card. Assigned once, on first redemption, and never reassigned - a
     * later redemption by somebody else does not take the card over.
     */
    public function assignRedeemer(CustomerInterface $redeemer): void;

    /** The purchased unit this card was generated for. Null for an admin-created card. */
    public function getOrderItemUnit(): ?OrderItemUnitInterface;

    public function setOrderItemUnit(?OrderItemUnitInterface $orderItemUnit): void;

    /** The order the card was bought on, derived from the unit it belongs to. */
    public function getOriginatingOrder(): ?BaseOrderInterface;

    /**
     * The orders this card has been applied to.
     *
     * @return Collection<array-key, BaseOrderInterface>
     */
    public function getAppliedOrders(): Collection;

    public function hasAppliedOrder(BaseOrderInterface $order): bool;

    public function addAppliedOrder(BaseOrderInterface $order): void;

    public function removeAppliedOrder(BaseOrderInterface $order): void;

    /**
     * The balance ledger, newest last.
     *
     * @return Collection<array-key, GiftCardTransactionInterface>
     */
    public function getTransactions(): Collection;

    public function addTransaction(GiftCardTransactionInterface $transaction): void;
}
