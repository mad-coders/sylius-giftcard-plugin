<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class GiftCardContext implements Context
{
    /** @param ExampleFactoryInterface<GiftCardInterface> $giftCardExampleFactory */
    public function __construct(
        private readonly SharedStorageInterface $sharedStorage,
        private readonly ExampleFactoryInterface $giftCardExampleFactory,
        private readonly ObjectManager $giftCardManager,
    ) {
    }

    /**
     * @Given the store has a gift card :code worth :amount
     */
    public function theStoreHasAGiftCardWorth(string $code, string $amount): void
    {
        $this->createGiftCard($code, $amount);
    }

    /**
     * @Given the store has an expired gift card :code worth :amount
     */
    public function theStoreHasAnExpiredGiftCardWorth(string $code, string $amount): void
    {
        $this->createGiftCard($code, $amount, ['expires_at' => new \DateTime('-1 day')]);
    }

    /**
     * @Given the store has a disabled gift card :code worth :amount
     */
    public function theStoreHasADisabledGiftCardWorth(string $code, string $amount): void
    {
        $this->createGiftCard($code, $amount, ['enabled' => false]);
    }

    /**
     * @Given the store has a gift card :code worth :amount with :remaining left
     */
    public function theStoreHasAGiftCardWorthWithLeft(string $code, string $amount, string $remaining): void
    {
        $initialAmount = self::toMinorUnits($amount);

        $this->createGiftCard($code, $amount, [
            'spent_amount' => $initialAmount - self::toMinorUnits($remaining),
        ]);
    }

    /** @param array<string, mixed> $options */
    private function createGiftCard(string $code, string $amount, array $options = []): GiftCardInterface
    {
        $giftCard = $this->giftCardExampleFactory->create(array_merge([
            'code' => $code,
            'initial_amount' => self::toMinorUnits($amount),
            'channel' => $this->getChannel(),
        ], $options));

        $this->giftCardManager->persist($giftCard);
        $this->giftCardManager->flush();

        $this->sharedStorage->set('gift_card', $giftCard);

        return $giftCard;
    }

    private function getChannel(): ChannelInterface
    {
        /** @var ChannelInterface $channel */
        $channel = $this->sharedStorage->get('channel');

        return $channel;
    }

    /**
     * Turns a written price such as "$100.00" or "100" into minor units, so features can be
     * expressed in the money a shopper would recognise.
     */
    private static function toMinorUnits(string $amount): int
    {
        $normalised = preg_replace('/[^0-9.]/', '', $amount) ?? '';

        return (int) round(((float) $normalised) * 100);
    }
}
