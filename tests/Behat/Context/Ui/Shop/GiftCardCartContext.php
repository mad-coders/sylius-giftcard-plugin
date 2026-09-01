<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Sylius\Behat\NotificationType;
use Sylius\Behat\Service\NotificationCheckerInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Cart\GiftCardPageInterface;
use Webmozart\Assert\Assert;

final class GiftCardCartContext implements Context
{
    public function __construct(
        private readonly GiftCardPageInterface $giftCardPage,
        private readonly NotificationCheckerInterface $notificationChecker,
    ) {
    }

    /**
     * @When I look at my cart
     */
    public function iLookAtMyCart(): void
    {
        $this->giftCardPage->open();
    }

    /**
     * @Then my cart should hold :count separate line/lines
     */
    public function myCartShouldHoldSeparateLines(int $count): void
    {
        $this->openCartIfNeeded();

        Assert::same(
            $this->giftCardPage->countItems(),
            $count,
            'Two gift cards bought for different amounts must not be collapsed into one line.',
        );
    }

    /**
     * @Then the lines should be priced :prices
     */
    public function theLinesShouldBePriced(string $prices): void
    {
        $this->openCartIfNeeded();

        $parts = preg_split('/\s*(?:,|and)\s*/', trim($prices));
        Assert::isArray($parts);

        $expected = array_map(static fn (string $price): string => trim($price), $parts);
        $actual = $this->giftCardPage->getItemUnitPrices();

        sort($expected);
        sort($actual);

        Assert::same($actual, $expected);
    }

    /**
     * @Then my cart should come to :total
     */
    public function myCartShouldComeTo(string $total): void
    {
        $this->openCartIfNeeded();

        Assert::same($this->giftCardPage->getItemsTotal(), $total);
    }

    /**
     * @When I apply the gift card :code
     * @When I try to apply the gift card :code
     */
    public function iApplyTheGiftCard(string $code): void
    {
        $this->openCartIfNeeded();

        $this->giftCardPage->applyGiftCard($code);
    }

    /**
     * @When I try to apply :count wrong gift card codes
     */
    public function iTryToApplyWrongGiftCardCodes(int $count): void
    {
        // Distinct codes, so nothing can pass by being remembered from the attempt before.
        for ($attempt = 1; $attempt <= $count; ++$attempt) {
            $this->iApplyTheGiftCard(sprintf('GIFT-WRONG-%d', $attempt));
        }
    }

    /**
     * @When I remove the gift card :code
     */
    public function iRemoveTheGiftCard(string $code): void
    {
        $this->openCartIfNeeded();

        $this->giftCardPage->removeGiftCard($code);
    }

    /**
     * @Then the gift card :code should be applied to my cart
     */
    public function theGiftCardShouldBeAppliedToMyCart(string $code): void
    {
        $this->openCartIfNeeded();

        Assert::inArray($code, $this->giftCardPage->getAppliedGiftCardCodes());
    }

    /**
     * @Then no gift card should be applied to my cart
     */
    public function noGiftCardShouldBeAppliedToMyCart(): void
    {
        $this->openCartIfNeeded();

        Assert::isEmpty($this->giftCardPage->getAppliedGiftCardCodes());
    }

    /**
     * @Then the gift card :code should no longer be applied to my cart
     */
    public function theGiftCardShouldNoLongerBeAppliedToMyCart(string $code): void
    {
        $this->openCartIfNeeded();

        Assert::false(in_array($code, $this->giftCardPage->getAppliedGiftCardCodes(), true));
    }

    /**
     * @Then my cart total should be :total
     */
    public function myCartTotalShouldBe(string $total): void
    {
        $this->openCartIfNeeded();

        Assert::same($this->giftCardPage->getOrderTotal(), $total);
    }

    /**
     * @Then I should have :amount left to pay
     */
    public function iShouldHaveLeftToPay(string $amount): void
    {
        $this->openCartIfNeeded();

        Assert::same($this->giftCardPage->getAmountToPay(), $amount);
    }

    /**
     * @Then my gift cards should cover :amount of my cart
     */
    public function theGiftCardsShouldReduceMyCartBy(string $amount): void
    {
        $this->openCartIfNeeded();

        Assert::same($this->giftCardPage->getGiftCardTotal(), $amount);
    }

    /**
     * @Then the gift card :code should have :balance left
     */
    public function theGiftCardShouldHaveLeft(string $code, string $balance): void
    {
        $this->openCartIfNeeded();

        Assert::same($this->giftCardPage->getGiftCardBalance($code), $balance);
    }

    /**
     * @Then I should be notified that the gift card has been applied
     */
    public function iShouldBeNotifiedThatTheGiftCardHasBeenApplied(): void
    {
        $this->notificationChecker->checkNotification(
            'The gift card has been applied to your cart.',
            NotificationType::success(),
        );
    }

    /**
     * @When I refresh the cart
     */
    public function iRefreshTheCart(): void
    {
        // open() unconditionally, not openCartIfNeeded(): the point is to reprocess the cart, and a
        // page object that thinks it is already open would skip the request.
        $this->giftCardPage->open();
    }

    /**
     * @Then I should be notified that the gift card cannot be used
     */
    public function iShouldBeNotifiedThatTheGiftCardCannotBeUsed(): void
    {
        // The same words whatever went wrong - no such code, expired, disabled, spent, wrong store.
        // A scenario that expects this after an unknown code and again after a real but unusable one
        // is what pins that the endpoint is not a code-existence oracle.
        $this->notificationChecker->checkNotification(
            'This gift card code cannot be used. Check it and try again.',
            NotificationType::failure(),
        );
    }

    /**
     * @Then I should be told I have tried too many gift card codes
     */
    public function iShouldBeToldIHaveTriedTooManyGiftCardCodes(): void
    {
        $this->notificationChecker->checkNotification(
            'Too many gift card codes have been tried from here. Please wait a few minutes before trying again.',
            NotificationType::failure(),
        );
    }

    private function openCartIfNeeded(): void
    {
        if (!$this->giftCardPage->isOpen()) {
            $this->giftCardPage->open();
        }
    }
}
