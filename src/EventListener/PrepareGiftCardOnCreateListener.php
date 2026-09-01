<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\EventListener;

use Madcoders\SyliusGiftCardPlugin\Calculator\GiftCardExpiryCalculatorInterface;
use Madcoders\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;

/**
 * Fills in what a caller should not have to supply when creating a gift card: the code, the currency
 * and the expiry date.
 *
 * Each is only ever set when it is still empty, so a caller who *did* supply a code (importing a
 * batch of pre-printed cards, say) or a date keeps it.
 *
 * This runs on the way into the database, after validation. The admin form has already filled the
 * expiry in by then - {@see \Madcoders\SyliusGiftCardPlugin\Form\Type\GiftCardType} pre-fills it so
 * the administrator can see and change the date, and refuses to save it blank. What is left for
 * this listener is every path that renders no form: a resource created through the API, or a
 * console command. None of them may produce a card without an expiry date, so none of them is
 * allowed to reach the database without passing here first.
 */
final readonly class PrepareGiftCardOnCreateListener
{
    public function __construct(
        private GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        private GiftCardConfigurationProviderInterface $giftCardConfigurationProvider,
        private GiftCardExpiryCalculatorInterface $giftCardExpiryCalculator,
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
            $giftCard->setExpiresAt($this->giftCardExpiryCalculator->calculate($configuration));
        }
    }
}
