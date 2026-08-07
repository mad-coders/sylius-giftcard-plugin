<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Exception;

/**
 * Base class for every gift card failure a caller might reasonably want to catch.
 */
abstract class GiftCardException extends \RuntimeException
{
    /**
     * Renders a code safely for an exception message.
     *
     * A gift card code is bearer money: whoever reads it can spend it. Exception messages reach
     * application logs and error trackers and stay there for months, so only enough of the code to
     * identify it goes in - never the whole thing.
     */
    protected static function maskCode(?string $code): string
    {
        if (null === $code || '' === $code) {
            return '(none)';
        }

        if (mb_strlen($code) <= 4) {
            return str_repeat('*', mb_strlen($code));
        }

        return str_repeat('*', mb_strlen($code) - 4) . mb_substr($code, -4);
    }
}
