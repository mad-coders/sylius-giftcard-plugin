<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Validator\Constraints;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Judges a chosen gift card amount against the channel the customer is shopping in.
 *
 * The decision itself belongs to the configuration ({@see GiftCardConfigurationInterface::isAllowedAmount()});
 * this only works out *which* refusal to show, so that a customer who typed 5 into a 10-to-500
 * channel is told the bounds rather than "invalid".
 */
final class ChosenGiftCardAmountValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        private readonly GiftCardConfigurationProviderInterface $giftCardConfigurationProvider,
        private readonly MoneyFormatterInterface $moneyFormatter,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ChosenGiftCardAmount) {
            throw new UnexpectedTypeException($constraint, ChosenGiftCardAmount::class);
        }

        $channel = $this->currentChannel();
        if (null === $channel) {
            return;
        }

        $configuration = $this->giftCardConfigurationProvider->getForChannel($channel);

        // No configuration, or a channel that sells gift cards at the product's price: there is
        // nothing for the customer to choose, so there is nothing to refuse either. The field is not
        // rendered in that case, and the processor discards any amount that arrives anyway.
        if (null === $configuration || !$configuration->allowsCustomerChosenAmount()) {
            return;
        }

        if (null === $value) {
            $this->context->buildViolation($constraint->missingMessage)->addViolation();

            return;
        }

        if (!is_int($value)) {
            throw new UnexpectedTypeException($value, 'int');
        }

        if ($configuration->isAllowedAmount($value)) {
            return;
        }

        $this->refuse($constraint, $configuration, $channel);
    }

    private function refuse(
        ChosenGiftCardAmount $constraint,
        GiftCardConfigurationInterface $configuration,
        ChannelInterface $channel,
    ): void {
        $minimum = $configuration->getMinimumAmount();
        $maximum = $configuration->getMaximumAmount();

        // Only a channel that actually offers a free amount, and knows both its bounds, can name
        // them. Anything else is a presets-only refusal, including a range channel left half
        // configured - naming a bound that does not exist would be worse than not naming one.
        if (!$configuration->getAmountMode()->offersFreeAmount() || null === $minimum || null === $maximum) {
            $this->context->buildViolation($constraint->notAPresetMessage)->addViolation();

            return;
        }

        $currencyCode = $channel->getBaseCurrency()?->getCode() ?? '';

        $this->context->buildViolation($constraint->outOfRangeMessage)
            ->setParameter('{{ minimum }}', $this->format($minimum, $currencyCode))
            ->setParameter('{{ maximum }}', $this->format($maximum, $currencyCode))
            ->addViolation()
        ;
    }

    private function format(int $amount, string $currencyCode): string
    {
        return $this->moneyFormatter->format($amount, $currencyCode, $this->currentLocale());
    }

    private function currentChannel(): ?ChannelInterface
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return null;
        }

        return $channel instanceof ChannelInterface ? $channel : null;
    }

    private function currentLocale(): ?string
    {
        try {
            return $this->localeContext->getLocaleCode();
        } catch (LocaleNotFoundException) {
            // The formatter falls back to the system locale, which is a better answer than failing
            // to tell the customer what the bounds are.
            return null;
        }
    }
}
