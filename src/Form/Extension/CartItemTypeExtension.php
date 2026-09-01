<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Form\Extension;

use Madcoders\SyliusGiftCardPlugin\Form\Type\GiftCardAmountType;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Sylius\Bundle\OrderBundle\Form\Type\CartItemType;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Asks the customer what the gift card should be worth and what it should say.
 *
 * Extends the cart item form rather than adding a form of its own, so the amount and the message
 * travel with the item through whatever puts it in the cart - Sylius' add-to-cart component, a
 * host application's own controller, or a hand-written POST.
 *
 * The fields only exist for a gift card product in a channel that lets the customer choose; the cart
 * page's quantity form is built without a `product` option and so never gets them, which is what
 * stops editing a quantity from clearing the choice.
 */
final class CartItemTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        private readonly GiftCardConfigurationProviderInterface $giftCardConfigurationProvider,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $product = $options['product'] ?? null;

        // A host application that has not applied ProductTrait simply sells no gift cards.
        if (!$product instanceof ProductInterface || !$product->isGiftCard()) {
            return;
        }

        // The message is offered whatever the amount mode is: a shop selling a fixed-price card
        // still sells a present.
        $builder->add('giftCardMessage', TextareaType::class, [
            'label' => 'madcoders_sylius_gift_card.ui.gift_card_message',
            'help' => 'madcoders_sylius_gift_card.ui.gift_card_message_help',
            'help_translation_parameters' => ['%limit%' => GiftCardInterface::CUSTOM_MESSAGE_MAX_LENGTH],
            'required' => false,
            'attr' => [
                'rows' => 3,
                // Advisory: the constraint below is what actually holds, because an attribute is
                // only a suggestion to a browser that chooses to honour it.
                'maxlength' => GiftCardInterface::CUSTOM_MESSAGE_MAX_LENGTH,
            ],
            'constraints' => [
                new Length(
                    max: GiftCardInterface::CUSTOM_MESSAGE_MAX_LENGTH,
                    maxMessage: 'madcoders_sylius_gift_card.cart_item.message.too_long',
                    groups: ['Default', 'sylius'],
                ),
            ],
        ]);

        $channel = $this->currentChannel();
        if (null === $channel) {
            return;
        }

        $configuration = $this->giftCardConfigurationProvider->getForChannel($channel);
        if (null === $configuration || !$configuration->allowsCustomerChosenAmount()) {
            return;
        }

        $builder->add('giftCardAmount', GiftCardAmountType::class, [
            'configuration' => $configuration,
            'currency_code' => $channel->getBaseCurrency()?->getCode() ?? '',
            'locale_code' => $this->currentLocale(),
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        // The base type, so both Sylius' shop and core cart item forms inherit the fields.
        return [CartItemType::class];
    }

    private function currentChannel(): ?ChannelInterface
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return null;
        }

        return $channel instanceof ChannelInterface ? $channel : null;
    }

    private function currentLocale(): ?string
    {
        try {
            return $this->localeContext->getLocaleCode();
        } catch (LocaleNotFoundException) {
            return null;
        }
    }
}
