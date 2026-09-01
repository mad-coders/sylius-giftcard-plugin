<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('madcoders_sylius_gift_card');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('redemption_rate_limit')
                    ->addDefaultsIfNotSet()
                    ->info('Throttles failed gift card redemption attempts. Requires symfony/rate-limiter.')
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('On by default. Without symfony/rate-limiter installed there is nothing to switch on, and the plugin boots unthrottled.')
                        ->end()
                        ->integerNode('limit')
                            ->defaultValue(10)
                            ->min(1)
                            ->info('Failed attempts allowed per client per window. Successful ones do not count and reset the tally.')
                        ->end()
                        ->scalarNode('interval')
                            ->defaultValue('15 minutes')
                            ->cannotBeEmpty()
                            ->info('Length of the window, as a relative date format - "15 minutes", "1 hour".')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
