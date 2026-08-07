<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Sylius\Behat\NotificationType;
use Sylius\Behat\Page\Admin\Crud\IndexPageInterface;
use Sylius\Behat\Service\NotificationCheckerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard\AdjustBalancePageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard\CreatePageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard\ShowPageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\Product\UpdatePageInterface as ProductUpdatePageInterface;
use Webmozart\Assert\Assert;

final class GiftCardContext implements Context
{
    public function __construct(
        private readonly SharedStorageInterface $sharedStorage,
        private readonly IndexPageInterface $indexPage,
        private readonly CreatePageInterface $createPage,
        private readonly ShowPageInterface $showPage,
        private readonly AdjustBalancePageInterface $adjustBalancePage,
        private readonly ProductUpdatePageInterface $productUpdatePage,
        private readonly NotificationCheckerInterface $notificationChecker,
    ) {
    }

    /**
     * @When I browse gift cards
     */
    public function iBrowseGiftCards(): void
    {
        $this->indexPage->open();
    }

    /**
     * @When I want to create a new gift card
     */
    public function iWantToCreateANewGiftCard(): void
    {
        $this->createPage->open();
    }

    /**
     * @When I specify its code as :code
     */
    public function iSpecifyItsCodeAs(string $code): void
    {
        $this->createPage->specifyCode($code);
    }

    /**
     * @When I specify its initial amount as :amount
     */
    public function iSpecifyItsInitialAmountAs(string $amount): void
    {
        $this->createPage->specifyInitialAmount($amount);
    }

    /**
     * @When I choose :channelName as its channel
     */
    public function iChooseAsItsChannel(string $channelName): void
    {
        $this->createPage->chooseChannel($channelName);
    }

    /**
     * @When I add this gift card
     */
    public function iAddThisGiftCard(): void
    {
        $this->createPage->create();
    }

    /**
     * @When I view the gift card :code
     */
    public function iViewTheGiftCard(string $code): void
    {
        $this->showPage->open(['id' => $this->getGiftCardIdByCode($code)]);
    }

    /**
     * @When I add :amount to its balance
     */
    public function iAddToItsBalance(string $amount): void
    {
        $this->showPage->adjustBalance();
        $this->adjustBalancePage->adjust('credit', $amount);
    }

    /**
     * @When I take :amount from its balance
     */
    public function iTakeFromItsBalance(string $amount): void
    {
        $this->showPage->adjustBalance();
        $this->adjustBalancePage->adjust('debit', $amount);
    }

    /**
     * @Then the gift card :code should appear in the list
     */
    public function theGiftCardShouldAppearInTheList(string $code): void
    {
        if (!$this->indexPage->isOpen()) {
            $this->indexPage->open();
        }

        Assert::true($this->indexPage->isSingleResourceOnPage(['code' => $code]));
    }

    /**
     * @Then its remaining balance should be :balance
     */
    public function itsRemainingBalanceShouldBe(string $balance): void
    {
        Assert::same($this->showPage->getBalance(), $balance);
    }

    /**
     * @Then it should have been bought by :email
     */
    public function itShouldHaveBeenBoughtBy(string $email): void
    {
        Assert::same($this->showPage->getPurchaser(), $email);
    }

    /**
     * @Then it should be used by :email
     */
    public function itShouldBeUsedBy(string $email): void
    {
        Assert::same($this->showPage->getRedeemer(), $email);
    }

    /**
     * @Then its balance history should have :count entries
     */
    public function itsBalanceHistoryShouldHaveEntries(int $count): void
    {
        Assert::same($this->showPage->countTransactions(), $count);
    }

    /**
     * @Then I should be notified that the balance has been adjusted
     */
    public function iShouldBeNotifiedThatTheBalanceHasBeenAdjusted(): void
    {
        $this->notificationChecker->checkNotification(
            'The gift card balance has been adjusted.',
            NotificationType::success(),
        );
    }

    /**
     * @When I edit the product :product and save it unchanged
     */
    public function iEditTheProductAndSaveItUnchanged(ProductInterface $product): void
    {
        $this->productUpdatePage->open(['id' => $product->getId()]);

        Assert::true(
            $this->productUpdatePage->hasGiftCardField(),
            'The product form has no gift card field. Because the field is mapped, an unrendered '
            . 'checkbox submits as absent and silently clears the flag on every save.',
        );

        $this->productUpdatePage->saveUnchanged();
    }

    private function getGiftCardIdByCode(string $code): int
    {
        /** @var array<string, int> $ids */
        $ids = $this->sharedStorage->has('gift_card_ids') ? $this->sharedStorage->get('gift_card_ids') : [];

        Assert::keyExists($ids, $code, sprintf('No gift card with code "%s" was created in this scenario.', $code));

        return $ids[$code];
    }
}
