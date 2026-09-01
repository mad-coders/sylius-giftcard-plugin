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
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Applies and removes gift cards on the current cart.
 *
 * Both actions redirect back to the page the customer was on - the cart, or whichever checkout step
 * carries the panel - because the outcome is a flash message plus an updated amount to pay, and
 * sending someone back to the cart from the middle of checkout loses their place.
 *
 * The target is chosen from the whitelist below by key, never from a submitted URL or the referer:
 * an endpoint that redirects wherever it is told is an open redirect, and this one is reachable
 * anonymously.
 */
final readonly class GiftCardCartController
{
    private const array RETURN_ROUTES = [
        'cart' => 'sylius_shop_cart_summary',
        'checkout_address' => 'sylius_shop_checkout_address',
        'checkout_shipping' => 'sylius_shop_checkout_select_shipping',
        'checkout_payment' => 'sylius_shop_checkout_select_payment',
        'checkout_complete' => 'sylius_shop_checkout_complete',
    ];

    public function __construct(
        private CartContextInterface $cartContext,
        private GiftCardApplicatorInterface $giftCardApplicator,
        private FormFactoryInterface $formFactory,
        private ObjectManager $orderManager,
        private UrlGeneratorInterface $urlGenerator,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function applyAction(Request $request): RedirectResponse
    {
        $cart = $this->getCart();
        if (null === $cart) {
            return $this->redirectBack($request);
        }

        $form = $this->formFactory->create(GiftCardCodeType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash($request, 'error', 'madcoders_sylius_gift_card.cart.code_required');

            return $this->redirectBack($request);
        }

        $submittedCode = $form->get('code')->getData();
        $code = is_string($submittedCode) ? trim($submittedCode) : '';

        if ('' === $code) {
            $this->addFlash($request, 'error', 'madcoders_sylius_gift_card.cart.code_required');

            return $this->redirectBack($request);
        }

        try {
            $this->giftCardApplicator->apply($cart, $code);
        } catch (GiftCardNotFoundException) {
            $this->addFlash($request, 'error', 'madcoders_sylius_gift_card.cart.not_found');

            return $this->redirectBack($request);
        } catch (GiftCardNotRedeemableException) {
            $this->addFlash($request, 'error', 'madcoders_sylius_gift_card.cart.not_redeemable');

            return $this->redirectBack($request);
        } catch (ChannelMismatchException) {
            $this->addFlash($request, 'error', 'madcoders_sylius_gift_card.cart.channel_mismatch');

            return $this->redirectBack($request);
        }

        $this->orderManager->flush();
        $this->addFlash($request, 'success', 'madcoders_sylius_gift_card.cart.applied');

        return $this->redirectBack($request);
    }

    public function removeAction(Request $request, string $code): RedirectResponse
    {
        $cart = $this->getCart();
        if (null === $cart) {
            return $this->redirectBack($request);
        }

        if (!$this->isCsrfTokenValid('madcoders_sylius_gift_card_remove', (string) $request->request->get('_token'))) {
            return $this->redirectBack($request);
        }

        try {
            $this->giftCardApplicator->remove($cart, $code);
        } catch (GiftCardNotFoundException) {
            // Deliberately the same outcome as a successful removal: the card was not on this cart,
            // and saying whether the code exists at all would leak which codes are real.
            return $this->redirectBack($request);
        }

        $this->orderManager->flush();
        $this->addFlash($request, 'success', 'madcoders_sylius_gift_card.cart.removed');

        return $this->redirectBack($request);
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

    private function isCsrfTokenValid(string $id, string $token): bool
    {
        return $this->csrfTokenManager->isTokenValid(new CsrfToken($id, $token));
    }

    /**
     * Back to whichever page carries the panel, defaulting to the cart.
     *
     * Only the keys of RETURN_ROUTES are accepted, so an unknown or forged value can do nothing
     * except send the customer to their cart.
     */
    private function redirectBack(Request $request): RedirectResponse
    {
        $key = (string) $request->request->get('_return_to', 'cart');

        return new RedirectResponse(
            $this->urlGenerator->generate(self::RETURN_ROUTES[$key] ?? self::RETURN_ROUTES['cart']),
        );
    }
}
