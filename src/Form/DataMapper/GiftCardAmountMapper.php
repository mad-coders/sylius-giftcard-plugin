<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Form\DataMapper;

use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Folds the two controls a customer can be offered - a list of preset amounts and a free amount -
 * into the single integer the order item stores, and back again.
 *
 * Two controls rather than one because the alternative that needs no JavaScript is a text box with
 * the presets written next to it, which is not an offer, it is a hint. A radio group is an offer.
 */
final readonly class GiftCardAmountMapper implements DataMapperInterface
{
    /** The `preset` choice that means "I want to type my own amount". Never a valid money value. */
    public const string CUSTOM_CHOICE = 'custom';

    /** @param list<int> $presets minor units */
    public function __construct(private array $presets)
    {
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms): void
    {
        /** @var array<string, FormInterface> $children */
        $children = iterator_to_array($forms);

        // Symfony casts scalar model data to a string on the way into a form with no transformers,
        // so the amount arrives here as "5000" rather than 5000 when the item already has one.
        $amount = match (true) {
            is_int($viewData) => $viewData,
            is_string($viewData) && ctype_digit($viewData) => (int) $viewData,
            default => null,
        };

        $isPreset = null !== $amount && in_array($amount, $this->presets, true);

        if (isset($children['preset'])) {
            $children['preset']->setData(match (true) {
                $isPreset => (string) $amount,
                // An amount that is not a preset can only have come from the free-amount box, so
                // that is the radio that should come back selected.
                null !== $amount && isset($children['custom']) => self::CUSTOM_CHOICE,
                default => null,
            });
        }

        if (isset($children['custom'])) {
            $children['custom']->setData($isPreset ? null : $amount);
        }
    }

    public function mapFormsToData(\Traversable $forms, mixed &$viewData): void
    {
        /** @var array<string, FormInterface> $children */
        $children = iterator_to_array($forms);

        $custom = isset($children['custom']) ? $children['custom']->getData() : null;
        $custom = is_int($custom) ? $custom : null;

        if (!isset($children['preset'])) {
            $viewData = $custom;

            return;
        }

        $preset = $children['preset']->getData();

        if (self::CUSTOM_CHOICE === $preset) {
            $viewData = $custom;

            return;
        }

        // Anything else came from the choice list, whose values are the presets rendered as decimal
        // strings. A value that is not one of those was not offered, so it becomes "chose nothing"
        // and the validator refuses it - it never becomes a price.
        $viewData = is_string($preset) && in_array((int) $preset, $this->presets, true)
            ? (int) $preset
            : null;
    }
}
