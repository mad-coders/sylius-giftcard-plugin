<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Form\Type;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

final class GiftCardConfigurationType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('channel', ChannelChoiceType::class, [
                'label' => 'sylius.ui.channel',
                'required' => true,
            ])
            ->add('codePrefix', TextType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.code_prefix',
                'required' => false,
                'help' => 'madcoders_sylius_gift_card.ui.code_prefix_help',
            ])
            ->add('codeLength', IntegerType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.code_length',
                'required' => true,
                'help' => 'madcoders_sylius_gift_card.ui.code_length_help',
                'constraints' => [
                    new GreaterThanOrEqual(
                        value: GiftCardConfiguration::MINIMUM_CODE_LENGTH,
                        message: 'madcoders_sylius_gift_card.gift_card_configuration.code_length.too_short',
                    ),
                ],
            ])
            ->add('validityPeriod', TextType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.validity_period',
                'required' => false,
                'help' => 'madcoders_sylius_gift_card.ui.validity_period_help',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
        ;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'madcoders_sylius_gift_card_gift_card_configuration';
    }
}
