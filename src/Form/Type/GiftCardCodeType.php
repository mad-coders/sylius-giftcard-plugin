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
        // This type is a plain AbstractType, not an AbstractResourceType, so it validates with
        // `Default` and the constraint below does run - unlike the ones on the admin forms before
        // issue #44. What it cannot do is put its own message in front of the customer: the panel in
        // templates/shop/common/gift_card_panel.html.twig writes the input by hand rather than
        // rendering a form view, and the controller answers with a redirect, so there is no rendered
        // field to hang an error on. GiftCardCartController therefore flashes
        // `madcoders_sylius_gift_card.cart.code_required`, which says the same sentence.
        //
        // The constraint stays because it is what a host rendering this form with form_widget gets,
        // and because a form that reports itself valid on an empty code would be lying to every
        // other caller.
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
