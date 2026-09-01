<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Product;

use Behat\Mink\Element\NodeElement;
use FriendsOfBehat\PageObjectExtension\Page\SymfonyPage;

/**
 * A gift card product's page in the shop.
 *
 * Only the fields the plugin contributes: the amount choice and the message. Everything else on the
 * page is Sylius'.
 */
final class ShowPage extends SymfonyPage implements ShowPageInterface
{
    public function getRouteName(): string
    {
        return 'sylius_shop_product_show';
    }

    public function hasAmountChoice(): bool
    {
        return $this->hasElement('gift_card_amount');
    }

    public function getAmountOptions(): array
    {
        return array_map(
            static fn (NodeElement $label): string => trim($label->getText()),
            $this->amountOptionLabels(),
        );
    }

    public function amountOptionsAreSelectable(): bool
    {
        $labels = $this->amountOptionLabels();

        if ([] === $labels) {
            return false;
        }

        foreach ($labels as $label) {
            $id = $label->getAttribute('for');

            // A dropdown's <option> has no companion input; a radio does, and that is what makes the
            // amounts selectable options rather than a list of numbers.
            if (null === $id || null === $this->getDocument()->find('css', sprintf('input[type="radio"]#%s', $id))) {
                return false;
            }
        }

        return true;
    }

    public function hasFreeAmountField(): bool
    {
        return $this->hasElement('gift_card_custom_amount');
    }

    public function getFreeAmountHelp(): string
    {
        $help = $this->getElement('gift_card_custom_amount')->find('css', '.form-text');

        return null === $help ? '' : trim($help->getText());
    }

    public function hasMessageField(): bool
    {
        return $this->hasElement('gift_card_message');
    }

    public function getMessageMaxLength(): ?int
    {
        $textarea = $this->getElement('gift_card_message')->find('css', 'textarea');

        if (null === $textarea) {
            return null;
        }

        $maxLength = $textarea->getAttribute('maxlength');

        return null === $maxLength ? null : (int) $maxLength;
    }

    public function getMessageHelp(): string
    {
        $help = $this->getElement('gift_card_message')->find('css', '.form-text');

        return null === $help ? '' : trim($help->getText());
    }

    /** @return list<NodeElement> */
    private function amountOptionLabels(): array
    {
        if (!$this->hasElement('gift_card_amount')) {
            return [];
        }

        return array_values($this->getElement('gift_card_amount')->findAll('css', '[data-test-gift-card-amount-option]'));
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'gift_card_amount' => '[data-test-gift-card-amount]',
            'gift_card_custom_amount' => '[data-test-gift-card-custom-amount]',
            'gift_card_message' => '[data-test-gift-card-message]',
        ]);
    }
}
