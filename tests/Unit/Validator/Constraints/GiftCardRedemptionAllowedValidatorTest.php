<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Validator\Constraints;

use Madcoders\SyliusGiftCardPlugin\Checker\GiftCardTenderCheckerInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCard;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\GiftCardRedemptionAllowed;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\GiftCardRedemptionAllowedValidator;
use Sylius\Component\Core\Model\Order as CoreOrder;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\Order;

/**
 * Refusing to complete an order that still carries gift cards after the basket stopped having
 * anything a gift card may pay for.
 *
 * This runs on every checkout in the shop, so what it leaves alone matters as much as what it
 * refuses.
 */
final class GiftCardRedemptionAllowedValidatorTest extends ConstraintValidatorTestCase
{
    private bool $allowed = true;

    public function testItRefusesAnOrderWhoseAppliedCardsCanSettleNothing(): void
    {
        $this->allowed = false;

        $this->validator->validate($this->createOrder(withGiftCard: true), new GiftCardRedemptionAllowed());

        $this->buildViolation('madcoders_sylius_gift_card.order.gift_card_cannot_pay_for_gift_card')->assertRaised();
    }

    public function testItRaisesOneViolationHoweverManyCardsAreApplied(): void
    {
        $this->allowed = false;

        $order = $this->createOrder(withGiftCard: true);
        $order->addGiftCard($this->createGiftCard('GIFT-B'));

        $this->validator->validate($order, new GiftCardRedemptionAllowed());

        $this->buildViolation('madcoders_sylius_gift_card.order.gift_card_cannot_pay_for_gift_card')->assertRaised();
    }

    public function testItAllowsAnOrderThatStillHasSomethingToSettle(): void
    {
        $this->allowed = true;

        $this->validator->validate($this->createOrder(withGiftCard: true), new GiftCardRedemptionAllowed());

        $this->assertNoViolation();
    }

    public function testItLeavesAnOrderWithNoCardsAppliedAlone(): void
    {
        // The ordinary case, and the one that would break the whole shop if it were wrong: every
        // checkout runs this constraint, gift cards or not.
        $this->allowed = false;

        $this->validator->validate($this->createOrder(withGiftCard: false), new GiftCardRedemptionAllowed());

        $this->assertNoViolation();
    }

    public function testItLeavesAnOrderThatCannotHoldGiftCardsAlone(): void
    {
        // A host that has not applied OrderTrait. Its orders carry no gift cards, so there is
        // nothing here to refuse - and an exception would take its checkout down.
        $this->allowed = false;

        $this->validator->validate(new CoreOrder(), new GiftCardRedemptionAllowed());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        $checker = $this->createMock(GiftCardTenderCheckerInterface::class);
        $checker->method('allowsRedemptionOn')->willReturnCallback(fn (BaseOrderInterface $order): bool => $this->allowed);

        return new GiftCardRedemptionAllowedValidator($checker);
    }

    private function createOrder(bool $withGiftCard): Order
    {
        $order = new Order();

        if ($withGiftCard) {
            $order->addGiftCard($this->createGiftCard('GIFT-A'));
        }

        return $order;
    }

    private function createGiftCard(string $code): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCode($code);
        $giftCard->setInitialAmount(5_000);

        return $giftCard;
    }
}
