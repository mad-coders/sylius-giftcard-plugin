<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use Sylius\Behat\Page\SyliusPage;

final class ShowPage extends SyliusPage implements ShowPageInterface
{
    public function getRouteName(): string
    {
        return 'madcoders_sylius_gift_card_admin_gift_card_show';
    }

    public function getBalance(): string
    {
        return trim($this->getElement('balance')->getText());
    }

    public function getPurchaser(): string
    {
        return trim($this->getElement('purchaser')->getText());
    }

    public function getRedeemer(): string
    {
        return trim($this->getElement('redeemer')->getText());
    }

    public function countTransactions(): int
    {
        if (!$this->hasElement('transactions')) {
            return 0;
        }

        return count($this->getElement('transactions')->findAll('css', '[data-test-gift-card-transaction]'));
    }

    public function adjustBalance(): void
    {
        $this->getElement('adjust_balance_link')->click();
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'adjust_balance_link' => '[data-test-adjust-balance-link]',
            'balance' => '[data-test-gift-card-balance]',
            'purchaser' => '[data-test-gift-card-purchaser]',
            'redeemer' => '[data-test-gift-card-redeemer]',
            'transactions' => '[data-test-gift-card-transactions]',
        ]);
    }
}
