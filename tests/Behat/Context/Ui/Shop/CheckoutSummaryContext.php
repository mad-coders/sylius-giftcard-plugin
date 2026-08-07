<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Checkout\SummaryPageInterface;
use Webmozart\Assert\Assert;

/**
 * What a customer is told they will be charged, on the last page before they pay.
 */
final readonly class CheckoutSummaryContext implements Context
{
    public function __construct(private SummaryPageInterface $summaryPage)
    {
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
}
