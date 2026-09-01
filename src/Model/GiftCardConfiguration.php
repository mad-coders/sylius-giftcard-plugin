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

    /**
     * Sellable by default, so a shop upgrading into this feature keeps selling gift cards exactly
     * as it did before.
     */
    protected GiftCardSaleMode $saleMode = GiftCardSaleMode::Sellable;

    protected GiftCardAmountMode $amountMode = GiftCardAmountMode::Fixed;

    /**
     * Nullable only because the column is - MySQL refuses a default on a JSON column, so a channel
     * that predates this setting has null there. Read it through {@see self::getAmountPresets()},
     * which resolves that to an empty list.
     *
     * @var list<int>|null
     */
    protected ?array $amountPresets = [];

    protected ?int $minimumAmount = null;

    protected ?int $maximumAmount = null;

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

    public function getSaleMode(): GiftCardSaleMode
    {
        return $this->saleMode;
    }

    public function setSaleMode(GiftCardSaleMode $saleMode): void
    {
        $this->saleMode = $saleMode;
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

    public function getAmountMode(): GiftCardAmountMode
    {
        return $this->amountMode;
    }

    public function setAmountMode(GiftCardAmountMode $amountMode): void
    {
        $this->amountMode = $amountMode;
    }

    public function getAmountPresets(): array
    {
        return $this->amountPresets ?? [];
    }

    public function setAmountPresets(array $amountPresets): void
    {
        // Normalised on the way in rather than on the way out, so everything reading presets - the
        // shop form, the validator, the admin - sees the same list in the same order. A zero or
        // negative preset is dropped because a card has to be worth something.
        $presets = array_values(array_unique(array_filter(
            $amountPresets,
            static fn (int $preset): bool => $preset > 0,
        )));

        sort($presets);

        $this->amountPresets = $presets;
    }

    public function getMinimumAmount(): ?int
    {
        return $this->minimumAmount;
    }

    public function setMinimumAmount(?int $minimumAmount): void
    {
        $this->minimumAmount = $minimumAmount;
    }

    public function getMaximumAmount(): ?int
    {
        return $this->maximumAmount;
    }

    public function setMaximumAmount(?int $maximumAmount): void
    {
        $this->maximumAmount = $maximumAmount;
    }

    public function allowsCustomerChosenAmount(): bool
    {
        return $this->amountMode->allowsCustomerChosenAmount();
    }

    public function isAllowedAmount(int $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        if ($this->amountMode->offersPresets() && in_array($amount, $this->getAmountPresets(), true)) {
            return true;
        }

        return $this->amountMode->offersFreeAmount() && $this->isWithinBounds($amount);
    }

    private function isWithinBounds(int $amount): bool
    {
        // A range mode with a bound missing is a misconfigured channel, and the safe reading of
        // "between unspecified and unspecified" is "nothing", not "anything" - the same instinct as
        // clamping the code length. The admin form refuses to leave a bound empty in these modes, so
        // this only catches a channel configured some other way.
        if (null === $this->minimumAmount || null === $this->maximumAmount) {
            return false;
        }

        return $amount >= $this->minimumAmount && $amount <= $this->maximumAmount;
    }
}
