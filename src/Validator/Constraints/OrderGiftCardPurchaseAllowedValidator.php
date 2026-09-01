<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Validator\Constraints;

use Madcoders\SyliusGiftCardPlugin\Checker\GiftCardPurchaseCheckerInterface;
use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface as GiftCardProductInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Webmozart\Assert\Assert;

final class OrderGiftCardPurchaseAllowedValidator extends ConstraintValidator
{
    public function __construct(private readonly GiftCardPurchaseCheckerInterface $giftCardPurchaseChecker)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        Assert::isInstanceOf($value, OrderInterface::class);
        Assert::isInstanceOf($constraint, OrderGiftCardPurchaseAllowed::class);

        $channel = $value->getChannel();

        // An order with no channel cannot be completed anyway, and there is no configuration to
        // consult. Sylius' own constraints deal with that case.
        if (null === $channel || $this->giftCardPurchaseChecker->canBeBoughtIn($channel)) {
            return;
        }

        /** @var OrderItemInterface $item */
        foreach ($value->getItems() as $item) {
            $product = $item->getProduct();

            // A host that has not applied ProductTrait sells no gift cards, so there is nothing
            // here to refuse.
            if (!$product instanceof GiftCardProductInterface || !$product->isGiftCard()) {
                continue;
            }

            // One violation for the order, not one per offending item - the customer is told the
            // channel does not sell gift cards, and repeating that per line adds nothing.
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }
    }
}
