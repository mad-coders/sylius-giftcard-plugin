<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Controller\Shop;

use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Madcoders\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotRedeemableException;
use Madcoders\SyliusGiftCardPlugin\Form\Type\GiftCardCodeType;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Applies and removes gift cards on the current cart.
 *
 * Both actions always redirect back to the cart: the outcome is a flash message plus an updated
 * total, which is what the customer needs to see either way.
 */
final readonly class GiftCardCartController
{
    public function __construct(
        private CartContextInterface $cartContext,
        private GiftCardApplicatorInterface $giftCardApplicator,
        private FormFactoryInterface $formFactory,
        private ObjectManager $orderManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function applyAction(Request $request): RedirectResponse
    {
        $cart = $this->getCart();
        if (null === $cart) {
            return $this->redirectToCart();
        }

        $form = $this->formFactory->create(GiftCardCodeType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash($request, 'error', 'madcoders_sylius_gift_card.cart.code_required');

            return $this->redirectToCart();
        }

        $submittedCode = $form->get('code')->getData();
        $code = is_string($submittedCode) ? trim($submittedCode) : '';

        if ('' === $code) {
            $this->addFlash($request, 'error', 'madcoders_sylius_gift_card.cart.code_required');

            return $this->redirectToCart();
        }

        try {
            $this->giftCardApplicator->apply($cart, $code);
        } catch (GiftCardNotFoundException) {
            $this->addFlash($request, 'error', 'madcoders_sylius_gift_card.cart.not_found');

            return $this->redirectToCart();
        } catch (GiftCardNotRedeemableException) {
            $this->addFlash($request, 'error', 'madcoders_sylius_gift_card.cart.not_redeemable');

            return $this->redirectToCart();
        } catch (ChannelMismatchException) {
            $this->addFlash($request, 'error', 'madcoders_sylius_gift_card.cart.channel_mismatch');

            return $this->redirectToCart();
        }

        $this->orderManager->flush();
        $this->addFlash($request, 'success', 'madcoders_sylius_gift_card.cart.applied');

        return $this->redirectToCart();
    }

    public function removeAction(Request $request, string $code): RedirectResponse
    {
        $cart = $this->getCart();
        if (null === $cart) {
            return $this->redirectToCart();
        }

        try {
            $this->giftCardApplicator->remove($cart, $code);
        } catch (GiftCardNotFoundException) {
            $this->addFlash($request, 'error', 'madcoders_sylius_gift_card.cart.not_found');

            return $this->redirectToCart();
        }

        $this->orderManager->flush();
        $this->addFlash($request, 'success', 'madcoders_sylius_gift_card.cart.removed');

        return $this->redirectToCart();
    }

    private function getCart(): ?OrderInterface
    {
        try {
            $cart = $this->cartContext->getCart();
        } catch (CartNotFoundException) {
            return null;
        }

        return $cart instanceof OrderInterface ? $cart : null;
    }

    private function addFlash(Request $request, string $type, string $message): void
    {
        $session = $request->getSession();

        if ($session instanceof Session) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    private function redirectToCart(): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_cart_summary'));
    }
}
