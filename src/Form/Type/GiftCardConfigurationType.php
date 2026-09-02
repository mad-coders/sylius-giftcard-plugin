<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Form\Type;

use Madcoders\SyliusGiftCardPlugin\Form\DataTransformer\AmountPresetsTransformer;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardAmountMode;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardSaleMode;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTenderMode;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
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
                // This is the only thing that tells the operator their code length was too short,
                // and it was inert until issue #44: an inline constraint carries `Default`, and the
                // form named only the resource group - see
                // docs/adr-log/0017-resource-forms-validate-with-default-too.md.
                //
                // It was long believed unable to catch a short code anyway, because
                // GiftCardConfiguration::setCodeLength() raises anything below the minimum to it as
                // a backstop, so no caller can leave a channel issuing guessable codes. That
                // reasoning is wrong, and the form used to carry a hand-made FormError built on it.
                // A field constraint validates the *child form's* own reverse-transformed value -
                // the 4 the operator typed - and never looks at the model at all, so the constraint
                // sees the raw input and the backstop is irrelevant to it. The two together showed
                // the same sentence twice.
                'constraints' => [
                    new GreaterThanOrEqual(
                        value: GiftCardConfiguration::MINIMUM_CODE_LENGTH,
                        message: 'madcoders_sylius_gift_card.gift_card_configuration.code_length.too_short',
                    ),
                ],
            ])
            // Required, and refused if it cannot be parsed. Both constraints live on the model in
            // config/validation/GiftCardConfiguration.xml rather than here, so a configuration
            // written by an importer or a data fixture is judged by the same rules as one typed
            // into this form.
            ->add('validityPeriod', TextType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.validity_period',
                'required' => true,
                'help' => 'madcoders_sylius_gift_card.ui.validity_period_help',
            ])
            ->add('tenderMode', EnumType::class, [
                'class' => GiftCardTenderMode::class,
                'label' => 'madcoders_sylius_gift_card.ui.tender_mode',
                'required' => true,
                'help' => 'madcoders_sylius_gift_card.ui.tender_mode_help',
                'choice_label' => static fn (GiftCardTenderMode $mode): string => 'madcoders_sylius_gift_card.ui.tender_mode_choice.' . $mode->value,
            ])
            ->add('saleMode', EnumType::class, [
                'class' => GiftCardSaleMode::class,
                'label' => 'madcoders_sylius_gift_card.ui.sale_mode',
                'required' => true,
                'help' => 'madcoders_sylius_gift_card.ui.sale_mode_help',
                'choice_label' => static fn (GiftCardSaleMode $mode): string => 'madcoders_sylius_gift_card.ui.sale_mode_choice.' . $mode->value,
            ])
            ->add('amountMode', EnumType::class, [
                'class' => GiftCardAmountMode::class,
                'label' => 'madcoders_sylius_gift_card.ui.amount_mode',
                'help' => 'madcoders_sylius_gift_card.ui.amount_mode_help',
                'required' => true,
                'choice_label' => static fn (GiftCardAmountMode $mode): string => 'madcoders_sylius_gift_card.ui.amount_mode.' . $mode->value,
            ])
            ->add('minimumAmount', MoneyType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.minimum_amount',
                'help' => 'madcoders_sylius_gift_card.ui.minimum_amount_help',
                'required' => false,
                // The channel's currency is chosen on this same form, so there is no symbol to show
                // that is guaranteed to be right.
                'currency' => false,
            ])
            ->add('maximumAmount', MoneyType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.maximum_amount',
                'help' => 'madcoders_sylius_gift_card.ui.maximum_amount_help',
                'required' => false,
                'currency' => false,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
        ;

        $builder->add(
            $builder->create('amountPresets', TextType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.amount_presets',
                'help' => 'madcoders_sylius_gift_card.ui.amount_presets_help',
                'required' => false,
                'invalid_message' => 'madcoders_sylius_gift_card.gift_card_configuration.amount_presets.invalid',
            ])->addModelTransformer(new AmountPresetsTransformer()),
        );

        $this->addAmountConsistencyChecks($builder);
    }

    /**
     * Refuses a channel that offers a choice it cannot honour.
     *
     * These are cross-field rules, so they cannot live on the fields themselves. They are checked
     * here rather than only on the model because the model's answer to a half-configured range is to
     * offer nothing - which is the safe behaviour at runtime, but a silent one for the operator who
     * thought they had set the channel up.
     */
    private function addAmountConsistencyChecks(FormBuilderInterface $builder): void
    {
        $presetsRequired = $this->validationMessage('madcoders_sylius_gift_card.gift_card_configuration.amount_presets.required');
        $boundsRequired = $this->validationMessage('madcoders_sylius_gift_card.gift_card_configuration.bounds.required');
        $boundsInverted = $this->validationMessage('madcoders_sylius_gift_card.gift_card_configuration.bounds.inverted');

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            static function (FormEvent $event) use ($presetsRequired, $boundsRequired, $boundsInverted): void {
                $configuration = $event->getData();

                if (!$configuration instanceof GiftCardConfigurationInterface) {
                    return;
                }

                $form = $event->getForm();
                $mode = $configuration->getAmountMode();

                if ($mode->offersPresets() && [] === $configuration->getAmountPresets()) {
                    $form->get('amountPresets')->addError(new FormError($presetsRequired));
                }

                if (!$mode->offersFreeAmount()) {
                    return;
                }

                $minimum = $configuration->getMinimumAmount();
                $maximum = $configuration->getMaximumAmount();

                if (null === $minimum || null === $maximum) {
                    $form->get(null === $minimum ? 'minimumAmount' : 'maximumAmount')->addError(new FormError($boundsRequired));

                    return;
                }

                if ($minimum > $maximum) {
                    $form->get('maximumAmount')->addError(new FormError($boundsInverted));
                }
            },
        );
    }

    /**
     * A validation message this form raises itself, ready to hand to a FormError.
     *
     * A FormError added by hand is rendered verbatim - unlike a violation from the validator,
     * nothing translates it on the way out - so it has to be translated here. The domain is named
     * explicitly because the translator's default is `messages`, while Symfony resolves every
     * constraint violation in `validators`. Leaving it to the default is what split this plugin's
     * validation messages across two catalogues and left the constraint ones rendering as raw keys
     * (issue #37). One rule now: a validation message lives in `validators`, whoever raises it.
     */
    private function validationMessage(string $key): string
    {
        return $this->translator->trans($key, [], 'validators');
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'madcoders_sylius_gift_card_gift_card_configuration';
    }
}
