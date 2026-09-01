<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Form\Type;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardSaleMode;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GiftCardConfigurationType extends AbstractResourceType
{
    /** @param array<string> $validationGroups */
    public function __construct(
        string $dataClass,
        array $validationGroups,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

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
                // Kept for anything that validates the object directly, but it cannot catch a short
                // code on this form - see the listener below.
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
            ->add('saleMode', EnumType::class, [
                'class' => GiftCardSaleMode::class,
                'label' => 'madcoders_sylius_gift_card.ui.sale_mode',
                'required' => true,
                'help' => 'madcoders_sylius_gift_card.ui.sale_mode_help',
                'choice_label' => static fn (GiftCardSaleMode $mode): string => 'madcoders_sylius_gift_card.ui.sale_mode_choice.' . $mode->value,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
        ;

        // The constraint above never fires. GiftCardConfiguration::setCodeLength() raises anything
        // below the minimum to it - a deliberate backstop, so no caller can leave a channel issuing
        // guessable codes - and by the time the field is validated it is holding the raised value.
        //
        // Silently giving an operator something other than what they typed is its own bug: they
        // walk away believing this channel issues 4-character codes. So the raw input is captured
        // before the model rounds it up, and the error is raised after the form has submitted -
        // an error added during PRE_SUBMIT would be discarded when the child submits.
        $submittedCodeLength = null;
        // A FormError added by hand is rendered verbatim - unlike a violation from the validator,
        // nothing translates it on the way out - so it is translated here.
        $tooShort = $this->translator->trans('madcoders_sylius_gift_card.gift_card_configuration.code_length.too_short');

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            static function (FormEvent $event) use (&$submittedCodeLength): void {
                $data = $event->getData();

                $submittedCodeLength = is_array($data) && isset($data['codeLength']) && is_numeric($data['codeLength'])
                    ? (int) $data['codeLength']
                    : null;
            },
        );

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            static function (FormEvent $event) use (&$submittedCodeLength, $tooShort): void {
                if (null === $submittedCodeLength || $submittedCodeLength >= GiftCardConfiguration::MINIMUM_CODE_LENGTH) {
                    return;
                }

                $event->getForm()->get('codeLength')->addError(new FormError($tooShort));
            },
        );
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'madcoders_sylius_gift_card_gift_card_configuration';
    }
}
