<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Account;

use Sylius\Behat\Page\Shop\Page as ShopPage;

final class GiftCardShowPage extends ShopPage implements GiftCardShowPageInterface
{
    public function getRouteName(): string
    {
        return 'madcoders_sylius_gift_card_shop_account_show';
    }

    public function getBalance(): string
    {
        return trim($this->getElement('balance')->getText());
    }

    public function getCustomMessage(): ?string
    {
        return $this->hasElement('custom_message') ? trim($this->getElement('custom_message')->getText()) : null;
    }

    public function countTransactions(): int
    {
        if (!$this->hasElement('transactions')) {
            return 0;
        }

        return count($this->getElement('transactions')->findAll('css', '[data-test-gift-card-transaction]'));
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'balance' => '[data-test-gift-card-balance]',
            'custom_message' => '[data-test-gift-card-custom-message]',
            'transactions' => '[data-test-gift-card-transactions]',
        ]);
    }
}
