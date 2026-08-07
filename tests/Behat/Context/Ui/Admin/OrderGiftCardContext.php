<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Sylius\Component\Core\Model\OrderInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\Order\ShowPageInterface;
use Webmozart\Assert\Assert;

/**
 * What an administrator sees on an order that was part-paid with a gift card.
 *
 * The order total stays at the full value of the goods, so without the plugin's summary lines there
 * is nothing on the page explaining why the payment is smaller - see
 * docs/adr-log/0010-gift-card-as-tender.md.
 */
final readonly class OrderGiftCardContext implements Context
{
    public function __construct(private ShowPageInterface $orderShowPage)
    {
    }

    /**
     * @When I view the order :order in the admin panel
     */
    public function iViewTheOrderInTheAdminPanel(OrderInterface $order): void
    {
        $this->orderShowPage->open(['id' => $order->getId()]);
    }

    /**
     * @Then it should show :amount covered by gift cards
     */
    public function itShouldShowCoveredByGiftCards(string $amount): void
    {
        Assert::same($this->orderShowPage->getGiftCardTotal(), $amount);
    }

    /**
     * @Then it should show :amount left to pay
     */
    public function itShouldShowLeftToPay(string $amount): void
    {
        Assert::same($this->orderShowPage->getAmountToPay(), $amount);
    }

    /**
     * @Then it should name the gift card :code that paid for it
     */
    public function itShouldNameTheGiftCardThatPaidForIt(string $code): void
    {
        // The code is the only handle an administrator has for tracing a balance back to the order
        // that spent it, so a total on its own is not enough.
        Assert::true(
            $this->orderShowPage->hasGiftCard($code),
            sprintf('The order page does not mention the gift card "%s".', $code),
        );
    }

    /**
     * @Then it should say nothing about gift cards
     */
    public function itShouldSayNothingAboutGiftCards(): void
    {
        Assert::false(
            $this->orderShowPage->hasGiftCardTotal(),
            'An order paid without a gift card should not carry gift card lines.',
        );
    }
}
