<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\Product;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPage;

/**
 * Sylius' own admin product update page, only far enough to open it and save it unchanged.
 *
 * That is exactly the interaction that used to clear the gift card flag, so the test needs the
 * real form rather than a synthetic submit.
 */
final class UpdatePage extends SymfonyPage implements UpdatePageInterface
{
    public function getRouteName(): string
    {
        return 'sylius_admin_product_update';
    }

    public function saveUnchanged(): void
    {
        $this->getElement('save_button')->press();
    }

    public function isGiftCardChecked(): bool
    {
        return $this->getElement('gift_card_checkbox')->isChecked();
    }

    public function hasGiftCardField(): bool
    {
        return $this->hasElement('gift_card_checkbox');
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'gift_card_checkbox' => '[data-test-product-gift-card] input[type="checkbox"]',
            'save_button' => '[type="submit"]',
        ]);
    }
}
