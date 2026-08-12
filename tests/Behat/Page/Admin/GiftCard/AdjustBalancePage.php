<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPage;

/**
 * Extends the page-object-extension base directly rather than a Sylius page class: Sylius renamed
 * Sylius\\Behat\\Page\\SymfonyPage to SyliusPage after 2.0, so neither name exists across the whole
 * supported range. This base does, and it is what Sylius' own page classes extend.
 */
final class AdjustBalancePage extends SymfonyPage implements AdjustBalancePageInterface
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

    public function getValidationMessage(): string
    {
        return trim($this->getElement('validation_error')->getText());
    }

    public function hasValidationMessage(): bool
    {
        return $this->hasElement('validation_error');
    }

    public function getCurrentBalance(): string
    {
        return trim($this->getElement('current_balance')->getText());
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'current_balance' => '[data-test-current-balance]',
            // Bootstrap 5's form theme, which this page asks for explicitly, wraps a field error
            // in `.invalid-feedback`. Matched on the class rather than the element id Symfony
            // gives it: that id only appears from Symfony 7, and the plugin supports 6.4 too.
            'validation_error' => '.invalid-feedback',
            'submit' => '[data-test-adjust-balance-button]',
        ]);
    }
}
