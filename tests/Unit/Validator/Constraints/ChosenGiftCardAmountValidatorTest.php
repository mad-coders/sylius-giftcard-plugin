<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Validator\Constraints;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardAmountMode;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\ChosenGiftCardAmount;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\ChosenGiftCardAmountValidator;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Refusing an amount nobody offered.
 *
 * Exercised through the validator rather than through the shop form on purpose: the whole point of
 * this check is that it holds for any request, however the amount arrived.
 */
final class ChosenGiftCardAmountValidatorTest extends ConstraintValidatorTestCase
{
    private ?GiftCardConfigurationInterface $configuration = null;

    public function testItAcceptsAPresetTheChannelOffers(): void
    {
        $this->configuration = self::presets([2500, 5000]);

        $this->validator->validate(5000, new ChosenGiftCardAmount());

        $this->assertNoViolation();
    }

    public function testItRefusesAnAmountThatIsNotAPreset(): void
    {
        $this->configuration = self::presets([2500, 5000]);

        $this->validator->validate(4999, new ChosenGiftCardAmount());

        $this->buildViolation('madcoders_sylius_gift_card.cart_item.amount.not_a_preset')->assertRaised();
    }

    public function testItRefusesNothingBeingChosenAtAll(): void
    {
        $this->configuration = self::presets([2500, 5000]);

        $this->validator->validate(null, new ChosenGiftCardAmount());

        $this->buildViolation('madcoders_sylius_gift_card.cart_item.amount.required')->assertRaised();
    }

    public function testItAcceptsAFreeAmountWithinTheBounds(): void
    {
        $this->configuration = self::range(1000, 50000);

        $this->validator->validate(1000, new ChosenGiftCardAmount());

        $this->assertNoViolation();
    }

    public function testAnOutOfRangeAmountIsRefusedWithBothBoundsNamed(): void
    {
        // "Invalid amount" leaves the customer guessing. The message has to say what to type
        // instead, which is why it carries both bounds.
        $this->configuration = self::range(1000, 50000);

        $this->validator->validate(999, new ChosenGiftCardAmount());

        $this->buildViolation('madcoders_sylius_gift_card.cart_item.amount.out_of_range')
            ->setParameter('{{ minimum }}', '1000 USD')
            ->setParameter('{{ maximum }}', '50000 USD')
            ->assertRaised()
        ;
    }

    public function testAChannelSellingAtTheProductPriceRefusesNothingBecauseItAsksNothing(): void
    {
        // The field is not rendered in this mode, so there is no answer to judge. The processor is
        // what makes sure an amount arriving anyway is never honoured.
        $this->configuration = new GiftCardConfiguration();

        $this->validator->validate(4999, new ChosenGiftCardAmount());

        $this->assertNoViolation();
    }

    public function testAChannelWithNoGiftCardConfigurationRefusesNothing(): void
    {
        $this->configuration = null;

        $this->validator->validate(4999, new ChosenGiftCardAmount());

        $this->assertNoViolation();
    }

    public function testAHalfConfiguredRangeIsRefusedWithoutNamingBoundsItDoesNotHave(): void
    {
        $this->configuration = self::range(1000, null);

        $this->validator->validate(2000, new ChosenGiftCardAmount());

        $this->buildViolation('madcoders_sylius_gift_card.cart_item.amount.not_a_preset')->assertRaised();
    }

    protected function createValidator(): ChosenGiftCardAmountValidator
    {
        $currency = new Currency();
        $currency->setCode('USD');

        $channel = new Channel();
        $channel->setCode('WEB');
        $channel->setBaseCurrency($currency);

        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $localeContext = $this->createMock(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('en_US');

        $provider = $this->createMock(GiftCardConfigurationProviderInterface::class);
        $provider->method('getForChannel')->willReturnCallback(fn (): ?GiftCardConfigurationInterface => $this->configuration);

        // Formatting is somebody else's job; a predictable string keeps this test about the refusal
        // rather than about ICU's idea of a dollar sign.
        $moneyFormatter = $this->createMock(MoneyFormatterInterface::class);
        $moneyFormatter->method('format')->willReturnCallback(
            static fn (int $amount, string $currencyCode): string => $amount . ' ' . $currencyCode,
        );

        return new ChosenGiftCardAmountValidator($channelContext, $localeContext, $provider, $moneyFormatter);
    }

    /** @param list<int> $presets */
    private static function presets(array $presets): GiftCardConfiguration
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setAmountMode(GiftCardAmountMode::Presets);
        $configuration->setAmountPresets($presets);

        return $configuration;
    }

    private static function range(?int $minimum, ?int $maximum): GiftCardConfiguration
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setAmountMode(GiftCardAmountMode::Range);
        $configuration->setMinimumAmount($minimum);
        $configuration->setMaximumAmount($maximum);

        return $configuration;
    }
}
