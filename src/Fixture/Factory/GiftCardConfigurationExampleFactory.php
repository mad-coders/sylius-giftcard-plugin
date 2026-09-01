<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Fixture\Factory;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardAmountMode;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardSaleMode;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

/**
 * @implements ExampleFactoryInterface<GiftCardConfigurationInterface>
 */
class GiftCardConfigurationExampleFactory extends AbstractExampleFactory implements ExampleFactoryInterface
{
    private readonly OptionsResolver $optionsResolver;

    /**
     * @param FactoryInterface<GiftCardConfigurationInterface> $giftCardConfigurationFactory
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly FactoryInterface $giftCardConfigurationFactory,
        private readonly RepositoryInterface $channelRepository,
    ) {
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): GiftCardConfigurationInterface
    {
        $options = $this->optionsResolver->resolve($options);

        // Asserted rather than assumed - see the note in GiftCardExampleFactory::create().
        $channel = $options['channel'];
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $codeLength = $options['code_length'];
        Assert::integer($codeLength);

        $codePrefix = $options['code_prefix'];
        Assert::nullOrString($codePrefix);

        $validityPeriod = $options['validity_period'];
        Assert::nullOrString($validityPeriod);

        $enabled = $options['enabled'];
        Assert::boolean($enabled);

        $saleMode = $options['sale_mode'];
        Assert::string($saleMode);

        $amountMode = $options['amount_mode'];
        Assert::isInstanceOf($amountMode, GiftCardAmountMode::class);

        $amountPresets = $options['amount_presets'];
        Assert::isList($amountPresets);
        Assert::allInteger($amountPresets);

        $minimumAmount = $options['minimum_amount'];
        Assert::nullOrInteger($minimumAmount);

        $maximumAmount = $options['maximum_amount'];
        Assert::nullOrInteger($maximumAmount);

        $configuration = $this->giftCardConfigurationFactory->createNew();
        $configuration->setChannel($channel);
        $configuration->setCodeLength($codeLength);
        $configuration->setCodePrefix($codePrefix);
        $configuration->setValidityPeriod($validityPeriod);
        $configuration->setEnabled($enabled);
        $configuration->setSaleMode(GiftCardSaleMode::from($saleMode));
        $configuration->setAmountMode($amountMode);
        $configuration->setAmountPresets($amountPresets);
        $configuration->setMinimumAmount($minimumAmount);
        $configuration->setMaximumAmount($maximumAmount);

        return $configuration;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('channel', LazyOption::randomOne($this->channelRepository))
            ->setAllowedTypes('channel', ['string', ChannelInterface::class])
            ->setNormalizer('channel', LazyOption::findOneBy($this->channelRepository, 'code'))

            ->setDefault('code_length', GiftCardConfiguration::DEFAULT_CODE_LENGTH)
            ->setAllowedTypes('code_length', 'int')

            ->setDefault('code_prefix', null)
            ->setAllowedTypes('code_prefix', ['null', 'string'])

            ->setDefault('validity_period', '1 year')
            ->setAllowedTypes('validity_period', ['null', 'string'])

            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', 'bool')

            // Taken as the backing value rather than the case, so a YAML fixture can say
            // `sale_mode: admin_only`.
            ->setDefault('sale_mode', GiftCardSaleMode::Sellable->value)
            ->setAllowedValues('sale_mode', array_map(
                static fn (GiftCardSaleMode $mode): string => $mode->value,
                GiftCardSaleMode::cases(),
            ))

            // Fixed by default, so a fixture that says nothing about amounts describes a channel
            // that behaves exactly as it did before the customer could choose one.
            ->setDefault('amount_mode', GiftCardAmountMode::Fixed)
            ->setAllowedTypes('amount_mode', ['string', GiftCardAmountMode::class])
            ->setNormalizer(
                'amount_mode',
                static fn (Options $options, string|GiftCardAmountMode $mode): GiftCardAmountMode => $mode instanceof GiftCardAmountMode
                    ? $mode
                    : GiftCardAmountMode::from($mode),
            )

            ->setDefault('amount_presets', [])
            ->setAllowedTypes('amount_presets', 'int[]')

            ->setDefault('minimum_amount', null)
            ->setAllowedTypes('minimum_amount', ['null', 'int'])

            ->setDefault('maximum_amount', null)
            ->setAllowedTypes('maximum_amount', ['null', 'int'])
        ;
    }
}
