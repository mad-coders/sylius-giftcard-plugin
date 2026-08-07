<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardOrigin;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
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
    ) {
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
