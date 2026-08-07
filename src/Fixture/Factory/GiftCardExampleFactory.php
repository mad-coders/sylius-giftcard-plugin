<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Fixture\Factory;

use Madcoders\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardOrigin;
use Madcoders\SyliusGiftCardPlugin\Modifier\GiftCardBalanceModifierInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

/**
 * @implements ExampleFactoryInterface<GiftCardInterface>
 */
class GiftCardExampleFactory extends AbstractExampleFactory implements ExampleFactoryInterface
{
    private readonly OptionsResolver $optionsResolver;

    /**
     * @param FactoryInterface<GiftCardInterface> $giftCardFactory
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     * @param RepositoryInterface<CustomerInterface> $customerRepository
     */
    public function __construct(
        private readonly FactoryInterface $giftCardFactory,
        private readonly RepositoryInterface $channelRepository,
        private readonly RepositoryInterface $customerRepository,
        private readonly GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        private readonly GiftCardConfigurationProviderInterface $giftCardConfigurationProvider,
        private readonly GiftCardBalanceModifierInterface $giftCardBalanceModifier,
    ) {
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): GiftCardInterface
    {
        $options = $this->optionsResolver->resolve($options);

        // Fixture options come from hand-written YAML, so the values are asserted rather than
        // assumed: a typo in a fixture file should say which option is wrong, not surface later as
        // a TypeError inside the model.
        $channel = $options['channel'];
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $initialAmount = $options['initial_amount'];
        Assert::integer($initialAmount);

        $spentAmount = $options['spent_amount'];
        Assert::integer($spentAmount);

        $code = $options['code'];
        Assert::nullOrString($code);

        $currencyCode = $options['currency_code'];
        Assert::nullOrString($currencyCode);

        $enabled = $options['enabled'];
        Assert::boolean($enabled);

        $origin = $options['origin'];
        Assert::isInstanceOf($origin, GiftCardOrigin::class);

        $customMessage = $options['custom_message'];
        Assert::nullOrString($customMessage);

        $purchaser = $options['purchaser'];
        Assert::nullOrIsInstanceOf($purchaser, CustomerInterface::class);

        $redeemer = $options['redeemer'];
        Assert::nullOrIsInstanceOf($redeemer, CustomerInterface::class);

        $expiresAt = $options['expires_at'];
        Assert::nullOrIsInstanceOf($expiresAt, \DateTimeInterface::class);

        $configuration = $this->giftCardConfigurationProvider->getForChannel($channel);

        $giftCard = $this->giftCardFactory->createNew();
        $giftCard->setChannel($channel);
        $giftCard->setCode($code ?? $this->giftCardCodeGenerator->generate($configuration));
        $giftCard->setCurrencyCode($currencyCode ?? $channel->getBaseCurrency()?->getCode());
        $giftCard->setInitialAmount($initialAmount);
        $giftCard->setOrigin($origin);
        $giftCard->setEnabled($enabled);
        $giftCard->setCustomMessage($customMessage);
        $giftCard->setPurchaser($purchaser);
        $giftCard->setExpiresAt($expiresAt ?? $configuration?->calculateExpiryDate());

        // Fixtures need to describe a partly-spent card - that is the interesting state for the
        // account page. Spending it down goes through the balance modifier rather than the model
        // directly, so the card gets a ledger entry too: a fixture must not be able to produce a
        // balance with no history, which is a state real usage can never reach.
        $spentAmount = min($spentAmount, $initialAmount);
        if ($spentAmount > 0) {
            $this->giftCardBalanceModifier->debit($giftCard, $spentAmount);
        }

        if (null !== $redeemer) {
            $giftCard->assignRedeemer($redeemer);
        }

        return $giftCard;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('code', null)
            ->setAllowedTypes('code', ['null', 'string'])

            ->setDefault('channel', LazyOption::randomOne($this->channelRepository))
            ->setAllowedTypes('channel', ['string', ChannelInterface::class])
            ->setNormalizer('channel', LazyOption::findOneBy($this->channelRepository, 'code'))

            ->setDefault('currency_code', null)
            ->setAllowedTypes('currency_code', ['null', 'string'])

            ->setDefault('initial_amount', 10000)
            ->setAllowedTypes('initial_amount', 'int')

            ->setDefault('spent_amount', 0)
            ->setAllowedTypes('spent_amount', 'int')

            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', 'bool')

            ->setDefault('expires_at', null)
            // Allowed types are checked before normalizers run, so a date string has to be accepted
            // here as well as the object the normalizer turns it into.
            ->setAllowedTypes('expires_at', ['null', 'string', \DateTimeInterface::class])
            ->setNormalizer('expires_at', static function (Options $options, mixed $value): ?\DateTimeInterface {
                if (is_string($value)) {
                    return new \DateTime($value);
                }

                return $value instanceof \DateTimeInterface ? $value : null;
            })

            ->setDefault('origin', GiftCardOrigin::Admin)
            ->setAllowedTypes('origin', GiftCardOrigin::class)

            ->setDefault('custom_message', null)
            ->setAllowedTypes('custom_message', ['null', 'string'])

            ->setDefault('purchaser', null)
            ->setAllowedTypes('purchaser', ['null', 'string', CustomerInterface::class])
            ->setNormalizer('purchaser', LazyOption::findOneBy($this->customerRepository, 'email'))

            ->setDefault('redeemer', null)
            ->setAllowedTypes('redeemer', ['null', 'string', CustomerInterface::class])
            ->setNormalizer('redeemer', LazyOption::findOneBy($this->customerRepository, 'email'))
        ;
    }
}
