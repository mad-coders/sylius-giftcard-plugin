<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Webmozart\Assert\Assert;

final class GiftCardContext implements Context
{
    /**
     * @param ExampleFactoryInterface<GiftCardInterface>              $giftCardExampleFactory
     * @param ExampleFactoryInterface<GiftCardConfigurationInterface> $giftCardConfigurationExampleFactory
     */
    public function __construct(
        private readonly SharedStorageInterface $sharedStorage,
        private readonly ExampleFactoryInterface $giftCardExampleFactory,
        private readonly ObjectManager $giftCardManager,
        private readonly ExampleFactoryInterface $giftCardConfigurationExampleFactory,
        private readonly GiftCardApplicatorInterface $giftCardApplicator,
    ) {
    }

    /**
     * @Given the channel issues gift card codes :length characters long prefixed with :prefix
     */
    public function theChannelIssuesGiftCardCodesLongPrefixedWith(int $length, string $prefix): void
    {
        $this->createConfiguration(['code_length' => $length, 'code_prefix' => $prefix]);
    }

    /**
     * @Given the channel's gift cards are valid for :period
     */
    public function theChannelsGiftCardsAreValidFor(string $period): void
    {
        $this->createConfiguration(['validity_period' => $period]);
    }

    /**
     * @Given the channel's gift cards are valid for the unparseable period :period
     */
    public function theChannelsGiftCardsAreValidForTheUnparseablePeriod(string $period): void
    {
        // A misconfigured period must not hand out a card that is already expired - a card issued
        // dead is worse than one that never expires, because the customer only finds out at the
        // till.
        //
        // The prefix rides along so the scenario can prove the configuration was actually consulted.
        // Without it, "no expiry date" would also be the answer if the configuration were ignored
        // altogether, and the test would pass while testing nothing.
        $this->createConfiguration(['validity_period' => $period, 'code_prefix' => 'BADCFG-']);
    }

    /** @param array<string, mixed> $options */
    private function createConfiguration(array $options): GiftCardConfigurationInterface
    {
        $configuration = $this->giftCardConfigurationExampleFactory->create(array_merge([
            'channel' => $this->getChannel(),
        ], $options));

        $this->giftCardManager->persist($configuration);
        $this->giftCardManager->flush();

        return $configuration;
    }

    /**
     * @Given the store has a gift card :code worth :amount
     */
    public function theStoreHasAGiftCardWorth(string $code, string $amount): void
    {
        $this->createGiftCard($code, $amount);
    }

    /**
     * @Given the gift card :code worth :amount is already on my cart
     */
    public function theGiftCardWorthIsAlreadyOnMyCart(string $code, string $amount): void
    {
        // Applied straight to the order rather than through the redeem field, because the scenario
        // that needs this is about what the redeem field does when the card is *already* there. Going
        // through the field first would spend the one forgiveness the window allows, and the bug the
        // scenario exists to catch would then be masked by the cap rather than caught by the guard.
        $giftCard = $this->createGiftCard($code, $amount);

        $cart = $this->sharedStorage->get('order');
        Assert::isInstanceOf($cart, OrderInterface::class);

        $this->giftCardApplicator->apply($cart, $giftCard);

        $this->giftCardManager->flush();
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

    /**
     * @Given the store has a gift card :code worth :amount in the :channel channel
     */
    public function theStoreHasAGiftCardWorthInTheChannel(string $code, string $amount, ChannelInterface $channel): void
    {
        $this->createGiftCard($code, $amount, ['channel' => $channel]);
    }

    /**
     * @Given the store has a gift card :code worth :amount used by :customer
     */
    public function theStoreHasAGiftCardWorthUsedBy(string $code, string $amount, CustomerInterface $customer): void
    {
        $this->createGiftCard($code, $amount, ['redeemer' => $customer]);
    }

    /**
     * @Given the store has a gift card :code worth :amount bought by :customer
     */
    public function theStoreHasAGiftCardWorthBoughtBy(string $code, string $amount, CustomerInterface $customer): void
    {
        $this->createGiftCard($code, $amount, ['purchaser' => $customer]);
    }

    /**
     * @Given the store has a gift card :code worth :amount with :remaining left used by :customer
     */
    public function theStoreHasAGiftCardWorthWithLeftUsedBy(
        string $code,
        string $amount,
        string $remaining,
        CustomerInterface $customer,
    ): void {
        $this->createGiftCard($code, $amount, [
            'spent_amount' => self::toMinorUnits($amount) - self::toMinorUnits($remaining),
            'redeemer' => $customer,
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

        // The admin scenarios address cards by code but the show page routes by id, so remember
        // the mapping as cards are created.
        $ids = $this->sharedStorage->has('gift_card_ids') ? $this->sharedStorage->get('gift_card_ids') : [];
        Assert::isArray($ids);
        $ids[(string) $giftCard->getCode()] = $giftCard->getId();
        $this->sharedStorage->set('gift_card_ids', $ids);

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
