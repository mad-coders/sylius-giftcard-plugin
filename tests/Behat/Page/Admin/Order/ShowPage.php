<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\Order;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPage;

/**
 * Sylius' admin order show page, only as far as the gift card lines the plugin adds to its summary.
 *
 * Deliberately not extending Sylius' own ShowPage: that class takes a TableAccessor and a good deal
 * else this needs none of, and the plugin only cares about three cells.
 */
final class ShowPage extends SymfonyPage implements ShowPageInterface
{
    public function getRouteName(): string
    {
        return 'sylius_admin_order_show';
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

    public function hasGiftCard(string $code): bool
    {
        return $this->hasElement('gift_card', ['%code%' => $code]);
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'amount_to_pay' => '[data-test-amount-to-pay]',
            'gift_card' => '[data-test-gift-card="%code%"]',
            'gift_card_total' => '[data-test-gift-card-total]',
        ]);
    }
}
