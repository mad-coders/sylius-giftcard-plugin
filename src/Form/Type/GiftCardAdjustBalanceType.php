<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Form\Type;

use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * Manual balance correction from the admin panel.
 *
 * The amount is always positive and the direction is an explicit choice, so an admin cannot turn a
 * top-up into a deduction with a stray minus sign.
 */
final class GiftCardAdjustBalanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('direction', ChoiceType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.adjustment_direction',
                'required' => true,
                'expanded' => true,
                'choices' => [
                    'madcoders_sylius_gift_card.ui.add_to_balance' => 'credit',
                    'madcoders_sylius_gift_card.ui.take_from_balance' => 'debit',
                ],
                'data' => 'credit',
                'constraints' => [new NotBlank()],
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.amount',
                'required' => true,
                'currency' => false,
                'constraints' => [
                    new NotBlank(),
                    new Positive(message: 'madcoders_sylius_gift_card.gift_card.amount.positive'),
                ],
            ])
        ;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'madcoders_sylius_gift_card_adjust_balance';
    }
}
