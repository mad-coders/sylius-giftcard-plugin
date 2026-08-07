<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Form\Extension;

use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Adds the "this product is a gift card" flag to the admin product form.
 *
 * The field is only added when the application's Product actually carries the flag, so a host
 * application that has not applied ProductTrait gets a working product form rather than an error.
 */
final class ProductTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $product = $event->getData();

            if (!$product instanceof ProductInterface) {
                return;
            }

            $event->getForm()->add('giftCard', CheckboxType::class, [
                'label' => 'madcoders_sylius_gift_card.ui.is_gift_card',
                'required' => false,
                'help' => 'madcoders_sylius_gift_card.ui.is_gift_card_help',
            ]);
        });
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }
}
