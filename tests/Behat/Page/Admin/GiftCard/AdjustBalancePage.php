<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use Sylius\Behat\Page\SyliusPage;

final class AdjustBalancePage extends SyliusPage implements AdjustBalancePageInterface
{
    public function getRouteName(): string
    {
        return 'madcoders_sylius_gift_card_admin_gift_card_adjust_balance';
    }

    public function adjust(string $direction, string $amount): void
    {
        $this->getDocument()->fillField('Amount', $amount);

        // The direction is an expanded choice (radio buttons). It has to be selected by field name
        // and value: a radio group has no label Mink can resolve, and the BrowserKit driver cannot
        // click a radio input directly.
        $this->getDocument()->selectFieldOption(
            'madcoders_sylius_gift_card_adjust_balance[direction]',
            $direction,
        );

        $this->getElement('submit')->press();
    }

    public function getCurrentBalance(): string
    {
        return trim($this->getElement('current_balance')->getText());
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'current_balance' => '[data-test-current-balance]',
            'submit' => '[data-test-adjust-balance-button]',
        ]);
    }
}
