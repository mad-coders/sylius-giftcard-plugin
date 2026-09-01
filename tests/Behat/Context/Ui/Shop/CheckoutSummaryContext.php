<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Sylius\Behat\NotificationType;
use Sylius\Behat\Service\NotificationCheckerInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Checkout\PaymentStepPageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Checkout\SummaryPageInterface;
use Webmozart\Assert\Assert;

/**
 * What a customer is told they will be charged, on the last page before they pay.
 */
final readonly class CheckoutSummaryContext implements Context
{
    public function __construct(
        private SummaryPageInterface $summaryPage,
        private NotificationCheckerInterface $notificationChecker,
        private PaymentStepPageInterface $paymentStepPage,
    ) {
    }

    /**
     * @When I look at the checkout summary
     */
    public function iLookAtTheCheckoutSummary(): void
    {
        $this->summaryPage->open();
    }

    /**
     * @Then the summary should show :amount covered by my gift cards
     */
    public function theSummaryShouldShowCoveredByMyGiftCards(string $amount): void
    {
        Assert::same($this->summaryPage->getGiftCardTotal(), $amount);
    }

    /**
     * @Then the summary should tell me I will be charged :amount
     */
    public function theSummaryShouldTellMeIWillBeCharged(string $amount): void
    {
        // The sidebar's own "Order total" is the full price of the goods, which is not what the
        // customer will be charged. This is the number that has to be right.
        Assert::same($this->summaryPage->getAmountToPay(), $amount);
    }

    /**
     * @Then the summary should say nothing about gift cards
     */
    public function theSummaryShouldSayNothingAboutGiftCards(): void
    {
        Assert::false(
            $this->summaryPage->hasGiftCardTotal(),
            'A checkout with no gift card applied should not carry gift card lines.',
        );
    }

    /**
     * @Then I should be able to redeem a gift card from the checkout
     */
    public function iShouldBeAbleToRedeemAGiftCardFromTheCheckout(): void
    {
        // The panel belongs next to the totals: that is the moment the customer is looking at what
        // they are about to pay, so it is where they look for a way to pay less of it.
        Assert::true(
            $this->summaryPage->hasGiftCardPanel(),
            'There is no gift card panel in the checkout.',
        );
    }

    /**
     * @When I apply the gift card :code from the checkout
     */
    public function iApplyTheGiftCardFromTheCheckout(string $code): void
    {
        $this->summaryPage->applyGiftCard($code);
    }

    /**
     * @When I remove the gift card :code from the checkout
     */
    public function iRemoveTheGiftCardFromTheCheckout(string $code): void
    {
        $this->summaryPage->removeGiftCard($code);
    }

    /**
     * @Then I should still be in the checkout
     */
    public function iShouldStillBeInTheCheckout(): void
    {
        // Applying a card used to send the customer back to the cart, losing their place. The
        // redirect target is chosen from a whitelist by key, so this also pins that the checkout
        // key resolves to the checkout rather than falling back to the cart.
        Assert::true(
            $this->summaryPage->isOpen(),
            'Applying a gift card took the customer out of the checkout.',
        );
    }

    /**
     * @Then the gift card :code should be applied in the checkout
     */
    public function theGiftCardShouldBeAppliedInTheCheckout(string $code): void
    {
        Assert::inArray($code, $this->summaryPage->getAppliedGiftCardCodes());
    }

    /**
     * @Then no gift card should be applied in the checkout
     */
    public function noGiftCardShouldBeAppliedInTheCheckout(): void
    {
        Assert::isEmpty($this->summaryPage->getAppliedGiftCardCodes());
    }

    /**
     * @Then I should be told in the checkout that the gift card was applied
     */
    public function iShouldBeToldInTheCheckoutThatTheGiftCardWasApplied(): void
    {
        $this->notificationChecker->checkNotification('The gift card has been applied to your cart.', NotificationType::success());
    }

    /**
     * @Then I should be told in the checkout that the code is not valid
     */
    public function iShouldBeToldInTheCheckoutThatTheCodeIsNotValid(): void
    {
        $this->notificationChecker->checkNotification('There is no gift card with this code.', NotificationType::failure());
    }

    /**
     * @Then I should be told in the checkout that the card cannot be redeemed
     */
    public function iShouldBeToldInTheCheckoutThatTheCardCannotBeRedeemed(): void
    {
        $this->notificationChecker->checkNotification(
            'This gift card cannot be used - it may be expired, disabled or already spent.',
            NotificationType::failure(),
        );
    }

    /**
     * @When I go to the payment step
     */
    public function iGoToThePaymentStep(): void
    {
        $this->paymentStepPage->open();
    }

    /**
     * @Then the payment step should let me redeem a gift card too
     */
    public function thePaymentStepShouldLetMeRedeemAGiftCardToo(): void
    {
        // A different hookable from the summary page's: the earlier steps share Sylius' sidebar,
        // which the summary step blanks entirely. One breaking would leave the other green.
        Assert::true(
            $this->paymentStepPage->hasGiftCardPanel(),
            'There is no gift card panel on the payment step.',
        );
    }

    /**
     * @When I apply the gift card :code from the payment step
     */
    public function iApplyTheGiftCardFromThePaymentStep(string $code): void
    {
        $this->paymentStepPage->applyGiftCard($code);
    }

    /**
     * @Then I should still be on the payment step
     */
    public function iShouldStillBeOnThePaymentStep(): void
    {
        // Asserted on the route, not on a number: the amount to pay is rendered with the same test
        // attribute on every checkout step, so reading "$60.00" proves nothing about where the
        // customer landed.
        Assert::true(
            $this->paymentStepPage->isOpen(),
            'Applying a gift card from the payment step did not return the customer to it.',
        );
    }

    /**
     * @Then I should be told on the payment step that the code is not valid
     */
    public function iShouldBeToldOnThePaymentStepThatTheCodeIsNotValid(): void
    {
        // The checkout steps render no flashes of their own, so the panel renders its own messages.
        // Without that this page comes back byte-identical and the customer is told nothing.
        $this->notificationChecker->checkNotification('There is no gift card with this code.', NotificationType::failure());
    }

    /**
     * @Then the payment step should tell me I will be charged :amount
     */
    public function thePaymentStepShouldTellMeIWillBeCharged(string $amount): void
    {
        // Also pins that the customer was returned to the payment step rather than the cart: the
        // amount is only readable here if this is where they landed.
        Assert::same($this->paymentStepPage->getAmountToPay(), $amount);
    }
}
