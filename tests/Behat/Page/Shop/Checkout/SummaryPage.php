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

    public function hasGiftCardPanel(): bool
    {
        return $this->hasElement('gift_card_panel');
    }

    public function applyGiftCard(string $code): void
    {
        $this->getElement('gift_card_code_input')->setValue($code);
        $this->getElement('apply_gift_card_button')->press();
    }

    public function removeGiftCard(string $code): void
    {
        foreach ($this->getAppliedGiftCardRows() as $row) {
            $codeElement = $row->find('css', '[data-test-applied-gift-card-code]');

            if (null !== $codeElement && trim($codeElement->getText()) === $code) {
                $row->find('css', '[data-test-remove-gift-card]')?->press();

                return;
            }
        }

        throw new \InvalidArgumentException(sprintf('There is no applied gift card "%s" in the checkout.', $code));
    }

    /** @return list<string> */
    public function getAppliedGiftCardCodes(): array
    {
        $codes = [];

        foreach ($this->getAppliedGiftCardRows() as $row) {
            $codeElement = $row->find('css', '[data-test-applied-gift-card-code]');

            if (null !== $codeElement) {
                $codes[] = trim($codeElement->getText());
            }
        }

        return $codes;
    }

    public function confirmOrder(): void
    {
        $this->getElement('confirm_button')->press();
    }

    public function getValidationErrors(): string
    {
        return $this->hasElement('validation_errors') ? trim($this->getElement('validation_errors')->getText()) : '';
    }

    /** @return list<\Behat\Mink\Element\NodeElement> */
    private function getAppliedGiftCardRows(): array
    {
        if (!$this->hasElement('applied_gift_cards')) {
            return [];
        }

        return array_values($this->getElement('applied_gift_cards')->findAll('css', '[data-test-applied-gift-card]'));
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'amount_to_pay' => '[data-test-checkout-amount-to-pay]',
            'applied_gift_cards' => '[data-test-applied-gift-cards]',
            // Sylius' own selectors on its own summary template, so the plugin reaches the confirm
            // button and the checkout-complete violations without owning that page.
            'confirm_button' => '[data-test-button="confirmation-button"]',
            'validation_errors' => '[data-test-validation-error]',
            'apply_gift_card_button' => '[data-test-apply-gift-card-button]',
            'gift_card_code_input' => '[data-test-gift-card-code-input]',
            'gift_card_panel' => '[data-test-checkout-gift-card-panel]',
            'gift_card_total' => '[data-test-checkout-gift-card-total]',
        ]);
    }
}
