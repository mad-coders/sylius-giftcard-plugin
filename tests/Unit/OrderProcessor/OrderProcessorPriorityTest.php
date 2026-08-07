<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\OrderProcessor;

use PHPUnit\Framework\TestCase;

/**
 * Guards where the gift card order processor sits in Sylius' processor chain.
 *
 * This is configuration rather than code, but getting it wrong costs real money and does so
 * silently. A gift card settles against the payment Sylius' payment processor has already sized
 * from the order total, so it has to run below it; and it has to run below taxes so that what it
 * settles against is the real amount owed.
 *
 * Both priorities are read from the real XML - ours and Sylius' - so the test fails if either side
 * moves, including when a Sylius upgrade renumbers its own chain.
 */
final class OrderProcessorPriorityTest extends TestCase
{
    public function testItRunsAfterSyliusPaymentProcessorSoItCanSettleThePayment(): void
    {
        // The chain is a priority queue, highest first. Sylius' payment processor sets the payment
        // from the order total; this one then takes what the gift cards cover off that payment. It
        // therefore has to run *after* it - the opposite of what a discount would need.
        self::assertLessThan(
            self::syliusPriority('sylius.order_processing.order_payment_processor.checkout'),
            self::giftCardPriority(),
            'The gift card processor must run after Sylius\' payment processor, whose amount it '
            . 'settles. Running before it would leave the payment holding the full total, so the '
            . 'customer is charged in full while their card is still debited.',
        );
    }

    public function testItRunsAfterSyliusTaxesProcessor(): void
    {
        self::assertLessThan(
            self::syliusPriority('sylius.order_processing.order_taxes_processor'),
            self::giftCardPriority(),
            'The gift card processor must run after taxes, so a card settles against what the '
            . 'customer actually owes.',
        );
    }

    public function testItRunsAfterSyliusAdjustmentsClearer(): void
    {
        // The clearer is what makes reprocessing idempotent; if the gift card processor overtook
        // it, the discount would compound across runs.
        self::assertLessThan(
            self::syliusPriority('sylius.order_processing.order_adjustments_clearer'),
            self::giftCardPriority(),
            'Sylius\' adjustments clearer must run before the gift card processor.',
        );
    }

    private static function giftCardPriority(): int
    {
        return self::priorityOf(
            \dirname(__DIR__, 3) . '/config/services/order_processing.xml',
            'madcoders_sylius_gift_card.order_processing.order_gift_card_processor',
        );
    }

    private static function syliusPriority(string $serviceId): int
    {
        return self::priorityOf(
            \dirname(__DIR__, 3) . '/vendor/sylius/sylius/src/Sylius/Bundle/CoreBundle/Resources/config/services/order_processing.xml',
            $serviceId,
        );
    }

    private static function priorityOf(string $file, string $serviceId): int
    {
        self::assertFileExists($file);

        $xml = new \DOMDocument();
        self::assertTrue($xml->load($file));

        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('c', 'http://symfony.com/schema/dic/services');

        $nodes = $xpath->query(sprintf(
            '//c:service[@id="%s"]/c:tag[@name="sylius.order_processor"]/@priority',
            $serviceId,
        ));

        self::assertNotFalse($nodes);
        self::assertSame(
            1,
            $nodes->length,
            sprintf('Expected exactly one sylius.order_processor tag priority for "%s".', $serviceId),
        );

        $priority = $nodes->item(0);
        self::assertNotNull($priority);

        return (int) $priority->nodeValue;
    }
}
