<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Account;

use Behat\Mink\Element\NodeElement;
use Sylius\Behat\Page\Shop\Page as ShopPage;

final class GiftCardIndexPage extends ShopPage implements GiftCardIndexPageInterface
{
    public function getRouteName(): string
    {
        return 'madcoders_sylius_gift_card_shop_account_index';
    }

    public function getRedeemedGiftCardCodes(): array
    {
        return $this->codesIn('redeemed_gift_cards', 'redeemed-gift-card');
    }

    public function getPurchasedGiftCardCodes(): array
    {
        return $this->codesIn('purchased_gift_cards', 'purchased-gift-card');
    }

    public function getBalanceOf(string $code): string
    {
        foreach ($this->rowsIn('redeemed_gift_cards', 'redeemed-gift-card') as $row) {
            $codeElement = $row->find('css', '[data-test-gift-card-code]');

            if (null !== $codeElement && trim($codeElement->getText()) === $code) {
                $balance = $row->find('css', '[data-test-gift-card-balance]');

                if (null !== $balance) {
                    return trim($balance->getText());
                }
            }
        }

        throw new \InvalidArgumentException(sprintf('There is no gift card "%s" in the list of cards you use.', $code));
    }

    public function openBalanceHistoryOf(string $code): void
    {
        foreach ($this->rowsIn('redeemed_gift_cards', 'redeemed-gift-card') as $row) {
            $codeElement = $row->find('css', '[data-test-gift-card-code]');

            if (null !== $codeElement && trim($codeElement->getText()) === $code) {
                $link = $row->find('css', '[data-test-show-gift-card]');

                if (null !== $link) {
                    $link->click();

                    return;
                }
            }
        }

        throw new \InvalidArgumentException(sprintf('There is no gift card "%s" to open.', $code));
    }

    /** @return list<string> */
    private function codesIn(string $section, string $rowAttribute): array
    {
        $codes = [];

        foreach ($this->rowsIn($section, $rowAttribute) as $row) {
            $codeElement = $row->find('css', '[data-test-gift-card-code]');

            if (null !== $codeElement) {
                $codes[] = trim($codeElement->getText());
            }
        }

        return $codes;
    }

    /** @return array<array-key, NodeElement> */
    private function rowsIn(string $section, string $rowAttribute): array
    {
        if (!$this->hasElement($section)) {
            return [];
        }

        return $this->getElement($section)->findAll('css', sprintf('[data-test-%s]', $rowAttribute));
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'purchased_gift_cards' => '[data-test-purchased-gift-cards]',
            'redeemed_gift_cards' => '[data-test-redeemed-gift-cards]',
        ]);
    }
}
