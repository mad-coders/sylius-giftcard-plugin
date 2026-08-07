<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardOrigin;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Behat\Service\Checker\EmailCheckerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Webmozart\Assert\Assert;

/**
 * Assertions about the gift cards issued *by* buying them, checked against the database rather than
 * a page: issuing happens on the payment transition, with no screen of its own until the customer
 * account view lands.
 */
final readonly class BoughtGiftCardContext implements Context
{
    public function __construct(
        private GiftCardRepositoryInterface $giftCardRepository,
        private ObjectManager $giftCardManager,
        private EmailCheckerInterface $emailChecker,
    ) {
    }

    /**
     * @Then :customer should have been emailed the code of their gift card
     */
    public function shouldHaveBeenEmailedTheCodeOfTheirGiftCard(CustomerInterface $customer): void
    {
        $this->giftCardManager->clear();

        $email = (string) $customer->getEmail();
        $giftCards = $this->giftCardRepository->findByPurchaser($customer);
        Assert::count($giftCards, 1);

        // Asserting on the code itself, not just that some mail arrived: an email that reaches the
        // customer without the code in it is the same as no email at all.
        Assert::true(
            $this->emailChecker->hasMessageTo((string) $giftCards[0]->getCode(), $email),
            sprintf('No email carrying the gift card code was sent to "%s".', $email),
        );
    }

    /**
     * @Then :customer should not have been emailed anything
     */
    public function shouldNotHaveBeenEmailedAnything(CustomerInterface $customer): void
    {
        Assert::same($this->emailChecker->countMessagesTo((string) $customer->getEmail()), 0);
    }

    /**
     * One counting step rather than a separate "no gift cards" wording: Behat treats two patterns
     * that both match a sentence as ambiguous even when they point at the same method.
     *
     * @Then :count gift card(s) should have been issued to :customer
     */
    public function giftCardsShouldHaveBeenIssuedTo(int $count, CustomerInterface $customer): void
    {
        $this->giftCardManager->clear();

        Assert::count($this->giftCardRepository->findByPurchaser($customer), $count);
    }

    /**
     * @Then the gift card issued to :customer should be worth :amount
     */
    public function theGiftCardIssuedToShouldBeWorth(CustomerInterface $customer, string $amount): void
    {
        $this->giftCardManager->clear();

        $giftCards = $this->giftCardRepository->findByPurchaser($customer);
        Assert::count($giftCards, 1);

        $giftCard = $giftCards[0];
        Assert::same($giftCard->getInitialAmount(), self::toMinorUnits($amount));
        Assert::same($giftCard->getAmount(), self::toMinorUnits($amount));
        Assert::same($giftCard->getOrigin(), GiftCardOrigin::Order);
        Assert::notNull($giftCard->getCode());
    }

    /**
     * @Then the gift card issued to :customer should be usable
     */
    public function theGiftCardIssuedToShouldBeUsable(CustomerInterface $customer): void
    {
        $this->giftCardManager->clear();

        $giftCards = $this->giftCardRepository->findByPurchaser($customer);
        Assert::count($giftCards, 1);
        Assert::true($giftCards[0]->isRedeemable());
    }

    /**
     * @Then the gift card issued to :customer should no longer be usable
     */
    public function theGiftCardIssuedToShouldNoLongerBeUsable(CustomerInterface $customer): void
    {
        $this->giftCardManager->clear();

        $giftCards = $this->giftCardRepository->findByPurchaser($customer);
        Assert::count($giftCards, 1);
        Assert::false($giftCards[0]->isRedeemable());
    }

    private static function toMinorUnits(string $amount): int
    {
        $normalised = preg_replace('/[^0-9.]/', '', $amount) ?? '';

        return (int) round(((float) $normalised) * 100);
    }
}
