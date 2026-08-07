<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

/**
 * Adds "My gift cards" to the shop account menu.
 */
final class ShopAccountMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $event->getMenu()
            ->addChild('madcoders_gift_cards', ['route' => 'madcoders_sylius_gift_card_shop_account_index'])
            ->setLabel('madcoders_sylius_gift_card.menu.shop.account.gift_cards')
            ->setLabelAttribute('icon', 'tabler:gift')
        ;
    }
}
