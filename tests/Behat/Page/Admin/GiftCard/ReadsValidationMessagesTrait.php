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
 *
 * **Nothing here may depend on a Symfony 7 detail.** The plugin supports `^6.4 || ^7.4`, a local run
 * is 7.4, and a selector that only matches on 7 therefore looks perfectly green here and fails three
 * CI legs - as a *test* failure that reads like a product bug, because a selector matching nothing
 * is indistinguishable from a form that raised nothing. That is the trap this file fell into once
 * already, and `AdjustBalancePage` and `GiftCardConfiguration/CreatePage` before it.
 */
trait ReadsValidationMessagesTrait
{
    /**
     * How far out of a field to look for its row wrapper.
     *
     * Two is enough for everything this admin renders - a plain widget's errors are one level up,
     * and a money widget wrapped in an `input-group` puts them two - so three is headroom rather
     * than a guess. It is a bound and not a search: climbing until something is found would walk out
     * to the whole form and report a different field's error as this one's.
     */
    private const int FIELD_WRAPPER_SEARCH_DEPTH = 3;

    /**
     * Every validation message on the page, joined.
     *
     * Bootstrap's form theme gives a *field* error the `invalid-feedback` class and a form-level one
     * `alert alert-danger`, so this only ever collects errors that were rendered against a field.
     * The class is on both Symfony versions.
     */
    public function getValidationMessages(): string
    {
        return self::textOf($this->getDocument()->findAll('css', '.invalid-feedback'));
    }

    /**
     * The messages rendered against one named field.
     *
     * This is what turns "the form said something" into "the form said it about this field" - the
     * difference between a constraint that landed on `expiresAt` and one that missed its `atPath`
     * and landed at the top of the form. Worth keeping, and it has to be earned structurally.
     *
     * Symfony ties an error to its field with an `id` of `<field id>_errorN`, which would say it
     * outright - but the theme only emits that id from **Symfony 7.3**. On 6.4 the same error is a
     * bare `<div class="invalid-feedback d-block">` and any selector naming the id matches nothing.
     *
     * So the binding is read from the shape instead, which is the same on both: `form_row` renders
     * label, widget, help and errors as siblings inside one row wrapper, so a field's errors are the
     * `.invalid-feedback` elements inside the nearest wrapper that contains that field and no other.
     * Checked against 6.4's own `bootstrap_5_layout`, not assumed - its `form_row` emits
     * `<div class="mb-3">{label}{widget}{help}{errors}</div>` exactly as 7.4's does, and its
     * `form_errors` differs in nothing but the missing id.
     */
    public function getFieldValidationMessage(string $label): string
    {
        $field = $this->getDocument()->findField($label);

        if (null === $field) {
            return '';
        }

        // Climbs from the field, not from the page, because the error is the field's sibling rather
        // than anything a page-wide selector could tell apart.
        $wrapper = $field;

        for ($depth = 0; $depth < self::FIELD_WRAPPER_SEARCH_DEPTH; ++$depth) {
            $wrapper = $wrapper->getParent();

            // The moment an ancestor holds a second control we have climbed out of this field's row
            // and an `.invalid-feedback` inside it could belong to any of them. Answering nothing is
            // the honest answer: this method's whole job is to say the error is on *this* field.
            if (1 < count($wrapper->findAll('css', 'input, select, textarea'))) {
                return '';
            }

            $errors = $wrapper->findAll('css', '.invalid-feedback');

            if ([] !== $errors) {
                return self::textOf($errors);
            }
        }

        return '';
    }

    /** @param array<array-key, NodeElement> $elements */
    private static function textOf(array $elements): string
    {
        return implode(' ', array_map(
            static fn (NodeElement $element): string => trim($element->getText()),
            $elements,
        ));
    }
}
