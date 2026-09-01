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

    /**
     * @param bool|null $rateLimiterAvailable whether symfony/rate-limiter can be used; null asks the
     *                                        autoloader, which is what the bundle does. It is an
     *                                        argument only so that a test can build the container as
     *                                        a host without the component would see it - the branch
     *                                        that has to be proven is the one this repository cannot
     *                                        otherwise reach, since it installs the component.
     */
    public function __construct(private readonly ?bool $rateLimiterAvailable = null)
    {
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{redemption_rate_limit: array{enabled: bool, limit: int, interval: string, shop_limit: int, shop_blocks: bool}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.xml');

        $this->loadRedemptionRateLimiter($config['redemption_rate_limit'], $this->isRateLimitExplicitlyEnabled($configs), $loader, $container);

        // `config/validation` is deliberately NOT registered here. FrameworkBundle already scans
        // `<bundle path>/config/validation` for every registered bundle, and this plugin's
        // getPath() points at the repository root - so the files load exactly once, on their own.
        //
        // Prepending to `framework` from load() would have been a no-op anyway
        // (MergeExtensionConfigurationPass runs every prepend() before it loads any extension, and
        // loads FrameworkBundle first), and moving it to prepend() would make the same files load
        // TWICE - two identical violations per add-to-cart.
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
     * limiter through an `on-invalid="null"` argument, so a host that has switched the limiter off -
     * or has simply never installed the component - boots and redeems cards exactly as before.
     *
     * Degrading quietly is right for the *default*. It is wrong for an explicit `enabled: true`: a
     * shop owner who turned the limiter on and forgot the `composer require` would otherwise get no
     * limiter, no exception, no log and documentation telling them they are protected. So that case
     * fails the container build instead.
     *
     * @param array{enabled: bool, limit: int, interval: string, shop_limit: int, shop_blocks: bool} $config
     */
    private function loadRedemptionRateLimiter(
        array $config,
        bool $explicitlyEnabled,
        XmlFileLoader $loader,
        ContainerBuilder $container,
    ): void {
        if (!$config['enabled']) {
            return;
        }

        if (!$this->isRateLimiterAvailable()) {
            if ($explicitlyEnabled) {
                throw new \LogicException(
                    'madcoders_sylius_gift_card.redemption_rate_limit is enabled but symfony/rate-limiter is not installed, so gift card redemption would accept unlimited attempts. Run "composer require symfony/rate-limiter", or set enabled: false to accept that deliberately.',
                );
            }

            return;
        }

        $container->setParameter('madcoders_sylius_gift_card.redemption_rate_limit.limit', $config['limit']);
        $container->setParameter('madcoders_sylius_gift_card.redemption_rate_limit.interval', $config['interval']);
        $container->setParameter('madcoders_sylius_gift_card.redemption_rate_limit.shop_limit', max(1, $config['shop_limit']));
        $container->setParameter('madcoders_sylius_gift_card.redemption_rate_limit.shop_blocks', $config['shop_blocks']);

        // A limit of zero means "do not watch the shop as a whole" rather than "refuse everybody", so
        // it becomes the component's own no-op policy rather than a window nobody can consume from.
        $container->setParameter(
            'madcoders_sylius_gift_card.redemption_rate_limit.shop_policy',
            0 === $config['shop_limit'] ? 'no_limit' : 'fixed_window',
        );

        // Outside config/services/, which services.xml globs wholesale: this is the one file whose
        // loading is a decision rather than a given.
        $loader->load('services/optional/rate_limiter.xml');
    }

    /**
     * Whether a host asked for the limiter in as many words, as opposed to inheriting the default.
     *
     * Read from the raw configuration because the processed one cannot tell the two apart - both
     * arrive as `true`, and the difference is the whole point.
     *
     * @param array<array-key, mixed> $configs
     */
    private function isRateLimitExplicitlyEnabled(array $configs): bool
    {
        $explicit = false;

        foreach ($configs as $config) {
            if (!\is_array($config) || !\is_array($config['redemption_rate_limit'] ?? null)) {
                continue;
            }

            if (\array_key_exists('enabled', $config['redemption_rate_limit'])) {
                $explicit = true === $config['redemption_rate_limit']['enabled'];
            }
        }

        return $explicit;
    }

    private function isRateLimiterAvailable(): bool
    {
        return $this->rateLimiterAvailable ?? class_exists(RateLimiterFactory::class);
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
        if (!$this->isRateLimiterAvailable()) {
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
