<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Form\Type;

use Madcoders\SyliusGiftCardPlugin\Form\DataMapper\GiftCardAmountMapper;
use Madcoders\SyliusGiftCardPlugin\Form\Type\GiftCardAmountType;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardAmountMode;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * What the two controls a customer sees fold down to.
 *
 * The submitted values are the raw strings a browser sends, so this covers the same ground a
 * hand-written POST does: whatever arrives, the field either produces an amount the channel offers
 * or produces nothing for the validator to refuse.
 */
final class GiftCardAmountTypeTest extends TypeTestCase
{
    public function testAPresetsChannelOffersOneRadioPerPreset(): void
    {
        $form = $this->factory->create(GiftCardAmountType::class, null, self::options(self::presets()));

        $view = $form->createView();

        self::assertArrayHasKey('preset', $view->children);
        self::assertArrayNotHasKey('custom', $view->children, 'nothing to type in a presets-only channel');
        self::assertCount(2, $view->children['preset']->children);
    }

    public function testAPresetsAndRangeChannelOffersTheRadiosPlusAnOtherAmountBox(): void
    {
        $form = $this->factory->create(GiftCardAmountType::class, null, self::options(self::presetsAndRange()));

        $view = $form->createView();

        self::assertArrayHasKey('custom', $view->children);
        // Two presets plus the radio that means "I want to type my own".
        self::assertCount(3, $view->children['preset']->children);
    }

    public function testARangeChannelOffersOnlyTheBox(): void
    {
        $form = $this->factory->create(GiftCardAmountType::class, null, self::options(self::range()));

        $view = $form->createView();

        self::assertArrayNotHasKey('preset', $view->children);
        self::assertArrayHasKey('custom', $view->children);
    }

    public function testAChosenPresetBecomesTheAmount(): void
    {
        $form = $this->factory->create(GiftCardAmountType::class, null, self::options(self::presets()));

        $form->submit(['preset' => '10000']);

        self::assertSame(10000, $form->getData());
    }

    public function testATypedAmountBecomesTheAmount(): void
    {
        $form = $this->factory->create(GiftCardAmountType::class, null, self::options(self::range()));

        $form->submit(['custom' => '123.45']);

        self::assertSame(12345, $form->getData());
    }

    public function testTheOtherAmountRadioTakesTheTypedValue(): void
    {
        $form = $this->factory->create(GiftCardAmountType::class, null, self::options(self::presetsAndRange()));

        $form->submit(['preset' => GiftCardAmountMapper::CUSTOM_CHOICE, 'custom' => '77.00']);

        self::assertSame(7700, $form->getData());
    }

    public function testAPresetWinsOverAValueLeftInTheBox(): void
    {
        $form = $this->factory->create(GiftCardAmountType::class, null, self::options(self::presetsAndRange()));

        $form->submit(['preset' => '5000', 'custom' => '999.00']);

        self::assertSame(5000, $form->getData());
    }

    public function testAnAmountNobodyOfferedNeverBecomesAnAmount(): void
    {
        // The forged submission. A radio value that was never rendered has to fold to nothing, so
        // the validator refuses the field rather than the price quietly becoming 1 cent.
        $form = $this->factory->create(GiftCardAmountType::class, null, self::options(self::presets()));

        $form->submit(['preset' => '1']);

        self::assertNull($form->getData());
    }

    public function testAnExistingAmountComesBackSelected(): void
    {
        $form = $this->factory->create(GiftCardAmountType::class, 5000, self::options(self::presetsAndRange()));

        self::assertSame('5000', $form->get('preset')->getData());
        self::assertNull($form->get('custom')->getData());
    }

    public function testAnExistingFreeAmountComesBackInTheBoxWithOtherSelected(): void
    {
        $form = $this->factory->create(GiftCardAmountType::class, 7700, self::options(self::presetsAndRange()));

        self::assertSame(GiftCardAmountMapper::CUSTOM_CHOICE, $form->get('preset')->getData());
        self::assertSame(7700, $form->get('custom')->getData());
    }

    #[\Override]
    protected function getExtensions(): array
    {
        $moneyFormatter = $this->createMock(MoneyFormatterInterface::class);
        $moneyFormatter->method('format')->willReturnCallback(
            static fn (int $amount, string $currencyCode): string => $amount . ' ' . $currencyCode,
        );

        return [new PreloadedExtension([new GiftCardAmountType($moneyFormatter)], [])];
    }

    /** @return array<string, mixed> */
    private static function options(GiftCardConfiguration $configuration): array
    {
        return ['configuration' => $configuration, 'currency_code' => 'USD'];
    }

    private static function presets(): GiftCardConfiguration
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setAmountMode(GiftCardAmountMode::Presets);
        $configuration->setAmountPresets([5000, 10000]);

        return $configuration;
    }

    private static function range(): GiftCardConfiguration
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setAmountMode(GiftCardAmountMode::Range);
        $configuration->setMinimumAmount(1000);
        $configuration->setMaximumAmount(50000);

        return $configuration;
    }

    private static function presetsAndRange(): GiftCardConfiguration
    {
        $configuration = self::range();
        $configuration->setAmountMode(GiftCardAmountMode::PresetsAndRange);
        $configuration->setAmountPresets([5000, 10000]);

        return $configuration;
    }
}
