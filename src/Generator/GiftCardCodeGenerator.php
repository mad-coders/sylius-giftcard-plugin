<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Generator;

use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardCodeGenerationException;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;

final readonly class GiftCardCodeGenerator implements GiftCardCodeGeneratorInterface
{
    /**
     * Deliberately excludes the letters people confuse when reading a code off a card or an email -
     * O, I, L and S - which leaves 0, 1 and 5 unambiguous, so the digits stay. A code nobody can
     * type back in is worse than a slightly shorter one.
     */
    private const string ALPHABET = '23456789ABCDEFGHJKMNPQRTUVWXYZ';

    private const int MAX_ATTEMPTS = 10;

    public function __construct(private GiftCardRepositoryInterface $giftCardRepository)
    {
    }

    public function generate(?GiftCardConfigurationInterface $configuration = null): string
    {
        $codeLength = $configuration?->getCodeLength() ?? GiftCardConfiguration::DEFAULT_CODE_LENGTH;
        // Same floor the configuration enforces, applied again here because the generator is
        // public API and can be called with a configuration a host built by hand.
        $codeLength = max(GiftCardConfiguration::MINIMUM_CODE_LENGTH, $codeLength);
        $prefix = $configuration?->getCodePrefix() ?? '';

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; ++$attempt) {
            $code = $prefix . $this->randomString($codeLength);

            if (!$this->giftCardRepository->codeExists($code)) {
                return $code;
            }
        }

        // Retrying forever would hang the request that is trying to issue a card. Failing loudly
        // points at the actual cause, which is always a code space too small for the shop's volume.
        throw GiftCardCodeGenerationException::exhausted(self::MAX_ATTEMPTS, $codeLength);
    }

    private function randomString(int $length): string
    {
        $alphabetLastIndex = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < $length; ++$i) {
            // random_int(), not rand(): a guessable gift card code is a way to spend other
            // people's money.
            $code .= self::ALPHABET[random_int(0, $alphabetLastIndex)];
        }

        return $code;
    }
}
