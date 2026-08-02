<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        return new TreeBuilder('madcoders_sylius_gift_card');
    }
}
