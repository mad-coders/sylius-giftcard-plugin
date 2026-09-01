<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Controller\Shop;

use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Madcoders\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotRedeemableException;
use Madcoders\SyliusGiftCardPlugin\Form\Type\GiftCardCodeType;
use Madcoders\SyliusGiftCardPlugin\RateLimiter\GiftCardRedemptionLimiterInterface;
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
    /**
     * Flash types the plugin's own panel renders, rather than Sylius' 'success' and 'error'.
     *
     * The checkout steps do not render flashes at all - only the cart and the summary step do - so a
     * refusal on the payment step was silent, and the unread message then surfaced on whichever
     * later page happened to render flashes, attached to the wrong action. The panel renders these
     * itself, so the customer gets the same answer wherever they redeem from.
     */
    public const string FLASH_SUCCESS = 'madcoders_gift_card_success';

    public const string FLASH_ERROR = 'madcoders_gift_card_error';

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
        /**
         * Null when the host has not installed symfony/rate-limiter, or has switched the limiter
         * off. Redemption then works exactly as it did before, unthrottled - see
         * docs/adr-log/0012-rate-limiting-gift-card-redemption.md.
         */
        private ?GiftCardRedemptionLimiterInterface $redemptionLimiter = null,
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
            $this->addFlash($request, self::FLASH_ERROR, 'madcoders_sylius_gift_card.cart.code_required');

            return $this->redirectBack($request, $cart);
        }

        $submittedCode = $form->get('code')->getData();
        $code = is_string($submittedCode) ? trim($submittedCode) : '';

        if ('' === $code) {
            $this->addFlash($request, self::FLASH_ERROR, 'madcoders_sylius_gift_card.cart.code_required');

            return $this->redirectBack($request, $cart);
        }

        // Asked before the applicator is, so a run of guesses never reaches the repository at all.
        if (true === $this->redemptionLimiter?->isBlocked($request)) {
            $this->addFlash($request, self::FLASH_ERROR, 'madcoders_sylius_gift_card.cart.too_many_attempts');

            return $this->redirectBack($request, $cart);
        }

        try {
            $this->giftCardApplicator->apply($cart, $code);
        } catch (GiftCardNotFoundException | GiftCardNotRedeemableException | ChannelMismatchException) {
            // One message for all three, deliberately. Saying "there is no such code" for one and
            // "this card is expired" for another tells an anonymous caller which codes are real,
            // which is the fact gift card codes exist to protect - the same reason removeAction
            // never consults the repository. Where the distinction is genuinely useful, the account
            // page gives it to the customer the card belongs to, behind a login.
            $this->redemptionLimiter?->recordFailure($request);
            $this->addFlash($request, self::FLASH_ERROR, 'madcoders_sylius_gift_card.cart.not_usable');

            return $this->redirectBack($request, $cart);
        }

        $this->orderManager->flush();

        // A code that works clears whatever this client got wrong before it: the limiter is there to
        // stop guessing, and somebody holding a real card is not guessing.
        $this->redemptionLimiter?->clear($request);
        $this->addFlash($request, self::FLASH_SUCCESS, 'madcoders_sylius_gift_card.cart.applied');

        return $this->redirectBack($request, $cart);
    }

    /**
     * Not rate limited, on purpose: removal is resolved against the cart's own cards and never
     * consults the repository, so repeating it cannot tell anybody which codes exist.
     */
    public function removeAction(Request $request, string $code): RedirectResponse
    {
        $cart = $this->getCart();
        if (null === $cart) {
            return $this->redirectToCart();
        }

        if (!$this->isCsrfTokenValid('madcoders_sylius_gift_card_remove', (string) $request->request->get('_token'))) {
            return $this->redirectBack($request, $cart);
        }

        try {
            $this->giftCardApplicator->remove($cart, $code);
        } catch (GiftCardNotFoundException) {
            // Deliberately the same outcome as a successful removal: the card was not on this cart,
            // and saying whether the code exists at all would leak which codes are real.
            return $this->redirectBack($request, $cart);
        }

        $this->orderManager->flush();
        $this->addFlash($request, self::FLASH_SUCCESS, 'madcoders_sylius_gift_card.cart.removed');

        return $this->redirectBack($request, $cart);
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
    private function redirectBack(Request $request, ?OrderInterface $cart): RedirectResponse
    {
        // A checkout step with no saved cart behind it is a 404 - Sylius looks the cart up by id and
        // the fallback cart context hands back an unsaved order. That happens when the session
        // expires with a checkout page open, which is exactly when the customer is least able to
        // understand a 404. The cart page handles having nothing to show.
        if (null === $cart || null === $cart->getId()) {
            return $this->redirectToCart();
        }

        // Read through all() rather than get()/getString(): both throw a 400 on a non-scalar value
        // before any cast can run, and `_return_to[]=cart` should fall back to the cart like every
        // other unrecognised value rather than becoming a bad request.
        $raw = $request->request->all()['_return_to'] ?? null;
        $key = is_string($raw) ? $raw : 'cart';

        return new RedirectResponse(
            $this->urlGenerator->generate(self::RETURN_ROUTES[$key] ?? self::RETURN_ROUTES['cart']),
        );
    }

    private function redirectToCart(): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate(self::RETURN_ROUTES['cart']));
    }
}
