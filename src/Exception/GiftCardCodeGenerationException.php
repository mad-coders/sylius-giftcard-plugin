<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Exception;

final class GiftCardCodeGenerationException extends GiftCardException
{
    public static function exhausted(int $attempts, int $codeLength): self
    {
        return new self(sprintf(
            'Could not generate an unused gift card code in %d attempts at a length of %d characters. The code space is most likely too small for the number of cards issued; raise the configured code length.',
            $attempts,
            $codeLength,
        ));
    }
}
