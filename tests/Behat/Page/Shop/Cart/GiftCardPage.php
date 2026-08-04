<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Cart;

use Behat\Mink\Element\NodeElement;
use Sylius\Behat\Page\Shop\Page as ShopPage;

/**
 * The gift card panel on the cart summary page.
 */
final class GiftCardPage extends ShopPage implements GiftCardPageInterface
{
    public function getRouteName(): string
    {
        return 'sylius_shop_cart_summary';
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
                $removeLink = $row->find('css', '[data-test-remove-gift-card]');

                if (null !== $removeLink) {
                    $removeLink->click();

                    return;
                }
            }
        }

        throw new \InvalidArgumentException(sprintf('There is no applied gift card with code "%s" on the cart.', $code));
    }

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

    public function getGiftCardBalance(string $code): string
    {
        foreach ($this->getAppliedGiftCardRows() as $row) {
            $codeElement = $row->find('css', '[data-test-applied-gift-card-code]');

            if (null !== $codeElement && trim($codeElement->getText()) === $code) {
                $balanceElement = $row->find('css', '[data-test-applied-gift-card-balance]');

                if (null !== $balanceElement) {
                    return trim($balanceElement->getText());
                }
            }
        }

        throw new \InvalidArgumentException(sprintf('There is no applied gift card with code "%s" on the cart.', $code));
    }

    public function getGiftCardTotal(): string
    {
        return trim($this->getElement('gift_card_total')->getText());
    }

    public function hasGiftCardTotal(): bool
    {
        return $this->hasElement('gift_card_total');
    }

    public function getOrderTotal(): string
    {
        return trim($this->getElement('order_total')->getText());
    }

    /** @return array<array-key, NodeElement> */
    private function getAppliedGiftCardRows(): array
    {
        if (!$this->hasElement('applied_gift_cards')) {
            return [];
        }

        return $this->getElement('applied_gift_cards')->findAll('css', '[data-test-applied-gift-card]');
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'applied_gift_cards' => '[data-test-applied-gift-cards]',
            'apply_gift_card_button' => '[data-test-apply-gift-card-button]',
            'gift_card_code_input' => '[data-test-gift-card-code-input]',
            'gift_card_panel' => '[data-test-gift-card-panel]',
            'gift_card_total' => '[data-test-cart-gift-card-total]',
            'order_total' => '[data-test-cart-grand-total]',
        ]);
    }
}
