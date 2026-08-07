<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Checkout;

use Sylius\Behat\Page\Shop\Page as ShopPage;

/**
 * The checkout summary, as far as the gift card lines the plugin adds to its sidebar.
 *
 * The sidebar is the last place a customer sees a number before they pay, and the order total shown
 * there is the full price of the goods - so if these lines are missing, the customer is told to
 * expect a charge much larger than the one that will actually reach their card.
 */
final class SummaryPage extends ShopPage implements SummaryPageInterface
{
    public function getRouteName(): string
    {
        return 'sylius_shop_checkout_complete';
    }

    public function getGiftCardTotal(): string
    {
        return trim($this->getElement('gift_card_total')->getText());
    }

    public function getAmountToPay(): string
    {
        return trim($this->getElement('amount_to_pay')->getText());
    }

    public function hasGiftCardTotal(): bool
    {
        return $this->hasElement('gift_card_total');
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'amount_to_pay' => '[data-test-checkout-amount-to-pay]',
            'gift_card_total' => '[data-test-checkout-gift-card-total]',
        ]);
    }
}
