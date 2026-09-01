<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Form\Type;

use Madcoders\SyliusGiftCardPlugin\Form\DataMapper\GiftCardAmountMapper;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\ChosenGiftCardAmount;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * How much the customer wants the gift card to be worth.
 *
 * Renders as whatever the channel offers: radio buttons for the presets, a money box for a free
 * amount, or both with an "other amount" radio joining them. Plain HTML controls throughout - no
 * JavaScript decides anything, so the field works in any browser and the non-JavaScript test suite
 * can drive it.
 *
 * Maps onto the single integer the order item stores; see {@see GiftCardAmountMapper}.
 */
final class GiftCardAmountType extends AbstractType
{
    public function __construct(private readonly MoneyFormatterInterface $moneyFormatter)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var GiftCardConfigurationInterface $configuration */
        $configuration = $options['configuration'];
        /** @var string $currencyCode */
        $currencyCode = $options['currency_code'];
        /** @var string|null $localeCode */
        $localeCode = $options['locale_code'];

        $mode = $configuration->getAmountMode();
        $presets = $configuration->getAmountPresets();

        if ($mode->offersPresets()) {
            $builder->add('preset', ChoiceType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.choose_amount',
                'choices' => $this->presetChoices($presets, $currencyCode, $localeCode, $mode->offersFreeAmount()),
                'expanded' => true,
                'multiple' => false,
                'required' => false,
                'placeholder' => false,
            ]);
        }

        if ($mode->offersFreeAmount()) {
            $builder->add('custom', MoneyType::class, [
                'label' => $mode->offersPresets()
                    ? 'madcoders_sylius_gift_card.ui.other_amount'
                    : 'madcoders_sylius_gift_card.ui.choose_amount',
                'required' => false,
                'currency' => $currencyCode,
                'attr' => [
                    // Advisory only. The bounds are enforced by the constraint on the compound
                    // field, which is what a request that ignores these attributes still meets.
                    'min' => self::toMajorUnits($configuration->getMinimumAmount()),
                    'max' => self::toMajorUnits($configuration->getMaximumAmount()),
                    'step' => 'any',
                ],
            ]);
        }

        $builder->setDataMapper(new GiftCardAmountMapper($presets));
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var GiftCardConfigurationInterface $configuration */
        $configuration = $options['configuration'];

        // The template needs the bounds to show the customer what they may type, and money is
        // formatted in Twig everywhere else in this plugin.
        $view->vars['minimum_amount'] = $configuration->getMinimumAmount();
        $view->vars['maximum_amount'] = $configuration->getMaximumAmount();
        $view->vars['currency_code'] = $options['currency_code'];
        $view->vars['offers_free_amount'] = $configuration->getAmountMode()->offersFreeAmount();
        $view->vars['custom_choice'] = GiftCardAmountMapper::CUSTOM_CHOICE;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['configuration', 'currency_code'])
            ->setDefaults([
                'locale_code' => null,
                'compound' => true,
                'error_bubbling' => false,
                'label' => false,
                // Both groups on purpose: Sylius validates the add-to-cart form in the "sylius"
                // group, but a host application posting the same form its own way may not, and an
                // amount that skips this check is an amount nobody offered.
                'constraints' => [new ChosenGiftCardAmount(groups: ['Default', 'sylius'])],
            ])
            ->setAllowedTypes('configuration', GiftCardConfigurationInterface::class)
            ->setAllowedTypes('currency_code', 'string')
            ->setAllowedTypes('locale_code', ['string', 'null'])
        ;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'madcoders_sylius_gift_card_amount';
    }

    /**
     * @param list<int> $presets
     *
     * @return array<string, string> label => submitted value
     */
    private function presetChoices(array $presets, string $currencyCode, ?string $localeCode, bool $offersFreeAmount): array
    {
        $choices = [];

        foreach ($presets as $preset) {
            $choices[$this->moneyFormatter->format($preset, $currencyCode, $localeCode)] = (string) $preset;
        }

        if ($offersFreeAmount) {
            $choices['madcoders_sylius_gift_card.ui.other_amount'] = GiftCardAmountMapper::CUSTOM_CHOICE;
        }

        return $choices;
    }

    /** The `min`/`max` attributes of a money input are in major units, like the value beside them. */
    private static function toMajorUnits(?int $amount): ?string
    {
        return null === $amount ? null : number_format($amount / 100, 2, '.', '');
    }
}
