<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Madcoders\SyliusGiftCardPlugin\Calculator\GiftCardExpiryCalculator;
use Madcoders\SyliusGiftCardPlugin\Calculator\GiftCardExpiryCalculatorInterface;
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

    /**
     * The plugin's default period, spelled out here so a channel created in code and never opened
     * in the admin still expires its cards after a year. Nullable only because the column is: rows
     * that predate the rule can hold null, and {@see GiftCardExpiryCalculatorInterface} resolves
     * that to the same default rather than to "never".
     */
    protected ?string $validityPeriod = GiftCardExpiryCalculator::DEFAULT_VALIDITY_PERIOD;

    /**
     * Sellable by default, so a shop upgrading into this feature keeps selling gift cards exactly
     * as it did before.
     */
    protected GiftCardSaleMode $saleMode = GiftCardSaleMode::Sellable;

    protected GiftCardAmountMode $amountMode = GiftCardAmountMode::Fixed;

    /**
     * Goods only by default, in the model and as the column default. Unlike the sale mode, this
     * default deliberately changes what an upgrading shop does - the previous behaviour let a card
     * buy a card and renew its own expiry forever, which is a hole rather than a feature. See
     * docs/adr-log/0016-a-gift-card-does-not-buy-a-gift-card.md.
     */
    protected GiftCardTenderMode $tenderMode = GiftCardTenderMode::GoodsOnly;

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

    public function getTenderMode(): GiftCardTenderMode
    {
        return $this->tenderMode;
    }

    public function setTenderMode(GiftCardTenderMode $tenderMode): void
    {
        $this->tenderMode = $tenderMode;
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
        // shop form, the validator, the admin - sees the same list in the same order. Sorting and
        // de-duplicating do not change what the channel offers; offering 50 twice is offering 50.
        //
        // Dropping a worthless preset *is* a correction, and it is a backstop only: the admin form
        // refuses one outright, exactly as it does a code length below the minimum. Nothing should
        // reach here having silently lost a preset the operator typed.
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
