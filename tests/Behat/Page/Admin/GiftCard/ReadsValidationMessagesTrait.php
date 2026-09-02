<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use Behat\Mink\Element\NodeElement;

/**
 * Reading validation messages off an admin form, shared by the create and update pages.
 *
 * Both pages need the same two readings and they are the same DOM, so they are written once: the
 * create and update templates are the same form type rendered by the same theme, and a helper that
 * drifted between them would let a bug hide on whichever page was checked less carefully.
 */
trait ReadsValidationMessagesTrait
{
    /**
     * Every validation message on the page, joined.
     *
     * Bootstrap's form theme gives a *field* error the `invalid-feedback` class and a form-level one
     * `alert alert-danger`, so this only ever collects errors that were rendered against a field.
     */
    public function getValidationMessages(): string
    {
        return implode(' ', array_map(
            static fn (NodeElement $error): string => trim($error->getText()),
            $this->getDocument()->findAll('css', '.invalid-feedback'),
        ));
    }

    /**
     * The messages rendered against one named field.
     *
     * The theme gives each error the id of its field plus `_errorN`, which is the only thing that
     * ties the two together in the markup. Matching on it is what turns "the form said something"
     * into "the form said it about this field" - the difference between a constraint that landed on
     * `expiresAt` and one that missed and landed at the top of the form.
     */
    public function getFieldValidationMessage(string $label): string
    {
        $field = $this->getDocument()->findField($label);
        $id = $field?->getAttribute('id');

        if (null === $id) {
            return '';
        }

        return implode(' ', array_map(
            static fn (NodeElement $error): string => trim($error->getText()),
            $this->getDocument()->findAll('css', sprintf('.invalid-feedback[id^="%s_error"]', $id)),
        ));
    }
}
