<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The "enter a gift card code" form on the cart.
 *
 * Deliberately a standalone form posting to the plugin's own controller rather than a field added
 * to Sylius' cart form: the cart page wraps its whole content in one form and drives it with a Live
 * Component, so a nested form would be invalid HTML and the Live Component wiring differs across
 * the Sylius 2.x minors this plugin supports.
 */
final class GiftCardCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'madcoders_sylius_gift_card.ui.gift_card_code',
            'required' => true,
            'constraints' => [
                new NotBlank(message: 'madcoders_sylius_gift_card.gift_card.code.not_blank'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'madcoders_sylius_gift_card',
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'madcoders_sylius_gift_card_code';
    }
}
