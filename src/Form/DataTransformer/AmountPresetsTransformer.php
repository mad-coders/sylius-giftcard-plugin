<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * The channel's preset amounts as one line of text: "25, 50, 100" on screen, minor units in the
 * model.
 *
 * A text field rather than a collection of money rows because a collection needs JavaScript to add
 * and remove rows, and the admin has to work without it - the same reason the validity period is a
 * text expression rather than a date builder.
 *
 * Major units are read at two decimal places, matching Sylius' own admin money fields, which are
 * fixed at a divisor of 100.
 *
 * @implements DataTransformerInterface<list<int>, string>
 */
final readonly class AmountPresetsTransformer implements DataTransformerInterface
{
    private const int DIVISOR = 100;

    public function transform(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        return implode(', ', array_map(
            static fn (int $amount): string => number_format($amount / self::DIVISOR, 2, '.', ''),
            $value,
        ));
    }

    /** @return list<int> */
    public function reverseTransform(mixed $value): array
    {
        if (null === $value || '' === trim($value)) {
            return [];
        }

        $parts = preg_split('/[,;\s]+/', trim($value));

        if (false === $parts) {
            throw new TransformationFailedException('The preset amounts could not be read.');
        }

        $presets = [];

        foreach ($parts as $part) {
            if ('' === $part) {
                continue;
            }

            // A dot is the only decimal separator this field can have: the comma is already the
            // separator *between* amounts, and "49,99" cannot mean both one amount and two. At most
            // two decimal places, because silently rounding 25.005 would leave the channel offering
            // an amount nobody typed.
            if (1 !== preg_match('/^\d+(\.\d{1,2})?$/', $part)) {
                throw new TransformationFailedException(sprintf('"%s" is not an amount.', $part));
            }

            $preset = (int) round(((float) $part) * self::DIVISOR);

            // Refused, not quietly dropped. The model drops it too, as a backstop, but an operator
            // who typed a worthless preset has to be told - the same standard the code length is
            // held to, and for the same reason: silently saving something other than what was asked
            // for leaves them believing their channel offers something it does not.
            if ($preset <= 0) {
                throw new TransformationFailedException(sprintf('"%s" is not worth anything.', $part));
            }

            $presets[] = $preset;
        }

        return $presets;
    }
}
