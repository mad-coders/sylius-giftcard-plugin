<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Validator\Constraints;

use Madcoders\SyliusGiftCardPlugin\Checker\GiftCardTenderCheckerInterface;
use Madcoders\SyliusGiftCardPlugin\Model\OrderInterface as GiftCardOrderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Webmozart\Assert\Assert;

final class GiftCardRedemptionAllowedValidator extends ConstraintValidator
{
    public function __construct(private readonly GiftCardTenderCheckerInterface $giftCardTenderChecker)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        Assert::isInstanceOf($value, OrderInterface::class);
        Assert::isInstanceOf($constraint, GiftCardRedemptionAllowed::class);

        // A host that has not applied OrderTrait has no gift cards on its orders to refuse.
        if (!$value instanceof GiftCardOrderInterface) {
            return;
        }

        // Nothing applied, nothing to say. This runs on every checkout in the shop, gift card or
        // not, so the early return matters: an over-eager violation here would stop the shop
        // selling anything at all.
        if ($value->getGiftCards()->isEmpty()) {
            return;
        }

        if ($this->giftCardTenderChecker->allowsRedemptionOn($value)) {
            return;
        }

        // One violation for the order, not one per card: the customer is told the basket is the
        // problem, and repeating that per card adds nothing.
        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
