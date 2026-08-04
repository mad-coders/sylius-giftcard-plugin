<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Fixture;

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
                ->booleanNode('enabled')->end()
        ;
    }
}
