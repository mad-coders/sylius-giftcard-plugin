<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Madcoders\SyliusGiftCardPlugin\Exception\InvalidGiftCardAmountException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Sylius\Resource\Model\TimestampableTrait;
use Sylius\Resource\Model\ToggleableTrait;

/**
 * @see GiftCardInterface for the contract and the invariants this class enforces
 */
class GiftCard implements GiftCardInterface, \Stringable
{
    use TimestampableTrait;
    use ToggleableTrait;

    protected ?int $id = null;

    protected ?string $code = null;

    protected ?ChannelInterface $channel = null;

    protected ?string $currencyCode = null;

    protected ?int $initialAmount = null;

    protected int $amount = 0;

    protected ?\DateTimeInterface $expiresAt = null;

    protected GiftCardOrigin $origin = GiftCardOrigin::Admin;

    protected ?string $customMessage = null;

    protected ?CustomerInterface $purchaser = null;

    protected ?CustomerInterface $redeemer = null;

    protected ?OrderItemUnitInterface $orderItemUnit = null;

    /** @var Collection<array-key, BaseOrderInterface> */
    protected Collection $appliedOrders;

    /** @var Collection<array-key, GiftCardTransactionInterface> */
    protected Collection $transactions;

    public function __construct()
    {
        $this->appliedOrders = new ArrayCollection();
        $this->transactions = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function __toString(): string
    {
        // Deliberately the code: this is what admin grids, choice lists and Doctrine error messages
        // render, and a masked value there would be useless. Exception messages mask it instead -
        // see GiftCardException::maskCode().
        return (string) $this->code;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(?string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    public function getInitialAmount(): ?int
    {
        return $this->initialAmount;
    }

    public function setInitialAmount(int $initialAmount): void
    {
        if (null !== $this->initialAmount) {
            throw InvalidGiftCardAmountException::initialAmountAlreadySet($this->initialAmount);
        }

        if ($initialAmount <= 0) {
            throw InvalidGiftCardAmountException::initialAmountNotPositive($initialAmount);
        }

        $this->initialAmount = $initialAmount;

        // A card is issued with its full value available; the balance only diverges once it is
        // redeemed.
        $this->amount = $initialAmount;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getSpentAmount(): int
    {
        return ($this->initialAmount ?? 0) - $this->amount;
    }

    public function debit(int $amount): void
    {
        $this->assertPositive($amount);

        if ($amount > $this->amount) {
            throw InvalidGiftCardAmountException::exceedsBalance($amount, $this->amount);
        }

        $this->amount -= $amount;
    }

    public function credit(int $amount): void
    {
        $this->assertPositive($amount);

        if (null === $this->initialAmount) {
            throw InvalidGiftCardAmountException::initialAmountNotSet();
        }

        if ($this->amount + $amount > $this->initialAmount) {
            throw InvalidGiftCardAmountException::exceedsInitialAmount($amount, $this->amount, $this->initialAmount);
        }

        $this->amount += $amount;
    }

    public function refund(int $amount): void
    {
        // No face-value cap here, and that is the whole point of the method - see the interface.
        $this->assertPositive($amount);

        $this->amount += $amount;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function isExpired(?\DateTimeInterface $at = null): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        return $this->expiresAt < ($at ?? new \DateTime());
    }

    public function isRedeemable(?\DateTimeInterface $at = null): bool
    {
        return $this->enabled && $this->amount > 0 && !$this->isExpired($at);
    }

    public function getOrigin(): GiftCardOrigin
    {
        return $this->origin;
    }

    public function setOrigin(GiftCardOrigin $origin): void
    {
        $this->origin = $origin;
    }

    public function getCustomMessage(): ?string
    {
        return $this->customMessage;
    }

    public function setCustomMessage(?string $customMessage): void
    {
        $this->customMessage = $customMessage;
    }

    public function getPurchaser(): ?CustomerInterface
    {
        return $this->purchaser;
    }

    public function setPurchaser(?CustomerInterface $purchaser): void
    {
        $this->purchaser = $purchaser;
    }

    public function getRedeemer(): ?CustomerInterface
    {
        return $this->redeemer;
    }

    public function assignRedeemer(CustomerInterface $redeemer): void
    {
        // Assigned once, on first redemption. A card handed on to somebody else stays associated
        // with the person who started spending it - see ADR 0005.
        if (null !== $this->redeemer) {
            return;
        }

        $this->redeemer = $redeemer;
    }

    public function getOrderItemUnit(): ?OrderItemUnitInterface
    {
        return $this->orderItemUnit;
    }

    public function setOrderItemUnit(?OrderItemUnitInterface $orderItemUnit): void
    {
        if ($this->orderItemUnit === $orderItemUnit) {
            return;
        }

        $this->orderItemUnit = $orderItemUnit;

        $orderItemUnit?->setGiftCard($this);
    }

    public function getOriginatingOrder(): ?BaseOrderInterface
    {
        $order = $this->orderItemUnit?->getOrderItem()?->getOrder();

        return $order instanceof BaseOrderInterface ? $order : null;
    }

    public function getAppliedOrders(): Collection
    {
        return $this->appliedOrders;
    }

    public function hasAppliedOrder(BaseOrderInterface $order): bool
    {
        return $this->appliedOrders->contains($order);
    }

    public function addAppliedOrder(BaseOrderInterface $order): void
    {
        if ($this->hasAppliedOrder($order)) {
            return;
        }

        $this->appliedOrders->add($order);
    }

    public function removeAppliedOrder(BaseOrderInterface $order): void
    {
        $this->appliedOrders->removeElement($order);
    }

    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(GiftCardTransactionInterface $transaction): void
    {
        if ($this->transactions->contains($transaction)) {
            return;
        }

        $this->transactions->add($transaction);
        $transaction->setGiftCard($this);
    }

    private function assertPositive(int $amount): void
    {
        if ($amount <= 0) {
            throw InvalidGiftCardAmountException::notPositive($amount);
        }
    }
}
