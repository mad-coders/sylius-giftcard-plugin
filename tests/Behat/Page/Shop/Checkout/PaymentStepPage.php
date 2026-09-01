<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Checkout;

use Sylius\Behat\Page\Shop\Page as ShopPage;

/**
 * The payment step, as far as the gift card panel in its sidebar.
 *
 * Covered separately from the summary page because the two reach the panel by different routes: the
 * summary step blanks Sylius' sidebar block entirely and gets the panel through
 * `sylius_shop.checkout.complete.content`, while the addressing, shipping and payment steps share
 * `sylius_shop.checkout.common.sidebar.summary`. A change that broke one would leave the other
 * green.
 */
final class PaymentStepPage extends ShopPage implements PaymentStepPageInterface
{
    public function getRouteName(): string
    {
        return 'sylius_shop_checkout_select_payment';
    }

    public function hasGiftCardPanel(): bool
    {
        return $this->hasElement('gift_card_panel');
    }

    public function applyGiftCard(string $code): void
    {
        $this->getElement('gift_card_code_input')->setValue($code);
        $this->getElement('apply_gift_card_button')->press();
    }

    public function getAmountToPay(): string
    {
        return trim($this->getElement('amount_to_pay')->getText());
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'amount_to_pay' => '[data-test-checkout-amount-to-pay]',
            'apply_gift_card_button' => '[data-test-apply-gift-card-button]',
            'gift_card_code_input' => '[data-test-gift-card-code-input]',
            'gift_card_panel' => '[data-test-checkout-gift-card-panel]',
        ]);
    }
}
