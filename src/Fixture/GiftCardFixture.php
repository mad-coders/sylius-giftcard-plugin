<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class GiftCardFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'madcoders_gift_card';
    }

    #[\Override]
    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $resourceNode
            ->children()
                ->scalarNode('code')->cannotBeEmpty()->end()
                ->scalarNode('channel')->cannotBeEmpty()->end()
                ->scalarNode('currency_code')->cannotBeEmpty()->end()
                ->integerNode('initial_amount')->min(1)->end()
                ->integerNode('spent_amount')->min(0)->end()
                ->booleanNode('enabled')->end()
                ->scalarNode('expires_at')->end()
                ->scalarNode('custom_message')->end()
                ->scalarNode('purchaser')->cannotBeEmpty()->end()
                ->scalarNode('redeemer')->cannotBeEmpty()->end()
        ;
    }
}
