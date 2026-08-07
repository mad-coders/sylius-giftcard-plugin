<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Generator;

use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardCodeGenerationException;
use Madcoders\SyliusGiftCardPlugin\Generator\GiftCardCodeGenerator;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * The generator decides how guessable a gift card code is, which decides whether the shop can be
 * drained by a script. It had no direct test.
 */
final class GiftCardCodeGeneratorTest extends TestCase
{
    public function testItGeneratesACodeOfTheConfiguredLengthWithThePrefix(): void
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setCodeLength(16);
        $configuration->setCodePrefix('GIFT-');

        $code = $this->createGenerator()->generate($configuration);

        self::assertStringStartsWith('GIFT-', $code);
        self::assertSame(16, mb_strlen(substr($code, mb_strlen('GIFT-'))));
    }

    public function testItRefusesToGenerateAGuessablyShortCode(): void
    {
        // A misconfigured length is not visible until the codes are already issued, so the floor is
        // enforced here as well as in the form - the generator is public API.
        $configuration = new GiftCardConfiguration();
        $configuration->setCodeLength(2);

        $code = $this->createGenerator()->generate($configuration);

        self::assertGreaterThanOrEqual(GiftCardConfiguration::MINIMUM_CODE_LENGTH, mb_strlen($code));
    }

    public function testItFallsBackToTheDefaultLengthWithoutAConfiguration(): void
    {
        $code = $this->createGenerator()->generate();

        self::assertSame(GiftCardConfiguration::DEFAULT_CODE_LENGTH, mb_strlen($code));
    }

    public function testItAvoidsCharactersPeopleMisreadOffACard(): void
    {
        $generator = $this->createGenerator();

        // O, I, L and S are the ones people misread; dropping them leaves 0, 1 and 5 unambiguous,
        // so the digits are kept rather than shrinking the alphabet further.
        for ($i = 0; $i < 50; ++$i) {
            self::assertDoesNotMatchRegularExpression('/[OILS]/', $generator->generate());
        }
    }

    public function testItRetriesWhenTheGeneratedCodeIsAlreadyTaken(): void
    {
        $repository = $this->createMock(GiftCardRepositoryInterface::class);
        $repository
            ->method('codeExists')
            // Taken twice, free on the third attempt.
            ->willReturnOnConsecutiveCalls(true, true, false)
        ;

        $code = (new GiftCardCodeGenerator($repository))->generate();

        self::assertNotSame('', $code);
    }

    public function testItGivesUpLoudlyRatherThanLoopingForeverWhenTheCodeSpaceIsExhausted(): void
    {
        // Retrying forever would hang the request trying to issue a card. Failing names the actual
        // cause, which is always a code space too small for the shop's volume.
        $repository = $this->createMock(GiftCardRepositoryInterface::class);
        $repository->method('codeExists')->willReturn(true);

        $this->expectException(GiftCardCodeGenerationException::class);

        (new GiftCardCodeGenerator($repository))->generate();
    }

    public function testItDoesNotProduceTheSameCodeTwice(): void
    {
        $generator = $this->createGenerator();

        $codes = [];
        for ($i = 0; $i < 200; ++$i) {
            $codes[] = $generator->generate();
        }

        self::assertCount(200, array_unique($codes), 'generated codes should not collide');
    }

    private function createGenerator(): GiftCardCodeGenerator
    {
        $repository = $this->createMock(GiftCardRepositoryInterface::class);
        $repository->method('codeExists')->willReturn(false);

        return new GiftCardCodeGenerator($repository);
    }
}
