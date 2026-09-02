<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Validator\Constraints;

use Madcoders\SyliusGiftCardPlugin\Calculator\GiftCardExpiryCalculatorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Webmozart\Assert\Assert;

final class ValidityPeriodValidator extends ConstraintValidator
{
    public function __construct(private readonly GiftCardExpiryCalculatorInterface $giftCardExpiryCalculator)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        Assert::isInstanceOf($constraint, ValidityPeriod::class);

        if (null === $value) {
            return;
        }

        Assert::string($value);

        // Blank is NotBlank's business, not this constraint's - answering "that is not a period"
        // to an empty field says the wrong thing about the wrong problem.
        if ('' === trim($value)) {
            return;
        }

        if ($this->giftCardExpiryCalculator->understands($value)) {
            return;
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
