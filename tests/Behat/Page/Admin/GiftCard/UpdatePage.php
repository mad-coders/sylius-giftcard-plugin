<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use Sylius\Behat\Page\Admin\Crud\UpdatePage as BaseUpdatePage;

final class UpdatePage extends BaseUpdatePage implements UpdatePageInterface
{
    public function isCodeEditable(): bool
    {
        $field = $this->getDocument()->findField('Code');

        if (null === $field) {
            // Absent is also not editable, but a disappeared field is a different bug from a locked
            // one, so it is worth failing loudly rather than passing quietly.
            return true;
        }

        return !$field->hasAttribute('disabled') && !$field->hasAttribute('readonly');
    }

    public function getCode(): string
    {
        $field = $this->getDocument()->findField('Code');

        if (null === $field) {
            return '';
        }

        // Mink types a field's value as array|bool|string to cover checkboxes and multi-selects;
        // a text input is always a string.
        $value = $field->getValue();

        return is_string($value) ? $value : '';
    }

    public function isInitialAmountEditable(): bool
    {
        return null !== $this->getDocument()->findField('Initial amount');
    }
}
