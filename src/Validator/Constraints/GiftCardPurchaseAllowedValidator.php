<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Validator\Constraints;

use Madcoders\SyliusGiftCardPlugin\Checker\GiftCardPurchaseCheckerInterface;
use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface as GiftCardProductInterface;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Webmozart\Assert\Assert;

final class GiftCardPurchaseAllowedValidator extends ConstraintValidator
{
    public function __construct(private readonly GiftCardPurchaseCheckerInterface $giftCardPurchaseChecker)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        Assert::isInstanceOf($value, AddToCartCommandInterface::class);
        Assert::isInstanceOf($constraint, GiftCardPurchaseAllowed::class);

        $cartItem = $value->getCartItem();
        if (!$cartItem instanceof OrderItemInterface) {
            return;
        }

        $product = $cartItem->getProduct();

        // A host application that has not applied ProductTrait simply sells no gift cards, so there
        // is nothing here to refuse.
        if (!$product instanceof GiftCardProductInterface || !$product->isGiftCard()) {
            return;
        }

        $cart = $value->getCart();
        $channel = $cart instanceof OrderInterface ? $cart->getChannel() : null;

        // No channel means the cart has not resolved one yet; there is no configuration to consult
        // and nothing to decide against.
        if (null === $channel || $this->giftCardPurchaseChecker->canBeBoughtIn($channel)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath('cartItem')
            ->addViolation()
        ;
    }
}
