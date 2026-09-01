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
                            ->info('On by default. Setting this to true explicitly without symfony/rate-limiter installed is an error rather than a no-op.')
                        ->end()
                        ->integerNode('limit')
                            ->defaultValue(10)
                            ->min(1)
                            ->info('Failed attempts allowed per client per window. Successful ones do not count.')
                        ->end()
                        // The window length is validated here rather than at the first redeem attempt.
                        // RateLimiterFactory parses the interval in its *constructor* and throws a
                        // LogicException when it cannot; the factory is lazy, so an unparseable value
                        // passes lint:container and then 500s on the first customer to type a gift
                        // card code - on the money path, in production. Making the same parse at
                        // compile time turns that into a container build failure, where a typo belongs.
                        ->scalarNode('interval')
                            ->defaultValue('15 minutes')
                            ->cannotBeEmpty()
                            ->info('Length of the window, as a relative date format - "15 minutes", "1 hour".')
                            ->validate()
                                ->ifTrue(static fn (mixed $interval): bool => !\is_string($interval) || !self::isParsableInterval($interval))
                                ->thenInvalid('Invalid gift card redemption rate limit interval %s. Use a relative date format such as "15 minutes" or "1 hour" - see https://php.net/datetime.formats#datetime.formats.relative.')
                            ->end()
                        ->end()
                        ->integerNode('shop_limit')
                            ->defaultValue(200)
                            ->min(0)
                            ->info('Failed attempts allowed across the whole shop per window, catching guessing spread over many addresses. 0 switches this off.')
                        ->end()
                        ->booleanNode('shop_blocks')
                            ->defaultFalse()
                            ->info('Whether reaching shop_limit refuses redemption for everybody, or only raises an alert. Off by default: a shop-wide block is a kill switch anyone with a botnet can pull.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /** The check RateLimiterFactory itself makes, made early. */
    private static function isParsableInterval(string $interval): bool
    {
        $now = new \DateTimeImmutable('@0');

        try {
            return false !== @$now->modify('+' . $interval);
        } catch (\Throwable) {
            return false;
        }
    }
}
