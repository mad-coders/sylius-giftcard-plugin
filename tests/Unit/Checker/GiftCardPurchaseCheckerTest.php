<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Checker;

use Madcoders\SyliusGiftCardPlugin\Checker\GiftCardPurchaseChecker;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardSaleMode;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Whether a channel lets the shop sell gift cards. Getting the default wrong here silently stops
 * every existing shop from selling, so the unconfigured case matters as much as the configured one.
 */
final class GiftCardPurchaseCheckerTest extends TestCase
{
    public function testAChannelSetToSellableSellsGiftCards(): void
    {
        $checker = $this->createChecker($this->createConfiguration(GiftCardSaleMode::Sellable));

        self::assertTrue($checker->canBeBoughtIn($this->createChannel()));
    }

    public function testAChannelSetToAdministratorOnlyDoesNotSellGiftCards(): void
    {
        $checker = $this->createChecker($this->createConfiguration(GiftCardSaleMode::AdminOnly));

        self::assertFalse($checker->canBeBoughtIn($this->createChannel()));
    }

    public function testAChannelWithNoConfigurationAtAllStillSellsGiftCards(): void
    {
        // The upgrade path: a shop that never opened the configuration screen must keep selling.
        $checker = $this->createChecker(null);

        self::assertTrue($checker->canBeBoughtIn($this->createChannel()));
    }

    public function testAFreshConfigurationSellsGiftCards(): void
    {
        // The model default, not just the provider's null case - a channel configured for its code
        // length alone has said nothing about sales and must not lose them.
        $checker = $this->createChecker(new GiftCardConfiguration());

        self::assertTrue($checker->canBeBoughtIn($this->createChannel()));
    }

    private function createChecker(?GiftCardConfigurationInterface $configuration): GiftCardPurchaseChecker
    {
        $provider = $this->createMock(GiftCardConfigurationProviderInterface::class);
        $provider->method('getForChannel')->willReturn($configuration);

        return new GiftCardPurchaseChecker($provider);
    }

    private function createConfiguration(GiftCardSaleMode $saleMode): GiftCardConfigurationInterface
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setSaleMode($saleMode);

        return $configuration;
    }

    private function createChannel(): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return $channel;
    }
}
