<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Yaml\Yaml;

final class MadcodersSyliusGiftCardExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.xml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMappings($container);
        $this->prependWinzouStateMachine($container);
        $this->prependDoctrineMigrations($container);
    }

    /**
     * Registers the winzou state machine callbacks, but only if that adapter is installed.
     *
     * Sylius 2.x supports both winzou/state-machine-bundle and Symfony Workflow, and defaults to
     * the latter - winzou is frequently absent. Importing configuration for an unregistered
     * extension is a hard container failure, so this cannot live in config/config.yaml. The
     * Symfony Workflow half of the wiring is plain service tags and needs no such guard; see
     * config/services/listeners.xml.
     */
    private function prependWinzouStateMachine(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('winzou_state_machine')) {
            return;
        }

        /** @var array{winzou_state_machine?: array<string, mixed>} $config */
        $config = Yaml::parseFile(\dirname(__DIR__, 2) . '/config/state_machine/winzou/sylius_order.yaml');

        if (isset($config['winzou_state_machine'])) {
            $container->prependExtensionConfig('winzou_state_machine', $config['winzou_state_machine']);
        }
    }

    /**
     * Registers config/doctrine explicitly rather than letting DoctrineBundle auto-detect it.
     *
     * Auto-detection assumes a bundle's mapped classes live under `<BundleNamespace>\Entity` and
     * derives the class name from the file name. The plugin's mapped superclasses live under
     * `\Model` (they are models, not entities - the host application supplies the entities), so the
     * prefix has to be stated.
     */
    private function prependDoctrineMappings(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'MadcodersSyliusGiftCardPlugin' => [
                        'is_bundle' => false,
                        'type' => 'xml',
                        'dir' => \dirname(__DIR__, 2) . '/config/doctrine',
                        'prefix' => 'Madcoders\SyliusGiftCardPlugin\Model',
                    ],
                ],
            ],
        ]);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'DoctrineMigrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@MadcodersSyliusGiftCardPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
