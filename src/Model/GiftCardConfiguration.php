<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Model\TimestampableTrait;
use Sylius\Resource\Model\ToggleableTrait;

/**
 * @see GiftCardConfigurationInterface
 */
class GiftCardConfiguration implements GiftCardConfigurationInterface
{
    use TimestampableTrait;
    use ToggleableTrait;

    public const DEFAULT_CODE_LENGTH = 16;

    /**
     * Below this the code space is small enough to guess. At 12 characters of the generator's
     * 30-character alphabet that is ~59 bits; at 6 it is ~29 bits, which is minutes of work.
     */
    public const MINIMUM_CODE_LENGTH = 12;

    protected ?int $id = null;

    protected ?ChannelInterface $channel = null;

    protected int $codeLength = self::DEFAULT_CODE_LENGTH;

    protected ?string $codePrefix = null;

    protected ?string $validityPeriod = '1 year';

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public function getCodeLength(): int
    {
        return $this->codeLength;
    }

    public function setCodeLength(int $codeLength): void
    {
        // Clamped rather than silently accepted: a gift card code is bearer money, and a
        // misconfigured length is not obvious until the codes are already guessable.
        $this->codeLength = max(self::MINIMUM_CODE_LENGTH, $codeLength);
    }

    public function getCodePrefix(): ?string
    {
        return $this->codePrefix;
    }

    public function setCodePrefix(?string $codePrefix): void
    {
        $this->codePrefix = $codePrefix;
    }

    public function getValidityPeriod(): ?string
    {
        return $this->validityPeriod;
    }

    public function setValidityPeriod(?string $validityPeriod): void
    {
        $this->validityPeriod = $validityPeriod;
    }

    public function calculateExpiryDate(?\DateTimeImmutable $from = null): ?\DateTimeImmutable
    {
        if (null === $this->validityPeriod || '' === trim($this->validityPeriod)) {
            return null;
        }

        $from ??= new \DateTimeImmutable();

        try {
            $expiresAt = $from->add(\DateInterval::createFromDateString($this->validityPeriod));
        } catch (\Throwable) {
            // An unparseable validity period must not hand out an already-expired card. PHP raises
            // this differently across versions - a DateMalformedIntervalStringException on 8.4, a
            // false return (and so a TypeError here) on 8.3 - so catch the failure, not the class.
            return null;
        }

        // A period that parses but moves nothing ("0 days") would expire the card on creation.
        return $expiresAt > $from ? $expiresAt : null;
    }
}
