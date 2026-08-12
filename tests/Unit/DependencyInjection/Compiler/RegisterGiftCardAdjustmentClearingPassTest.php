<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\DependencyInjection\Compiler;

use Madcoders\SyliusGiftCardPlugin\DependencyInjection\Compiler\RegisterGiftCardAdjustmentClearingPass;
use Madcoders\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The pass that makes processing idempotent.
 *
 * Sylius' adjustment clearer runs at priority 60, before promotions (20) and taxes (10), while the
 * gift card processor runs last. If a previous run's coverage survived into this one, every
 * processor in between would compute against an order that already had coverage on it, and the
 * coverage would compound on each reprocess.
 */
final class RegisterGiftCardAdjustmentClearingPassTest extends TestCase
{
    private const string PARAMETER = 'sylius.order_processing.adjustment_clearing_types';

    public function testItAddsTheGiftCardTypeToTheClearedList(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(self::PARAMETER, ['sylius_order_promotion', 'tax']);

        (new RegisterGiftCardAdjustmentClearingPass())->process($container);

        self::assertSame(
            ['sylius_order_promotion', 'tax', AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT],
            $container->getParameter(self::PARAMETER),
        );
    }

    public function testItKeepsTheTypesSyliusAlreadyClears(): void
    {
        // Replacing the list rather than appending to it would stop promotions and taxes being
        // cleared, which is a far worse bug than the one this pass exists to prevent.
        $container = new ContainerBuilder();
        $container->setParameter(self::PARAMETER, ['sylius_order_promotion', 'tax', 'shipping']);

        (new RegisterGiftCardAdjustmentClearingPass())->process($container);

        /** @var list<string> $types */
        $types = $container->getParameter(self::PARAMETER);

        self::assertContains('sylius_order_promotion', $types);
        self::assertContains('tax', $types);
        self::assertContains('shipping', $types);
    }

    public function testItDoesNotAddTheTypeTwice(): void
    {
        // Compiler passes can run more than once across a container build, and a duplicated entry
        // would have the clearer walk the adjustments an extra time on every single order process.
        $container = new ContainerBuilder();
        $container->setParameter(self::PARAMETER, ['tax']);

        $pass = new RegisterGiftCardAdjustmentClearingPass();
        $pass->process($container);
        $pass->process($container);

        /** @var list<string> $types */
        $types = $container->getParameter(self::PARAMETER);

        self::assertSame(['tax', AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT], $types);
    }

    public function testItLeavesAContainerWithoutTheParameterAlone(): void
    {
        // The guard that stops the plugin exploding in a host that is not a full Sylius Core
        // installation - the parameter belongs to CoreBundle, not to us.
        $container = new ContainerBuilder();

        (new RegisterGiftCardAdjustmentClearingPass())->process($container);

        self::assertFalse($container->hasParameter(self::PARAMETER));
    }
}
