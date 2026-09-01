<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;
use Sylius\Resource\Model\ToggleableInterface;

/**
 * Per-channel gift card settings: how codes are generated, how long a new card stays valid, and how
 * a customer buying one chooses what it is worth.
 *
 * Deliberately holds no presentation settings - 1.0 ships no PDF or image rendering, see
 * docs/adr-log/0009-no-pdf-in-1-0.md.
 */
interface GiftCardConfigurationInterface extends ResourceInterface, TimestampableInterface, ToggleableInterface
{
    public function getId(): ?int;

    public function getChannel(): ?ChannelInterface;

    public function setChannel(?ChannelInterface $channel): void;

    /** Number of random characters in a generated code, excluding the prefix. */
    public function getCodeLength(): int;

    public function setCodeLength(int $codeLength): void;

    /** Optional fixed prefix prepended to every generated code, e.g. "GIFT-". */
    public function getCodePrefix(): ?string;

    public function setCodePrefix(?string $codePrefix): void;

    /**
     * How long a newly created card stays valid, as a relative date expression understood by
     * {@see \DateInterval::createFromDateString()}, e.g. "1 year" or "6 months". Null means the
     * cards never expire.
     */
    public function getValidityPeriod(): ?string;

    public function setValidityPeriod(?string $validityPeriod): void;

    /**
     * Whether the shop may sell gift cards in this channel, or only an administrator may issue
     * them. Redeeming is never gated by this - see {@see GiftCardSaleMode}.
     */
    public function getSaleMode(): GiftCardSaleMode;

    public function setSaleMode(GiftCardSaleMode $saleMode): void;

    /**
     * The expiry date a card created now would get, or null when cards in this channel do not
     * expire.
     */
    public function calculateExpiryDate(?\DateTimeImmutable $from = null): ?\DateTimeImmutable;

    /** How a customer buying a gift card in this channel chooses what it is worth. */
    public function getAmountMode(): GiftCardAmountMode;

    public function setAmountMode(GiftCardAmountMode $amountMode): void;

    /**
     * The amounts this channel offers as ready-made choices, in the channel's currency and in
     * integer minor units, ascending and without duplicates.
     *
     * @return list<int>
     */
    public function getAmountPresets(): array;

    /** @param list<int> $amountPresets minor units; sorted, deduplicated and stripped of non-positive values */
    public function setAmountPresets(array $amountPresets): void;

    /** The smallest amount a customer may type in, in minor units. Null means no free amount is offered. */
    public function getMinimumAmount(): ?int;

    public function setMinimumAmount(?int $minimumAmount): void;

    /** The largest amount a customer may type in, in minor units. Null means no free amount is offered. */
    public function getMaximumAmount(): ?int;

    public function setMaximumAmount(?int $maximumAmount): void;

    /**
     * Whether a customer buying a gift card in this channel picks the amount, rather than paying the
     * product's channel price.
     */
    public function allowsCustomerChosenAmount(): bool;

    /**
     * Whether a customer buying a gift card in this channel may be charged this amount.
     *
     * The single decision point behind both the shop form and the order processor, so a forged
     * amount is judged by exactly the same rule as a typed one - see
     * docs/adr-log/0014-customer-chosen-gift-card-amount.md.
     *
     * @param int $amount minor units
     */
    public function isAllowedAmount(int $amount): bool;
}
