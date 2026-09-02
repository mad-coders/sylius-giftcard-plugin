<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Behat\NotificationType;
use Sylius\Behat\Page\Admin\Crud\IndexPageInterface;
use Sylius\Behat\Service\NotificationCheckerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard\AdjustBalancePageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard\CreatePageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard\GiftCardFormPageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard\ShowPageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard\UpdatePageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\Product\UpdatePageInterface as ProductUpdatePageInterface;
use Webmozart\Assert\Assert;

final class GiftCardContext implements Context
{
    /**
     * Whichever of the two gift card forms the scenario is currently on.
     *
     * The create and update pages render the same form type, so the steps about what it refuses are
     * written once. Which page is open cannot be asked at the time - the update page's isOpen()
     * needs the id it was opened with - so it is remembered as the scenario opens it.
     */
    private ?GiftCardFormPageInterface $giftCardFormPage = null;

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
        private readonly ObjectManager $giftCardManager,
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
        $this->giftCardFormPage = $this->createPage;
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
        $this->giftCardFormPage = $this->updatePage;
    }

    /**
     * @When I disable it
     */
    public function iDisableIt(): void
    {
        $this->updatePage->disable();
    }

    /**
     * @When I save my changes to this gift card
     */
    public function iSaveMyChangesToThisGiftCard(): void
    {
        $this->updatePage->saveChanges();
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
     * @When I leave its initial amount empty
     */
    public function iLeaveItsInitialAmountEmpty(): void
    {
        // Typed as empty rather than skipped, because that is what an administrator does: the field
        // is on the form and they tab past it.
        $this->createPage->specifyInitialAmount('');
    }

    /**
     * @When I set its expiry date to :expiresAt
     * @When I move its expiry date to :expiresAt
     */
    public function iSetItsExpiryDateTo(string $expiresAt): void
    {
        $this->giftCardForm()->specifyExpiryDate($expiresAt);
    }

    /**
     * @When I move its expiry date to :days days from now
     */
    public function iMoveItsExpiryDateToDaysFromNow(int $days): void
    {
        $this->giftCardForm()->specifyExpiryDate(
            (new \DateTimeImmutable(sprintf('+%d days', $days)))->format('Y-m-d H:i'),
        );
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
     * @Then I should be told on the initial amount that it is required
     */
    public function iShouldBeToldOnTheInitialAmountThatItIsRequired(): void
    {
        $this->assertFieldWasRefused(
            'Initial amount',
            'Please enter the amount the gift card is worth.',
            'The form took a gift card with no initial amount.',
        );
    }

    /**
     * @Then I should be told on the initial amount that it must be greater than zero
     */
    public function iShouldBeToldOnTheInitialAmountThatItMustBeGreaterThanZero(): void
    {
        $this->assertFieldWasRefused(
            'Initial amount',
            'The amount must be greater than zero.',
            'The form took a gift card worth nothing.',
        );
    }

    /**
     * @Then I should be told on the code that it is already taken
     */
    public function iShouldBeToldOnTheCodeThatItIsAlreadyTaken(): void
    {
        $this->assertFieldWasRefused(
            'Code',
            'A gift card with this code already exists.',
            'The form took a code that is already in use, leaving the unique index to refuse it as a 500.',
        );
    }

    /**
     * @Then I should be told on the expiry date that it cannot be in the past
     */
    public function iShouldBeToldOnTheExpiryDateThatItCannotBeInThePast(): void
    {
        $this->assertFieldWasRefused(
            'Expires at',
            'The expiry date cannot be in the past',
            'The form took an expiry date that makes the balance unspendable.',
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
     * @Then there should still be only one gift card
     */
    public function thereShouldStillBeOnlyOneGiftCard(): void
    {
        Assert::count(
            $this->giftCardRepository->findAll(),
            1,
            'A second gift card was created despite the form being rejected.',
        );
    }

    /**
     * @Then the gift card :code should be worth :amount
     */
    public function theGiftCardShouldBeWorth(string $code, string $amount): void
    {
        Assert::same(
            $this->storedGiftCard($code)->getInitialAmount(),
            self::toMinorUnits($amount),
            'The card was issued without the face value the administrator typed.',
        );
    }

    /**
     * @Then the gift card :code should have :amount left
     */
    public function theGiftCardShouldHaveLeft(string $code, string $amount): void
    {
        // Read separately from the face value, because they are set by one call and a card whose
        // balance did not follow its face value is unspendable however right the face value looks.
        Assert::same(
            $this->storedGiftCard($code)->getAmount(),
            self::toMinorUnits($amount),
            'The card was issued with a balance that does not match its face value.',
        );
    }

    /** A written price such as "$75.00" as the minor units the model stores. */
    private static function toMinorUnits(string $amount): int
    {
        return (int) round(((float) (preg_replace('/[^0-9.]/', '', $amount) ?? '')) * 100);
    }

    /**
     * @Then the gift card :code should still expire in the future
     */
    public function theGiftCardShouldStillExpireInTheFuture(string $code): void
    {
        $expiresAt = $this->storedGiftCard($code)->getExpiresAt();
        Assert::notNull($expiresAt, 'The card lost its expiry date.');

        Assert::true(
            $expiresAt > new \DateTimeImmutable(),
            sprintf('The card now expires at %s, which is in the past.', $expiresAt->format('c')),
        );
    }

    /**
     * @Then the gift card :code should expire in about :days days
     */
    public function theGiftCardShouldExpireInAboutDays(string $code, int $days): void
    {
        $expiresAt = $this->storedGiftCard($code)->getExpiresAt();
        Assert::notNull($expiresAt, 'The card lost its expiry date.');

        self::assertIsAboutDaysAway($expiresAt, $days);
    }

    /**
     * @Then the gift card :code should still be expired
     */
    public function theGiftCardShouldStillBeExpired(string $code): void
    {
        Assert::true(
            $this->storedGiftCard($code)->isExpired(),
            'The card came back from the form no longer expired, so the save invented a new date.',
        );
    }

    /**
     * @Then the gift card :code should be disabled
     */
    public function theGiftCardShouldBeDisabled(string $code): void
    {
        // Also the proof that the save went through at all: a refused form leaves the card enabled.
        Assert::false(
            $this->storedGiftCard($code)->isEnabled(),
            'The card is still enabled, so the edit was refused rather than saved.',
        );
    }

    /**
     * Asserts the form came back with a message, that it is the right one, and that it is attached
     * to the field it is about.
     *
     * All three matter and they fail differently. Nothing at all is the constraint never running -
     * issue #44. The wrong words are the message resolving in the wrong translation catalogue and
     * rendering as its own key - issue #37. The right words in the wrong place is a class constraint
     * that missed its `atPath` and landed at the top of the form, where an administrator with eight
     * fields in front of them has to guess which one it means.
     */
    private function assertFieldWasRefused(string $field, string $message, string $whatWentWrong): void
    {
        Assert::contains($this->giftCardForm()->getFieldValidationMessage($field), $message, $whatWentWrong);
    }

    /** The form the scenario is on, whichever of the two it opened. */
    private function giftCardForm(): GiftCardFormPageInterface
    {
        Assert::notNull($this->giftCardFormPage, 'No gift card form has been opened in this scenario.');

        return $this->giftCardFormPage;
    }

    /**
     * A gift card read back from the database, past anything the context is still holding.
     *
     * The admin request runs against the same entity manager as this context, so an object left in
     * the identity map would answer with the values the scenario set up rather than the ones the
     * form saved - and a step asserting a change was refused would pass whether it was or not.
     *
     * `refresh()` on the one card, not `clear()` on the manager. Clearing would detach everything
     * the Sylius setup contexts and SharedStorage are still holding - the channel, the admin user,
     * the order - which is harmless only for as long as every caller happens to be a terminal
     * `Then`. That is a landmine for whoever writes the next step, not a design.
     */
    private function storedGiftCard(string $code): GiftCardInterface
    {
        $giftCard = $this->giftCardRepository->findOneBy(['code' => $code]);
        Assert::isInstanceOf($giftCard, GiftCardInterface::class, sprintf('There is no gift card "%s".', $code));

        $this->giftCardManager->refresh($giftCard);

        return $giftCard;
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
