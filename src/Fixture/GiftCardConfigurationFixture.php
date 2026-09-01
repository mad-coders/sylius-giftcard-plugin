<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Fixture;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardAmountMode;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTenderMode;
use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class GiftCardConfigurationFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'madcoders_gift_card_configuration';
    }

    #[\Override]
    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $resourceNode
            ->children()
                ->scalarNode('channel')->cannotBeEmpty()->end()
                ->integerNode('code_length')->min(1)->end()
                ->scalarNode('code_prefix')->end()
                ->scalarNode('validity_period')->end()
                ->scalarNode('sale_mode')->end()
                ->enumNode('tender_mode')
                    ->values(array_column(GiftCardTenderMode::cases(), 'value'))
                ->end()
                ->booleanNode('enabled')->end()
                ->enumNode('amount_mode')
                    ->values(array_column(GiftCardAmountMode::cases(), 'value'))
                ->end()
                // Minor units, like every other amount in the plugin.
                ->arrayNode('amount_presets')->integerPrototype()->end()->end()
                ->integerNode('minimum_amount')->end()
                ->integerNode('maximum_amount')->end()
        ;
    }
}
