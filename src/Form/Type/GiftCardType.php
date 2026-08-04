<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Form\Type;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * The admin create/edit form for a gift card.
 */
final class GiftCardType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.code',
                'required' => false,
                'help' => 'madcoders_sylius_gift_card.ui.code_help',
            ])
            ->add('channel', ChannelChoiceType::class, [
                'label' => 'sylius.ui.channel',
                'required' => true,
            ])
            ->add('expiresAt', DateTimeType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.expires_at',
                'required' => false,
                'widget' => 'single_text',
                'help' => 'madcoders_sylius_gift_card.ui.expires_at_help',
            ])
            ->add('customMessage', TextareaType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.custom_message',
                'required' => false,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
        ;

        // The initial amount is immutable once set - the model throws rather than let a card's face
        // value change under orders that already reference it. So the field only exists while the
        // card is new; editing an existing card cannot touch it. Use the balance adjustment action
        // to correct a balance.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $giftCard = $event->getData();

            if ($giftCard instanceof GiftCardInterface && null !== $giftCard->getInitialAmount()) {
                return;
            }

            $event->getForm()->add('initialAmount', MoneyType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.initial_amount',
                'required' => true,
                // The card's currency comes from its channel, so the widget shows no symbol
                // rather than a hardcoded (and probably wrong) one.
                'currency' => false,
            ]);
        });
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'madcoders_sylius_gift_card_gift_card';
    }
}
