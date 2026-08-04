<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\EventListener;

use Madcoders\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;

/**
 * Fills in what an admin should not have to type when creating a gift card by hand: the code and,
 * where the channel's configuration says so, the expiry date.
 *
 * Both are only ever set when they are still empty, so an admin who *did* enter a code (importing a
 * batch of pre-printed cards, say) keeps it.
 */
final readonly class PrepareGiftCardOnCreateListener
{
    public function __construct(
        private GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        private GiftCardConfigurationProviderInterface $giftCardConfigurationProvider,
    ) {
    }

    public function __invoke(ResourceControllerEvent $event): void
    {
        $giftCard = $event->getSubject();
        if (!$giftCard instanceof GiftCardInterface) {
            return;
        }

        $channel = $giftCard->getChannel();
        $configuration = null === $channel ? null : $this->giftCardConfigurationProvider->getForChannel($channel);

        if (null === $giftCard->getCode() || '' === trim($giftCard->getCode())) {
            $giftCard->setCode($this->giftCardCodeGenerator->generate($configuration));
        }

        if (null === $giftCard->getCurrencyCode()) {
            $giftCard->setCurrencyCode($channel?->getBaseCurrency()?->getCode());
        }

        if (null === $giftCard->getExpiresAt()) {
            $giftCard->setExpiresAt($configuration?->calculateExpiryDate());
        }
    }
}
