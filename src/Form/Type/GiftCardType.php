<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Form\Type;

use Madcoders\SyliusGiftCardPlugin\Calculator\GiftCardExpiryCalculatorInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * The admin create/edit form for a gift card.
 */
final class GiftCardType extends AbstractResourceType
{
    /**
     * @param array<string> $validationGroups
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        string $dataClass,
        array $validationGroups,
        private readonly GiftCardExpiryCalculatorInterface $giftCardExpiryCalculator,
        private readonly GiftCardConfigurationProviderInterface $giftCardConfigurationProvider,
        private readonly RepositoryInterface $channelRepository,
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

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
            // Required, because every card expires. The field arrives pre-filled - see
            // prefillExpiryDate() below - so an administrator normally has nothing to type. What
            // they cannot do is empty it, or move it into the past: NotNull and
            // GiftCardExpiryNotInThePast both live on the model in config/validation/GiftCard.xml,
            // so every write path is covered rather than only this form, and `required` here is
            // what stops the browser submitting a blank at all.
            ->add('expiresAt', DateTimeType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.expires_at',
                'required' => true,
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
        //
        // The code is immutable for the same reason and one more: it is the only link between an
        // order and the card it was paid with, since that is what the adjustment records. Renaming
        // an issued card would strand every order that used it - cancelling one would silently
        // refund nothing - and would invalidate the code already printed on the customer's card.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $giftCard = $event->getData();

            if ($giftCard instanceof GiftCardInterface) {
                $this->prefillExpiryDate($giftCard);
            }

            if ($giftCard instanceof GiftCardInterface && null !== $giftCard->getCode()) {
                // Disabled rather than removed: the admin still needs to see which card they are
                // editing, and a disabled field keeps its stored value whatever is submitted.
                $event->getForm()->add('code', TextType::class, [
                    'label' => 'madcoders_sylius_gift_card.ui.code',
                    'required' => false,
                    'disabled' => true,
                    'help' => 'madcoders_sylius_gift_card.ui.code_immutable_help',
                ]);
            }

            if ($giftCard instanceof GiftCardInterface && null !== $giftCard->getInitialAmount()) {
                return;
            }

            $event->getForm()->add('initialAmount', MoneyType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.initial_amount',
                'required' => true,
                // The card's currency comes from its channel, so the widget shows no symbol
                // rather than a hardcoded (and probably wrong) one.
                'currency' => false,
                // Written through a callback rather than straight onto the property, and that is
                // load-bearing rather than a style choice. Symfony maps a submitted value onto the
                // object during submit() and validates it afterwards, so a plainly mapped field
                // hands setInitialAmount() a zero *before* the Positive below is looked at - and the
                // model, which refuses a non-positive amount by throwing, turns a field error into a
                // 500. Declining to write leaves the constraint to report it, and leaves the card
                // without a face value, which is what an invalid form should do.
                //
                // A blank survives a plainly mapped field only by accident: Symfony skips the write
                // when the new value equals the current one, and on a new card both are null. That
                // is not a guarantee worth depending on, so the callback covers it as well.
                //
                // Only the write is skipped: the field stays mapped, so the value the administrator
                // typed is read back and re-rendered with the error against it.
                'setter' => static function (GiftCardInterface $giftCard, ?int $initialAmount): void {
                    if (null === $initialAmount || $initialAmount <= 0) {
                        return;
                    }

                    $giftCard->setInitialAmount($initialAmount);
                },
                // These are what tells the administrator which of the two is wrong. They only run
                // because this form validates with `Default` as well as the resource group
                // (config/services/forms.xml): an inline constraint carries `Default` unless it is
                // told otherwise, and until issue #44 the form named only the resource group, so
                // neither of them was ever evaluated - see
                // docs/adr-log/0017-resource-forms-validate-with-default-too.md.
                'constraints' => [
                    new NotBlank(message: 'madcoders_sylius_gift_card.gift_card.initial_amount.not_blank'),
                    new Positive(message: 'madcoders_sylius_gift_card.gift_card.initial_amount.positive'),
                ],
            ]);
        });
    }

    /**
     * Puts a date into the expiry field of an empty create form.
     *
     * The administrator has to *see* what they are about to issue: an expiry is a term of the sale,
     * and it is the one field they may reasonably want to change per card. Done here rather than in
     * PrepareGiftCardOnCreateListener because the resource controller dispatches its initialize
     * event after the form has already been built from the resource, so anything set there would
     * never reach the rendered field.
     *
     * The channel is chosen on this same form, so there is usually no channel yet to read a
     * validity period from. In a single-channel shop - most of them - that one channel is the
     * answer and its own period is used. In a multi-channel shop the honest answer is the plugin's
     * default period, shown and editable, rather than a date computed from a channel the
     * administrator has not picked. See docs/adr-log/0015-every-gift-card-expires.md.
     */
    private function prefillExpiryDate(GiftCardInterface $giftCard): void
    {
        if (null !== $giftCard->getExpiresAt()) {
            return;
        }

        $channel = $giftCard->getChannel() ?? $this->onlyChannel();

        $giftCard->setExpiresAt($this->giftCardExpiryCalculator->calculate(
            null === $channel ? null : $this->giftCardConfigurationProvider->getForChannel($channel),
        ));
    }

    /** The shop's channel when it has exactly one, so a single-channel shop needs no guesswork. */
    private function onlyChannel(): ?ChannelInterface
    {
        $channels = $this->channelRepository->findAll();

        return 1 === count($channels) ? $channels[0] : null;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'madcoders_sylius_gift_card_gift_card';
    }
}
