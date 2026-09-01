<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\DependencyInjection;

use Madcoders\SyliusGiftCardPlugin\Controller\Shop\GiftCardCartController;
use Madcoders\SyliusGiftCardPlugin\DependencyInjection\MadcodersSyliusGiftCardExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * What a host application can say about the redemption limiter, and what it gets if it says nothing.
 */
final class MadcodersSyliusGiftCardExtensionTest extends TestCase
{
    public function testTheLimiterIsOnByDefault(): void
    {
        // The default has to be on. A plugin that ships an unthrottled endpoint for bearer money and
        // waits to be asked to protect it protects only the hosts that already knew to ask.
        $container = $this->load([]);

        self::assertTrue($container->hasDefinition('madcoders_sylius_gift_card.rate_limiter.redemption'));
        self::assertSame(10, $container->getParameter('madcoders_sylius_gift_card.redemption_rate_limit.limit'));
        self::assertSame('15 minutes', $container->getParameter('madcoders_sylius_gift_card.redemption_rate_limit.interval'));
    }

    public function testTheThresholdAndWindowAreTheHostApplicationsToChoose(): void
    {
        $container = $this->load([['redemption_rate_limit' => ['limit' => 25, 'interval' => '1 hour']]]);

        self::assertSame(25, $container->getParameter('madcoders_sylius_gift_card.redemption_rate_limit.limit'));
        self::assertSame('1 hour', $container->getParameter('madcoders_sylius_gift_card.redemption_rate_limit.interval'));
    }

    public function testAHostCanSwitchTheLimiterOff(): void
    {
        $container = $this->load([['redemption_rate_limit' => ['enabled' => false]]]);

        // Nothing registered, rather than a limiter that lets everything through: the controller's
        // argument is optional, so an absent service *is* the off switch.
        self::assertFalse($container->hasDefinition('madcoders_sylius_gift_card.rate_limiter.redemption'));
        self::assertFalse($container->hasParameter('madcoders_sylius_gift_card.redemption_rate_limit.limit'));
    }

    public function testTheControllerTakesTheLimiterWithoutRequiringIt(): void
    {
        // The argument has to stay optional, or switching the limiter off - or installing the plugin
        // without symfony/rate-limiter - would leave the container unable to build the controller.
        $container = $this->load([['redemption_rate_limit' => ['enabled' => false]]]);

        $arguments = $container->getDefinition(GiftCardCartController::class)->getArguments();
        $limiterArgument = end($arguments);

        self::assertInstanceOf(Reference::class, $limiterArgument);
        self::assertSame('madcoders_sylius_gift_card.rate_limiter.redemption', (string) $limiterArgument);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $limiterArgument->getInvalidBehavior());
    }

    public function testAThresholdThatWouldRefuseEverybodyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([['redemption_rate_limit' => ['limit' => 0]]]);
    }

    /** @param list<array<string, mixed>> $configs */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new MadcodersSyliusGiftCardExtension())->load($configs, $container);

        return $container;
    }
}
