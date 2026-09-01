<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Applicator;

use Madcoders\SyliusGiftCardPlugin\Applicator\GiftCardApplicator;
use Madcoders\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotRedeemableException;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCard;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\Order;

/**
 * The applicator is the gate every redemption passes through, and the only place the channel rule
 * is enforced. Until now nothing tested it.
 */
final class GiftCardApplicatorTest extends TestCase
{
    public function testItAppliesARedeemableCardAndReprocessesTheOrder(): void
    {
        $order = $this->createOrder('WEB');
        $giftCard = $this->createGiftCard('GIFT-A', 5_000, 'WEB');

        $processor = $this->createMock(OrderProcessorInterface::class);
        $processor->expects(self::once())->method('process')->with($order);

        $this->createApplicator($processor)->apply($order, $giftCard);

        self::assertTrue($order->hasGiftCard($giftCard));
    }

    public function testACardFromAnotherChannelIsRefused(): void
    {
        // Cards are channel-scoped so a face value cannot silently change store or currency. This
        // guard is one `if`, and it is the only thing enforcing the rule.
        //
        // Covered here rather than in Behat: a second channel makes the test application's shop
        // unable to resolve a channel by hostname, so the cart page 500s before the guard is
        // reached. The rule is what matters, and this exercises it directly.
        $order = $this->createOrder('WEB');
        $giftCard = $this->createGiftCard('GIFT-EU', 5_000, 'EUROPE');

        $this->expectException(ChannelMismatchException::class);

        $this->createApplicator()->apply($order, $giftCard);
    }

    public function testACardIsRefusedWhenTheOrderHasNoChannel(): void
    {
        $order = new Order();
        $giftCard = $this->createGiftCard('GIFT-A', 5_000, 'WEB');

        $this->expectException(ChannelMismatchException::class);

        $this->createApplicator()->apply($order, $giftCard);
    }

    public function testADisabledCardIsRefused(): void
    {
        $order = $this->createOrder('WEB');
        $giftCard = $this->createGiftCard('GIFT-A', 5_000, 'WEB');
        $giftCard->disable();

        $this->expectException(GiftCardNotRedeemableException::class);

        $this->createApplicator()->apply($order, $giftCard);
    }

    public function testAnExpiredCardIsRefused(): void
    {
        $order = $this->createOrder('WEB');
        $giftCard = $this->createGiftCard('GIFT-A', 5_000, 'WEB');
        $giftCard->setExpiresAt(new \DateTime('2000-01-01'));

        $this->expectException(GiftCardNotRedeemableException::class);

        $this->createApplicator()->apply($order, $giftCard);
    }

    public function testASpentOutCardIsRefused(): void
    {
        $order = $this->createOrder('WEB');
        $giftCard = $this->createGiftCard('GIFT-A', 5_000, 'WEB');
        $giftCard->debit(5_000);

        $this->expectException(GiftCardNotRedeemableException::class);

        $this->createApplicator()->apply($order, $giftCard);
    }

    public function testAnUnknownCodeIsRefused(): void
    {
        $order = $this->createOrder('WEB');

        $this->expectException(GiftCardNotFoundException::class);

        $this->createApplicator()->apply($order, 'GIFT-NOPE');
    }

    public function testApplyingTheSameCardTwiceAppliesItOnce(): void
    {
        $order = $this->createOrder('WEB');
        $giftCard = $this->createGiftCard('GIFT-A', 5_000, 'WEB');

        $applicator = $this->createApplicator();

        // The return value is what separates a redemption from a re-submission. Callers that treat
        // the second as the first - the rate limiter did - hand out credit for doing nothing, and
        // this call costs the card nothing and can be repeated for ever.
        self::assertTrue($applicator->apply($order, $giftCard));
        self::assertFalse($applicator->apply($order, $giftCard));

        self::assertCount(1, $order->getGiftCards());
    }

    public function testACardRemovedAndAppliedAgainIsANewApplication(): void
    {
        $order = $this->createOrder('WEB');
        $giftCard = $this->createGiftCard('GIFT-A', 5_000, 'WEB');

        $applicator = $this->createApplicator();
        $applicator->apply($order, $giftCard);
        $applicator->remove($order, $giftCard);

        // Which is why "newly applied" cannot be the whole defence: it is still repeatable, just at
        // the cost of a round trip. The limiter caps how often a success may forgive failures.
        self::assertTrue($applicator->apply($order, $giftCard));
    }

    public function testRemovingACardThatIsNotOnTheOrderIsRefusedWithoutConsultingTheRepository(): void
    {
        // The repository must not be asked, because "no such card" versus "removed" would tell an
        // anonymous caller which codes exist - and a gift card code is bearer money.
        $order = $this->createOrder('WEB');

        $repository = $this->createMock(GiftCardRepositoryInterface::class);
        $repository->expects(self::never())->method('findOneByCode');

        $this->expectException(GiftCardNotFoundException::class);

        $this->createApplicator(null, $repository)->remove($order, 'GIFT-SOMEBODY-ELSES');
    }

    public function testRemovingAnAppliedCardTakesItOffTheOrder(): void
    {
        $order = $this->createOrder('WEB');
        $giftCard = $this->createGiftCard('GIFT-A', 5_000, 'WEB');

        $applicator = $this->createApplicator();
        $applicator->apply($order, $giftCard);
        $applicator->remove($order, 'GIFT-A');

        self::assertCount(0, $order->getGiftCards());
    }

    private function createApplicator(
        ?OrderProcessorInterface $processor = null,
        ?GiftCardRepositoryInterface $repository = null,
    ): GiftCardApplicator {
        $repository ??= $this->createMock(GiftCardRepositoryInterface::class);

        return new GiftCardApplicator(
            $repository,
            $processor ?? $this->createMock(OrderProcessorInterface::class),
        );
    }

    private function createOrder(string $channelCode): Order
    {
        $order = new Order();
        $order->setChannel($this->createChannel($channelCode));

        return $order;
    }

    private function createChannel(string $code): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);

        return $channel;
    }

    private function createGiftCard(string $code, int $amount, string $channelCode): GiftCardInterface
    {
        $giftCard = new GiftCard();
        $giftCard->setCode($code);
        $giftCard->setInitialAmount($amount);
        $giftCard->setChannel($this->createChannel($channelCode));

        return $giftCard;
    }
}
