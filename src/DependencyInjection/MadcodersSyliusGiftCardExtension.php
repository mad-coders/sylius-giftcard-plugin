<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Yaml\Yaml;

final class MadcodersSyliusGiftCardExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{redemption_rate_limit: array{enabled: bool, limit: int, interval: string}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.xml');

        $this->loadRedemptionRateLimiter($config['redemption_rate_limit'], $loader, $container);

        // Validation lives in its own directory rather than alongside the Doctrine mapping, so a
        // host overriding one does not have to take the other.
        $container->prependExtensionConfig('framework', [
            'validation' => [
                'mapping' => [
                    'paths' => [\dirname(__DIR__, 2) . '/config/validation'],
                ],
            ],
        ]);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMappings($container);
        $this->prependMailerEmails($container);
        $this->prependDoctrineMigrations($container);
        $this->prependRateLimiterCachePool($container);
    }

    /**
     * Wires the redemption limiter, if the host can have one and wants one.
     *
     * symfony/rate-limiter is not a Sylius dependency, so it is a `suggest` rather than a `require`
     * and the services that need it are only registered when it is there. The controller takes the
     * limiter through an `on-invalid="null"` argument, so a host without the component - or one that
     * has switched the limiter off - boots and redeems cards exactly as before, unthrottled.
     *
     * @param array{enabled: bool, limit: int, interval: string} $config
     */
    private function loadRedemptionRateLimiter(array $config, XmlFileLoader $loader, ContainerBuilder $container): void
    {
        if (!$config['enabled'] || !class_exists(RateLimiterFactory::class)) {
            return;
        }

        $container->setParameter('madcoders_sylius_gift_card.redemption_rate_limit.limit', $config['limit']);
        $container->setParameter('madcoders_sylius_gift_card.redemption_rate_limit.interval', $config['interval']);

        // Outside config/services/, which services.xml globs wholesale: this is the one file whose
        // loading is a decision rather than a given.
        $loader->load('services/optional/rate_limiter.xml');
    }

    /**
     * Gives the limiter its own cache pool.
     *
     * Prepended rather than declared in XML because a cache pool is FrameworkBundle's to build, and
     * prepending only works from prepend() - by the time load() runs, FrameworkBundle has already
     * had its configuration. A pool of its own rather than cache.app so a shop running more than one
     * web node can point *this* at a shared Redis without moving everything else: a limiter whose
     * state is per-node is a limiter multiplied by the number of nodes.
     */
    private function prependRateLimiterCachePool(ContainerBuilder $container): void
    {
        if (!class_exists(RateLimiterFactory::class)) {
            return;
        }

        $container->prependExtensionConfig('framework', [
            'cache' => [
                'pools' => [
                    'madcoders_sylius_gift_card.cache.rate_limiter' => ['adapter' => 'cache.app'],
                ],
            ],
        ]);
    }

    /**
     * Registers the plugin's emails with Sylius' mailer, if that bundle is installed.
     *
     * Configuring an extension the host has not registered is a hard container failure, so this
     * cannot live in config/config.yaml - a host can run without SyliusMailerBundle.
     */
    private function prependMailerEmails(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('sylius_mailer')) {
            return;
        }

        /** @var array{sylius_mailer?: array<string, mixed>} $config */
        $config = Yaml::parseFile(\dirname(__DIR__, 2) . '/config/mailer.yaml');

        if (isset($config['sylius_mailer'])) {
            $container->prependExtensionConfig('sylius_mailer', $config['sylius_mailer']);
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
        // The plugin's own namespace, not the application's `DoctrineMigrations`. A host application
        // already maps that name to its own `migrations/` directory, and its configuration beats
        // anything prepended here - so registering under it silently threw the plugin's migrations
        // away and `doctrine:migrations:migrate` created no gift card tables at all.
        return 'Madcoders\\SyliusGiftCardPlugin\\Migrations';
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
