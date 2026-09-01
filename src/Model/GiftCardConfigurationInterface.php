<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;
use Sylius\Resource\Model\ToggleableInterface;

/**
 * Per-channel gift card settings: how codes are generated and how long a new card stays valid.
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
}
