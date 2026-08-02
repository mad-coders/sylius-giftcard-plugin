<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit;

use Madcoders\SyliusGiftCardPlugin\DependencyInjection\MadcodersSyliusGiftCardExtension;
use Madcoders\SyliusGiftCardPlugin\MadcodersSyliusGiftCardPlugin;
use PHPUnit\Framework\TestCase;

final class MadcodersSyliusGiftCardPluginTest extends TestCase
{
    public function testItsPathIsTheRepositoryRootSoConfigAndTemplatesResolve(): void
    {
        $plugin = new MadcodersSyliusGiftCardPlugin();

        // The Sylius 2.x plugin layout keeps config/ and templates/ at the repository root rather
        // than under src/Resources/, so the bundle path must point one level above src/.
        self::assertFileExists($plugin->getPath() . '/config/config.yaml');
        self::assertFileExists($plugin->getPath() . '/config/services.php');
    }

    public function testItExposesTheExpectedConfigurationAlias(): void
    {
        // The alias is the key host applications configure the plugin under, and the prefix every
        // service id and parameter derives from. Changing it is a breaking change.
        self::assertSame('madcoders_sylius_gift_card', (new MadcodersSyliusGiftCardExtension())->getAlias());
    }
}
