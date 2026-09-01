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

    /**
     * The repository root, not `src/`, because the plugin keeps its configuration in `config/`
     * rather than the older `src/Resources/config/`.
     *
     * FrameworkBundle scans `<bundle path>/config/validation` for constraint mapping files, so this
     * override is what registers `config/validation/*.xml`. Removing it would silently unregister
     * every constraint in there - see the wiring test in
     * tests/Functional/Validator/GiftCardPurchaseConstraintWiringTest.php.
     */
    #[\Override]
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
