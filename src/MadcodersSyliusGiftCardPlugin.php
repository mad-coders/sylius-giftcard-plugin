<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin;

use Madcoders\SyliusGiftCardPlugin\DependencyInjection\Compiler\RegisterGiftCardAdjustmentClearingPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class MadcodersSyliusGiftCardPlugin extends Bundle
{
    use SyliusPluginTrait;

    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterGiftCardAdjustmentClearingPass());
    }

    #[\Override]
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
