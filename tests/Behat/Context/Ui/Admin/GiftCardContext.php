<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Behat\NotificationType;
use Sylius\Behat\Page\Admin\Crud\IndexPageInterface;
use Sylius\Behat\Service\NotificationCheckerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard\AdjustBalancePageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard\CreatePageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard\ShowPageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard\UpdatePageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\Product\UpdatePageInterface as ProductUpdatePageInterface;
use Webmozart\Assert\Assert;

final class GiftCardContext implements Context
{
    public function __construct(
        private readonly SharedStorageInterface $sharedStorage,
        private readonly IndexPageInterface $indexPage,
        private readonly CreatePageInterface $createPage,
        private readonly ShowPageInterface $showPage,
        private readonly UpdatePageInterface $updatePage,
        private readonly AdjustBalancePageInterface $adjustBalancePage,
        private readonly ProductUpdatePageInterface $productUpdatePage,
        private readonly NotificationCheckerInterface $notificationChecker,
        private readonly GiftCardRepositoryInterface $giftCardRepository,
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

    /**
     * @When I want to edit the gift card :code
     */
    public function iWantToEditTheGiftCard(string $code): void
    {
        $this->updatePage->open(['id' => $this->getGiftCardIdByCode($code)]);
    }

    /**
     * @Then I should not be able to change its code
     */
    public function iShouldNotBeAbleToChangeItsCode(): void
    {
        // A code is bearer money: the customer is holding it, and it is the only link between an
        // order and the card that paid for it. Renaming an issued card would invalidate the one in
        // their hand and silently strand every refund.
        Assert::false(
            $this->updatePage->isCodeEditable(),
            'The code can still be edited on an issued gift card.',
        );
    }

    /**
     * @Then its code should still be :code
     */
    public function itsCodeShouldStillBe(string $code): void
    {
        Assert::same($this->updatePage->getCode(), $code);
    }

    /**
     * @Then I should not be able to change its initial amount
     */
    public function iShouldNotBeAbleToChangeItsInitialAmount(): void
    {
        // The face value is what orders were priced against; it cannot move under them.
        Assert::false(
            $this->updatePage->isInitialAmountEditable(),
            'The initial amount can still be edited on an issued gift card.',
        );
    }

    /**
     * @Then the balance on the form should still be :balance
     */
    public function theBalanceOnTheFormShouldStillBe(string $balance): void
    {
        // A rejected adjustment leaves the admin on the form, so this reads the balance the form
        // itself shows - which is the number that must not have moved.
        Assert::same($this->adjustBalancePage->getCurrentBalance(), $balance);
    }

    /**
     * @Then I should be told the adjustment is not possible
     */
    public function iShouldBeToldTheAdjustmentIsNotPossible(): void
    {
        // The model refuses adjustments that would break its invariants - overdrawing a card, or
        // crediting it above its face value. The admin has to be told which, on the form; letting
        // the exception out would be a 500 with the reason buried in a log.
        Assert::true(
            $this->adjustBalancePage->hasValidationMessage(),
            'The form came back without telling the administrator what was wrong.',
        );
    }

    /**
     * @Then I should be told the amount must be greater than zero
     */
    public function iShouldBeToldTheAmountMustBeGreaterThanZero(): void
    {
        // Asserts the sentence, not merely that something was said. Symfony resolves a constraint
        // violation in the `validators` catalogue, so a message key left in `messages` renders as
        // the key itself - which looks like a working form to every test that only checks that an
        // error appeared, and like a bug report to the administrator reading it.
        Assert::same(
            $this->adjustBalancePage->getValidationMessage(),
            'The amount must be greater than zero.',
            'The form did not put the constraint message in front of the administrator in English.',
        );
    }

    /**
     * @Then the issued card's code should start with :prefix
     */
    public function theIssuedCardsCodeShouldStartWith(string $prefix): void
    {
        Assert::startsWith($this->issuedCard()->getCode() ?? '', $prefix);
    }

    /**
     * @Then the issued card's code should have :length characters after the prefix :prefix
     */
    public function theIssuedCardsCodeShouldHaveCharactersAfterThePrefix(int $length, string $prefix): void
    {
        $code = $this->issuedCard()->getCode() ?? '';

        Assert::same(mb_strlen(mb_substr($code, mb_strlen($prefix))), $length);
    }

    /**
     * @Then the issued card should expire in about :days days
     */
    public function theIssuedCardShouldExpireInAboutDays(int $days): void
    {
        $expiresAt = $this->issuedCard()->getExpiresAt();
        Assert::notNull($expiresAt, 'The card was issued without an expiry date.');

        self::assertIsAboutDaysAway($expiresAt, $days);
    }

    /** A day either side, so the assertion does not depend on the clock ticking over mid-run. */
    private static function assertIsAboutDaysAway(\DateTimeInterface $expiresAt, int $days): void
    {
        $actual = (new \DateTimeImmutable())->diff($expiresAt)->days;

        Assert::greaterThanEq($actual, $days - 1, sprintf('The card expires in %d days, not about %d.', $actual, $days));
        Assert::lessThanEq($actual, $days + 1, sprintf('The card expires in %d days, not about %d.', $actual, $days));
    }

    /**
     * @Then the expiry date should already be filled in about :days days from now
     */
    public function theExpiryDateShouldAlreadyBeFilledInAboutDaysFromNow(int $days): void
    {
        // Read off the form, not the database: an expiry is a term of the sale, so the point is
        // that the administrator can see and change the date before they save it.
        $expiresAt = $this->createPage->getExpiryDate();
        Assert::notEmpty($expiresAt, 'The create form offered an empty expiry date.');

        self::assertIsAboutDaysAway(new \DateTimeImmutable($expiresAt), $days);
    }

    /**
     * @When I clear its expiry date
     */
    public function iClearItsExpiryDate(): void
    {
        $this->createPage->specifyExpiryDate('');
    }

    /**
     * @Then I should be told the expiry date is required
     */
    public function iShouldBeToldTheExpiryDateIsRequired(): void
    {
        Assert::contains(
            $this->createPage->getValidationMessages(),
            'enter the date this gift card expires',
            'The form accepted a gift card with no expiry date.',
        );
    }

    /**
     * @Then no gift card should have been created
     */
    public function noGiftCardShouldHaveBeenCreated(): void
    {
        Assert::isEmpty($this->giftCardRepository->findAll(), 'A gift card was created despite the form being rejected.');
    }

    /**
     * The card the admin just created, read back from the database rather than the page.
     */
    private function issuedCard(): \Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface
    {
        $cards = $this->giftCardRepository->findAll();
        Assert::notEmpty($cards, 'No gift card was created.');

        $card = end($cards);
        Assert::isInstanceOf($card, \Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface::class);

        return $card;
    }
}
