<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Exception;

use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * A gift card code is bearer money, and exception messages end up in logs and error trackers that
 * are retained for months. Enough of the code to identify it, never enough to spend it.
 */
final class GiftCardExceptionTest extends TestCase
{
    public function testItDoesNotPutASpendableCodeInTheMessage(): void
    {
        $exception = new GiftCardNotFoundException('GIFT-SPENDABLE-1234');

        self::assertStringNotContainsString('GIFT-SPENDABLE-1234', $exception->getMessage());
        self::assertStringContainsString('1234', $exception->getMessage(), 'the tail identifies it');
    }

    public function testItStillExposesTheCodeToCallersThatNeedIt(): void
    {
        // The masking is about the message, not the data - a caller catching this may legitimately
        // need the code, for instance to re-prompt the customer.
        $exception = new GiftCardNotFoundException('GIFT-SPENDABLE-1234');

        self::assertSame('GIFT-SPENDABLE-1234', $exception->getGiftCardCode());
    }

    public function testAShortCodeIsMaskedEntirely(): void
    {
        $exception = new GiftCardNotFoundException('ABCD');

        self::assertStringNotContainsString('ABCD', $exception->getMessage());
    }
}
