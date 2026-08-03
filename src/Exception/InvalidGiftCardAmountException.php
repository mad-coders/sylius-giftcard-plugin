<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Exception;

/**
 * Thrown when a balance change would break one of the gift card's amount invariants: a
 * non-positive amount, a debit larger than the remaining balance, or a credit taking the balance
 * above the initial amount.
 */
final class InvalidGiftCardAmountException extends GiftCardException
{
    public static function notPositive(int $amount): self
    {
        return new self(sprintf('A gift card balance change must be a positive amount, got %d.', $amount));
    }

    public static function exceedsBalance(int $amount, int $balance): self
    {
        return new self(sprintf('Cannot debit %d from a gift card with a balance of %d.', $amount, $balance));
    }

    public static function exceedsInitialAmount(int $amount, int $balance, int $initialAmount): self
    {
        return new self(sprintf(
            'Cannot credit %d to a gift card with a balance of %d: it would exceed its initial amount of %d.',
            $amount,
            $balance,
            $initialAmount,
        ));
    }

    public static function initialAmountNotPositive(int $initialAmount): self
    {
        return new self(sprintf('A gift card initial amount must be positive, got %d.', $initialAmount));
    }

    public static function initialAmountAlreadySet(int $initialAmount): self
    {
        return new self(sprintf(
            'The initial amount of a gift card cannot be changed; it is already set to %d.',
            $initialAmount,
        ));
    }

    public static function initialAmountNotSet(): self
    {
        return new self('The gift card has no initial amount yet; set it before changing the balance.');
    }
}
