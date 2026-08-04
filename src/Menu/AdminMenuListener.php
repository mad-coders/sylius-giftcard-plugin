<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

/**
 * Adds the gift card entries to the admin main menu, under Marketing - a gift card sits alongside
 * promotions in a shop owner's mental model, not under Configuration.
 */
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $marketing = $menu->getChild('marketing');
        if (null === $marketing) {
            // A host application that has rebuilt its menu without a marketing section should not
            // get a crash for it.
            return;
        }

        $marketing
            ->addChild('madcoders_gift_cards', [
                'route' => 'madcoders_sylius_gift_card_admin_gift_card_index',
                'extras' => ['routes' => [
                    ['route' => 'madcoders_sylius_gift_card_admin_gift_card_create'],
                    ['route' => 'madcoders_sylius_gift_card_admin_gift_card_update'],
                    ['route' => 'madcoders_sylius_gift_card_admin_gift_card_show'],
                ]],
            ])
            ->setLabel('madcoders_sylius_gift_card.menu.admin.gift_cards')
            ->setLabelAttribute('icon', 'tabler:gift')
        ;

        $marketing
            ->addChild('madcoders_gift_card_configurations', [
                'route' => 'madcoders_sylius_gift_card_admin_gift_card_configuration_index',
                'extras' => ['routes' => [
                    ['route' => 'madcoders_sylius_gift_card_admin_gift_card_configuration_create'],
                    ['route' => 'madcoders_sylius_gift_card_admin_gift_card_configuration_update'],
                ]],
            ])
            ->setLabel('madcoders_sylius_gift_card.menu.admin.gift_card_configuration')
            ->setLabelAttribute('icon', 'tabler:settings')
        ;
    }
}
