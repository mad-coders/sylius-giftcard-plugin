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

    public function testTheCounterIsLockedWhereTheHostHasALockToUse(): void
    {
        // Without a lock the window is a read-modify-write with no mutual exclusion, so concurrent
        // attempts all read the same count and all store one more: the real allowance per round trip
        // becomes the number of PHP workers. Optional, because symfony/lock is a suggest too.
        $arguments = $this->load([])
            ->getDefinition('madcoders_sylius_gift_card.rate_limiter.redemption_factory')
            ->getArguments()
        ;
        $lockArgument = end($arguments);

        self::assertInstanceOf(Reference::class, $lockArgument);
        self::assertSame('lock.factory', (string) $lockArgument);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $lockArgument->getInvalidBehavior());
    }

    public function testTheShopWideWindowWatchesButDoesNotBlockUnlessAsked(): void
    {
        $container = $this->load([]);

        self::assertSame(200, $container->getParameter('madcoders_sylius_gift_card.redemption_rate_limit.shop_limit'));
        self::assertFalse($container->getParameter('madcoders_sylius_gift_card.redemption_rate_limit.shop_blocks'));
        self::assertSame('fixed_window', $container->getParameter('madcoders_sylius_gift_card.redemption_rate_limit.shop_policy'));
    }

    public function testAShopLimitOfZeroStopsWatchingRatherThanRefusingEverybody(): void
    {
        // A limit of zero read literally would be a window nobody can ever consume from, which with
        // shop_blocks on would refuse every redemption in the shop for ever.
        $container = $this->load([['redemption_rate_limit' => ['shop_limit' => 0]]]);

        self::assertSame('no_limit', $container->getParameter('madcoders_sylius_gift_card.redemption_rate_limit.shop_policy'));
        self::assertSame(1, $container->getParameter('madcoders_sylius_gift_card.redemption_rate_limit.shop_limit'));
    }

    public function testAThresholdThatWouldRefuseEverybodyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([['redemption_rate_limit' => ['limit' => 0]]]);
    }

    public function testAWindowNobodyCanParseIsRejectedHereRatherThanAtTheCheckout(): void
    {
        // RateLimiterFactory parses the interval in its constructor and the factory is lazy, so an
        // unparseable window passes lint:container and then 500s on the first customer to type a
        // code. Rejecting it while the container is built is the difference between a deploy that
        // fails and a shop that cannot take gift cards.
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/fifteen minutes/');

        $this->load([['redemption_rate_limit' => ['interval' => 'fifteen minutes']]]);
    }

    public function testAHostWithoutTheComponentBootsUnthrottled(): void
    {
        $container = $this->load([], rateLimiterAvailable: false);

        self::assertFalse($container->hasDefinition('madcoders_sylius_gift_card.rate_limiter.redemption'));
    }

    public function testAskingForTheLimiterWithoutTheComponentIsAnErrorRatherThanASilentNoOp(): void
    {
        // Degrading quietly is right for the default and wrong for an explicit request: a shop owner
        // who turned the limiter on and forgot the composer require would otherwise get no limiter,
        // no exception, no log, and documentation telling them they are protected.
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/composer require symfony\/rate-limiter/');

        $this->load([['redemption_rate_limit' => ['enabled' => true]]], rateLimiterAvailable: false);
    }

    /** @param list<array<string, mixed>> $configs */
    private function load(array $configs, ?bool $rateLimiterAvailable = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new MadcodersSyliusGiftCardExtension($rateLimiterAvailable))->load($configs, $container);

        return $container;
    }
}
