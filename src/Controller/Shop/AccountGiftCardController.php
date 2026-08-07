<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Controller\Shop;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * "My gift cards" in the customer account.
 *
 * Shows two lists, which is the whole point of the two-customer model: the cards this customer
 * *bought* (which they may have given away) and the cards this customer *uses* (which somebody else
 * may have paid for). Only the second list has a balance worth watching.
 */
final readonly class AccountGiftCardController
{
    public function __construct(
        private CustomerContextInterface $customerContext,
        private GiftCardRepositoryInterface $giftCardRepository,
        private Environment $twig,
    ) {
    }

    public function indexAction(): Response
    {
        $customer = $this->getCustomer();

        return new Response($this->twig->render(
            '@MadcodersSyliusGiftCardPlugin/shop/account/gift_card/index.html.twig',
            [
                'redeemed_gift_cards' => $this->giftCardRepository->findByRedeemer($customer),
                'purchased_gift_cards' => $this->giftCardRepository->findByPurchaser($customer),
            ],
        ));
    }

    public function showAction(int $id): Response
    {
        $customer = $this->getCustomer();

        $giftCard = $this->giftCardRepository->find($id);
        if (!$giftCard instanceof GiftCardInterface) {
            throw new NotFoundHttpException(sprintf('There is no gift card with id %d.', $id));
        }

        // A gift card code is bearer-like: anyone holding it can spend it. So the account view is
        // restricted to the two customers the card actually belongs to - otherwise incrementing the
        // id in the URL would walk the shop's entire gift card list, codes and all.
        if (!$this->belongsTo($giftCard, $customer)) {
            throw new AccessDeniedHttpException('This gift card does not belong to you.');
        }

        return new Response($this->twig->render(
            '@MadcodersSyliusGiftCardPlugin/shop/account/gift_card/show.html.twig',
            ['gift_card' => $giftCard],
        ));
    }

    private function belongsTo(GiftCardInterface $giftCard, CustomerInterface $customer): bool
    {
        foreach ([$giftCard->getRedeemer(), $giftCard->getPurchaser()] as $linkedCustomer) {
            if (null !== $linkedCustomer && $linkedCustomer->getId() === $customer->getId()) {
                return true;
            }
        }

        return false;
    }

    private function getCustomer(): CustomerInterface
    {
        $customer = $this->customerContext->getCustomer();

        // The firewall keeps anonymous visitors out of /account, so this only trips if a host
        // application has loosened that.
        if (!$customer instanceof CustomerInterface) {
            throw new AccessDeniedHttpException('You must be logged in to see your gift cards.');
        }

        return $customer;
    }
}
