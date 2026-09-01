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

    public function chooseAmount(string $label): void
    {
        foreach ($this->amountOptionLabels() as $option) {
            if (trim($option->getText()) !== $label) {
                continue;
            }

            // The label, not the input: the radio is visually hidden behind Bootstrap's `btn-check`,
            // and a real customer clicks the button they can see.
            $option->click();

            return;
        }

        throw new \InvalidArgumentException(sprintf('The page does not offer an amount labelled "%s".', $label));
    }

    public function specifyCustomAmount(string $amount): void
    {
        $this->fieldIn('gift_card_custom_amount', 'input')->setValue($amount);
    }

    public function specifyMessage(string $message): void
    {
        $this->fieldIn('gift_card_message', 'textarea')->setValue($message);
    }

    public function specifyMessageIgnoringTheBrowserLimit(string $message): void
    {
        // The textarea carries a `maxlength`, and a browser honours it - so setValue() alone can
        // only ever produce a message the server would accept, and would test nothing. Dropping the
        // attribute first makes this the case that matters: a client that ignores the hint, which is
        // every client that did not come from this form.
        $field = $this->fieldIn('gift_card_message', 'textarea');
        $this->getSession()->executeScript(sprintf(
            'document.querySelector("%s textarea").removeAttribute("maxlength");',
            '[data-test-gift-card-message]',
        ));

        $field->setValue($message);
    }

    public function addToCart(): void
    {
        $this->getElement('add_to_cart_button')->click();

        // The component either redirects the browser to the cart (a successful add) or re-renders
        // in place carrying the refusal. Waiting for whichever arrives is what makes the next
        // assertion look at the outcome rather than at the page as it was before the click.
        $this->getDocument()->waitFor(
            15,
            fn (): bool => $this->hasLeftTheProductPage() || '' !== $this->getValidationMessages(),
        );
    }

    private function hasLeftTheProductPage(): bool
    {
        return !str_contains($this->getSession()->getCurrentUrl(), '/products/');
    }

    public function getValidationMessages(): string
    {
        $messages = [];

        foreach ($this->getDocument()->findAll('css', '.invalid-feedback, .form-error-message') as $error) {
            $text = trim($error->getText());

            if ('' !== $text) {
                $messages[] = $text;
            }
        }

        return implode(' ', $messages);
    }

    private function fieldIn(string $element, string $tag): NodeElement
    {
        $field = $this->getElement($element)->find('css', $tag);

        if (null === $field) {
            throw new \RuntimeException(sprintf('No <%s> inside the "%s" element.', $tag, $element));
        }

        return $field;
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
            'add_to_cart_button' => '#add-to-cart-button',
        ]);
    }
}
