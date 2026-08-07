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
     * @When I apply the gift card :code
     * @When I try to apply the gift card :code
     */
    public function iApplyTheGiftCard(string $code): void
    {
        $this->openCartIfNeeded();

        $this->giftCardPage->applyGiftCard($code);
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
     * @Then the gift cards should reduce my cart by :amount
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
     * @Then I should be notified that the gift card does not exist
     */
    public function iShouldBeNotifiedThatTheGiftCardDoesNotExist(): void
    {
        $this->notificationChecker->checkNotification(
            'There is no gift card with this code.',
            NotificationType::failure(),
        );
    }

    /**
     * @Then I should be notified that the gift card cannot be used
     */
    public function iShouldBeNotifiedThatTheGiftCardCannotBeUsed(): void
    {
        $this->notificationChecker->checkNotification(
            'This gift card cannot be used - it may be expired, disabled or already spent.',
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
