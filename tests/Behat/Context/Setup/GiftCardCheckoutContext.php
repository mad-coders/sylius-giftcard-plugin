<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Modifier\GiftCardBalanceModifierInterface;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Payment\PaymentTransitions;
use Webmozart\Assert\Assert;

/**
 * Drives and inspects the path nothing else covered: a gift card applied to a real order that is
 * then actually placed.
 *
 * Everything up to this point tested the cart (where no money moves) and the modifier in isolation
 * (against hand-built adjustments). The interesting failures live in between - in the order
 * processor chain and the state machine wiring - and are silent when they happen.
 */
final readonly class GiftCardCheckoutContext implements Context
{
    /** @param ExampleFactoryInterface<GiftCardInterface> $giftCardExampleFactory */
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private ExampleFactoryInterface $giftCardExampleFactory,
        private GiftCardApplicatorInterface $giftCardApplicator,
        private GiftCardBalanceModifierInterface $giftCardBalanceModifier,
        private GiftCardRepositoryInterface $giftCardRepository,
        private ObjectManager $giftCardManager,
        private StateMachineInterface $stateMachine,
    ) {
    }

    /**
     * @When this order is placed
     */
    public function thisOrderIsPlaced(): void
    {
        $order = $this->order();

        // A gift card covering the whole total makes Sylius' payment processor remove the payment
        // outright - the right outcome, since the customer should not be sent to a gateway for
        // zero. The checkout then has to *skip* the payment step rather than select a method on a
        // payment that no longer exists; `complete` is only reachable from payment_selected or
        // payment_skipped.
        $this->stateMachine->apply(
            $order,
            OrderCheckoutTransitions::GRAPH,
            null === $order->getLastPayment()
                ? OrderCheckoutTransitions::TRANSITION_SKIP_PAYMENT
                : OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT,
        );

        $this->stateMachine->apply(
            $order,
            OrderCheckoutTransitions::GRAPH,
            OrderCheckoutTransitions::TRANSITION_COMPLETE,
        );

        $this->giftCardManager->flush();
    }

    /**
     * @When the payment for this order fails
     */
    public function thePaymentForThisOrderFails(): void
    {
        $order = $this->order();

        $payment = $order->getLastPayment();
        Assert::notNull($payment, 'The order has no payment to fail.');

        // Sylius reacts to this by replacing the payment with a fresh one, sized from the order
        // total. The cards were already debited when the order was placed, so this is where a
        // customer could be charged the same money twice.
        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL);

        $this->giftCardManager->flush();
    }

    /**
     * @Then the customer should be asked to pay :amount to retry
     */
    public function theCustomerShouldBeAskedToPayToRetry(string $amount): void
    {
        $order = $this->refreshedOrder();

        $payment = $order->getLastPayment(PaymentInterface::STATE_NEW);
        Assert::notNull($payment, 'Sylius did not create a payment for the customer to retry with.');
        Assert::same($payment->getAmount(), self::toMinorUnits($amount));
    }

    /**
     * @Then this order should be fully paid
     */
    public function thisOrderShouldBeFullyPaid(): void
    {
        // Sylius decides the payment state by comparing completed payments against the order total.
        // Under the tender model the total stays at full value while the payment is reduced, so
        // without a resolver that knows about gift cards the order sticks at partially_paid - and
        // the `pay` transition that issues purchased cards and sends their codes never fires.
        $order = $this->refreshedOrder();

        Assert::same(
            $order->getPaymentState(),
            OrderPaymentStates::STATE_PAID,
            sprintf('The order is "%s" rather than paid.', (string) $order->getPaymentState()),
        );
    }

    /**
     * @Then this order should have nothing left to pay
     */
    public function thisOrderShouldHaveNothingLeftToPay(): void
    {
        // The order keeps its value - the goods still cost what they cost - and the payment is what
        // goes to zero. That is the whole difference between a gift card and a discount.
        $order = $this->refreshedOrder();

        $payment = $order->getLastPayment();
        Assert::notNull($payment, 'The order should still carry a payment, for zero.');
        Assert::same($payment->getAmount(), 0);
    }

    /**
     * @Given the gift card :code worth :amount is applied to this order
     */
    public function theGiftCardWorthIsAppliedToThisOrder(string $code, string $amount): void
    {
        $giftCard = $this->giftCardExampleFactory->create([
            'code' => $code,
            'initial_amount' => self::toMinorUnits($amount),
            'channel' => $this->channel(),
        ]);

        $this->giftCardManager->persist($giftCard);
        $this->giftCardManager->flush();

        $this->giftCardApplicator->apply($this->order(), $giftCard);

        $this->giftCardManager->flush();
    }

    /**
     * @Given the gift card :code has been spent down to :balance meanwhile
     */
    public function theGiftCardHasBeenSpentDownToMeanwhile(string $code, string $balance): void
    {
        // Simulates the same code being used on another order between applying it here and placing
        // this one - two devices, or a code shared with somebody else.
        $giftCard = $this->giftCard($code);

        $spend = $giftCard->getAmount() - self::toMinorUnits($balance);
        Assert::greaterThan($spend, 0, 'The card already holds less than that.');

        $this->giftCardBalanceModifier->debit($giftCard, $spend);

        $this->giftCardManager->flush();
    }

    /**
     * @Given an administrator topped the gift card :code up by :amount
     */
    public function anAdministratorToppedTheGiftCardUpBy(string $code, string $amount): void
    {
        // Goodwill, or a correction. It goes through the same modifier the admin screen uses.
        $this->giftCardBalanceModifier->credit($this->giftCard($code), self::toMinorUnits($amount));

        $this->giftCardManager->flush();
    }

    /**
     * @Given the gift card :code has expired meanwhile
     */
    public function theGiftCardHasExpiredMeanwhile(string $code): void
    {
        // Between the customer applying the card and the order being placed. Long checkouts and
        // abandoned carts make this ordinary, not exotic.
        $giftCard = $this->attachedGiftCard($code);
        $giftCard->setExpiresAt(new \DateTime('-1 day'));

        $this->giftCardManager->flush();
    }

    /**
     * @Given the gift card :code has been disabled meanwhile
     */
    public function theGiftCardHasBeenDisabledMeanwhile(string $code): void
    {
        $giftCard = $this->attachedGiftCard($code);
        $giftCard->disable();

        $this->giftCardManager->flush();
    }

    /**
     * @Then the gift card :code should be worth :balance
     */
    public function theGiftCardShouldBeWorth(string $code, string $balance): void
    {
        Assert::same($this->giftCard($code)->getAmount(), self::toMinorUnits($balance));
    }

    /**
     * @Then the gift card :code should have :count entry/entries in its balance history
     */
    public function theGiftCardShouldHaveEntriesInItsBalanceHistory(string $code, int $count): void
    {
        Assert::count($this->giftCard($code)->getTransactions(), $count);
    }

    /**
     * @Then the gift card :code should be used by :customer
     */
    public function theGiftCardShouldBeUsedBy(string $code, CustomerInterface $customer): void
    {
        $redeemer = $this->giftCard($code)->getRedeemer();

        Assert::notNull($redeemer);
        Assert::same($redeemer->getEmail(), $customer->getEmail());
    }

    /**
     * @Then the gift card :code should not be used by anybody
     */
    public function theGiftCardShouldNotBeUsedByAnybody(string $code): void
    {
        Assert::null($this->giftCard($code)->getRedeemer());
    }

    /**
     * @Then the payment for this order should be :amount
     */
    public function thePaymentForThisOrderShouldBe(string $amount): void
    {
        // The assertion that matters most here. Sylius' payment processor takes the amount from
        // Order::getTotal(); if the gift card processor ever runs after it, the payment keeps the
        // pre-discount total and the customer is charged full price while their card is debited -
        // and nothing anywhere throws.
        $order = $this->refreshedOrder();

        $payment = $order->getLastPayment();
        Assert::notNull($payment, 'The order has no payment.');
        Assert::same($payment->getAmount(), self::toMinorUnits($amount));
    }

    /**
     * @Then the total of this order should be :amount
     */
    public function theTotalOfThisOrderShouldBe(string $amount): void
    {
        Assert::same($this->refreshedOrder()->getTotal(), self::toMinorUnits($amount));
    }

    /**
     * The card as the current unit of work already knows it.
     *
     * Unlike {@see self::giftCard()} this does not clear the entity manager first: these steps run
     * mid-scenario, and detaching the order under them makes the next checkout step fail trying to
     * remove an adjustment it no longer owns.
     */
    private function attachedGiftCard(string $code): GiftCardInterface
    {
        $giftCard = $this->giftCardRepository->findOneByCode($code);
        Assert::isInstanceOf($giftCard, GiftCardInterface::class, sprintf('There is no gift card "%s".', $code));

        return $giftCard;
    }

    private function giftCard(string $code): GiftCardInterface
    {
        // Read through the repository after clearing, so an assertion cannot pass on an in-memory
        // object that was never actually written.
        $this->giftCardManager->clear();

        $giftCard = $this->giftCardRepository->findOneByCode($code);
        Assert::isInstanceOf($giftCard, GiftCardInterface::class, sprintf('There is no gift card "%s".', $code));

        return $giftCard;
    }

    private function refreshedOrder(): OrderInterface
    {
        $order = $this->order();
        $this->giftCardManager->refresh($order);

        return $order;
    }

    private function order(): OrderInterface
    {
        /** @var OrderInterface $order */
        $order = $this->sharedStorage->get('order');

        return $order;
    }

    private function channel(): ChannelInterface
    {
        /** @var ChannelInterface $channel */
        $channel = $this->sharedStorage->get('channel');

        return $channel;
    }

    private static function toMinorUnits(string $amount): int
    {
        $normalised = preg_replace('/[^0-9.]/', '', $amount) ?? '';

        return (int) round(((float) $normalised) * 100);
    }
}
