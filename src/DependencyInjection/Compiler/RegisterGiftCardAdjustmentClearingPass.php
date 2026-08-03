<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\DependencyInjection\Compiler;

use Madcoders\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds the gift card adjustment type to the list Sylius' OrderAdjustmentsClearer wipes at the start
 * of every order processing run.
 *
 * This is load-bearing, not tidiness. The clearer runs at priority 60, before promotions (20) and
 * taxes (10); the gift card processor runs last. If yesterday's gift card adjustments survived into
 * this run, every processor in between would compute against an order total that had already been
 * discounted - promotions with a total threshold and tax calculations would both come out wrong,
 * and the discount would compound on each reprocess.
 */
final class RegisterGiftCardAdjustmentClearingPass implements CompilerPassInterface
{
    private const string PARAMETER = 'sylius.order_processing.adjustment_clearing_types';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAMETER)) {
            return;
        }

        /** @var list<string> $types */
        $types = (array) $container->getParameter(self::PARAMETER);

        if (in_array(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT, $types, true)) {
            return;
        }

        $types[] = AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT;

        $container->setParameter(self::PARAMETER, $types);
    }
}
