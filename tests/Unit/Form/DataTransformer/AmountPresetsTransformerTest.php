<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Form\DataTransformer;

use Madcoders\SyliusGiftCardPlugin\Form\DataTransformer\AmountPresetsTransformer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * The channel's preset amounts, as an admin types them and as the model stores them.
 *
 * Money is minor units on one side and major units on the other, which is exactly the kind of seam
 * where a factor of a hundred goes missing.
 */
final class AmountPresetsTransformerTest extends TestCase
{
    public function testItShowsMinorUnitsAsMajorUnits(): void
    {
        self::assertSame('25.00, 50.00, 100.00', (new AmountPresetsTransformer())->transform([2500, 5000, 10000]));
    }

    public function testItShowsNothingForAChannelWithNoPresets(): void
    {
        self::assertSame('', (new AmountPresetsTransformer())->transform([]));
        self::assertSame('', (new AmountPresetsTransformer())->transform(null));
    }

    public function testItReadsACommaSeparatedListAsMinorUnits(): void
    {
        self::assertSame([2500, 5000, 10000], (new AmountPresetsTransformer())->reverseTransform('25, 50, 100'));
    }

    public function testItReadsWholeAndFractionalAmounts(): void
    {
        self::assertSame([2500, 4999], (new AmountPresetsTransformer())->reverseTransform('25 49.99'));
    }

    public function testACommaOnlySeparatesAmounts(): void
    {
        // It cannot also be a decimal separator: "49,99" would then mean both one amount and two,
        // and the field would have no unambiguous reading at all.
        self::assertSame([4900, 9900], (new AmountPresetsTransformer())->reverseTransform('49,99'));
    }

    public function testAnEmptyFieldMeansNoPresets(): void
    {
        self::assertSame([], (new AmountPresetsTransformer())->reverseTransform('   '));
        self::assertSame([], (new AmountPresetsTransformer())->reverseTransform(null));
    }

    public function testItRefusesSomethingThatIsNotAnAmount(): void
    {
        $this->expectException(TransformationFailedException::class);

        (new AmountPresetsTransformer())->reverseTransform('25, fifty');
    }

    public function testItRefusesMorePrecisionThanMoneyHas(): void
    {
        // Silently rounding 25.005 would give the operator a channel offering an amount they never
        // typed.
        $this->expectException(TransformationFailedException::class);

        (new AmountPresetsTransformer())->reverseTransform('25.005');
    }
}
