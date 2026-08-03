<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Generator;

use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardCodeGenerationException;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;

interface GiftCardCodeGeneratorInterface
{
    /**
     * Generates a code that is not already in use.
     *
     * @throws GiftCardCodeGenerationException if no
     *         unused code could be produced
     */
    public function generate(?GiftCardConfigurationInterface $configuration = null): string;
}
