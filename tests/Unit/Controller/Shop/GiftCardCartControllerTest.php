<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Controller\Shop;

use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Madcoders\SyliusGiftCardPlugin\Controller\Shop\GiftCardCartController;
use Madcoders\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardsNotAcceptedOnOrderException;
use Madcoders\SyliusGiftCardPlugin\RateLimiter\GiftCardRedemptionLimiterInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Where the apply and remove endpoints send the customer afterwards.
 *
 * The panel now lives in the checkout as well as the cart, so the controller has to send people
 * back where they came from. It works that out from a whitelist key rather than a submitted URL or
 * the referer, because both endpoints are reachable anonymously and one that redirects wherever it
 * is told is an open redirect.
 */
final class GiftCardCartControllerTest extends TestCase
{
    public function testItSendsTheCustomerBackToTheCheckoutStepTheyCameFrom(): void
    {
        $response = $this->removeWithReturnTo('checkout_payment');

        self::assertSame('/checkout/select-payment', $response->getTargetUrl());
    }

    public function testItSendsTheCustomerBackToTheCartByDefault(): void
    {
        $response = $this->removeWithReturnTo(null);

        self::assertSame('/cart', $response->getTargetUrl());
    }

    public function testAForgedReturnTargetFallsBackToTheCart(): void
    {
        // The whole point of keying on a whitelist: anything unrecognised can only send the customer
        // to their own cart, never off-site.
        $response = $this->removeWithReturnTo('https://evil.example.com/phish');

        self::assertSame('/cart', $response->getTargetUrl());
    }

    public function testAnUnknownKeyFallsBackToTheCart(): void
    {
        $response = $this->removeWithReturnTo('checkout_somewhere_else');

        self::assertSame('/cart', $response->getTargetUrl());
    }

    public function testAnArrayReturnTargetFallsBackToTheCartRatherThanA400(): void
    {
        // InputBag::get() throws a BadRequestException on a non-scalar value, before any cast can
        // run, so `_return_to[]=cart` used to be a 400 rather than the documented fallback.
        $response = $this->removeWithParameters(['_token' => 'nope', '_return_to' => ['cart']]);

        self::assertSame('/cart', $response->getTargetUrl());
    }

    public function testItWillNotSendTheCustomerIntoCheckoutWithoutASavedCart(): void
    {
        // Sylius looks a checkout step's cart up by id, and its fallback cart context hands back an
        // unsaved order - so redirecting there would be a 404. This is what an expired session with
        // a checkout page left open looks like, which is the worst moment to show one.
        $response = $this->removeWithParameters(
            ['_token' => 'nope', '_return_to' => 'checkout_payment'],
            cartHasId: false,
        );

        self::assertSame('/cart', $response->getTargetUrl());
    }

    public function testARateLimitedClientIsRefusedWithoutTheCodeBeingLookedUpAtAll(): void
    {
        // The point of the limiter: a run of guesses must not reach the repository. If the applicator
        // is consulted first the shop is still answering the attacker's question, just more slowly.
        $applicator = $this->createMock(GiftCardApplicatorInterface::class);
        $applicator->expects(self::never())->method('apply');

        $request = $this->applyRequest();
        $response = $this->apply($request, $applicator, $this->limiterBlocking(true));

        self::assertSame('/cart', $response->getTargetUrl());
        self::assertSame(
            ['madcoders_sylius_gift_card.cart.too_many_attempts'],
            $this->flashes($request, GiftCardCartController::FLASH_ERROR),
        );
    }

    public function testAnUnknownCodeAndAnUnusableCardAreRefusedInTheSameWords(): void
    {
        // Two different exceptions, one message. A distinct message per cause is a code-existence
        // oracle on an endpoint anybody can post to.
        $notFound = $this->applyRequest();
        $notRedeemable = $this->applyRequest();

        $this->apply($notFound, $this->applicatorThrowing(new GiftCardNotFoundException('GIFT-NOPE')));
        $this->apply($notRedeemable, $this->applicatorThrowing(new ChannelMismatchException(null, null)));

        self::assertSame(
            $this->flashes($notFound, GiftCardCartController::FLASH_ERROR),
            $this->flashes($notRedeemable, GiftCardCartController::FLASH_ERROR),
        );
        self::assertSame(
            ['madcoders_sylius_gift_card.cart.not_usable'],
            $this->flashes($notFound, GiftCardCartController::FLASH_ERROR),
        );
    }

    public function testAFailedApplyCountsAgainstTheClientAndASuccessfulOneClearsTheTally(): void
    {
        $failing = $this->createMock(GiftCardRedemptionLimiterInterface::class);
        $failing->expects(self::once())->method('recordFailure');
        $failing->expects(self::never())->method('clear');

        $this->apply(
            $this->applyRequest(),
            $this->applicatorThrowing(new GiftCardNotFoundException('GIFT-NOPE')),
            $failing,
        );

        $succeeding = $this->createMock(GiftCardRedemptionLimiterInterface::class);
        $succeeding->expects(self::never())->method('recordFailure');
        $succeeding->expects(self::once())->method('clear');

        $this->apply($this->applyRequest(), $this->applicatorReturning(true), $succeeding);
    }

    public function testReSubmittingACardTheCartAlreadyHasDoesNotBuyBackTheAllowance(): void
    {
        // The bypass this guards: applying a card does not debit it, and addGiftCard() early-returns
        // on one the cart already has, so re-posting the same code succeeds, flushes and flashes just
        // like the first time. If that counted as a redemption, one $5 card would buy an attacker
        // unlimited guessing - nine wrong codes, then their own, for ever.
        $limiter = $this->createMock(GiftCardRedemptionLimiterInterface::class);
        $limiter->expects(self::never())->method('clear');

        $request = $this->applyRequest();
        $response = $this->apply($request, $this->applicatorReturning(false), $limiter);

        // Still a success as far as the customer is concerned - the card *is* on their cart.
        self::assertSame(
            ['madcoders_sylius_gift_card.cart.applied'],
            $this->flashes($request, GiftCardCartController::FLASH_SUCCESS),
        );
        self::assertSame('/cart', $response->getTargetUrl());
    }

    public function testABasketThatTakesNoGiftCardsGetsAMessageOfItsOwn(): void
    {
        // The one refusal that is allowed to be specific, because the applicator judges the basket
        // before it looks the code up - so it says the same thing for a real code and an invented
        // one. The customer can act on this, and "this code cannot be used" would send them looking
        // at their card instead of their basket.
        $request = $this->applyRequest();

        $this->apply($request, $this->applicatorThrowing(new GiftCardsNotAcceptedOnOrderException()));

        self::assertSame(
            ['madcoders_sylius_gift_card.cart.gift_card_cannot_pay_for_gift_card'],
            $this->flashes($request, GiftCardCartController::FLASH_ERROR),
        );
    }

    public function testABasketRefusalIsNotCountedAsAGuess(): void
    {
        // Nothing was guessed - the basket was refused - so spending the client's allowance on it
        // would let a shop's own rule lock its customers out.
        $limiter = $this->createMock(GiftCardRedemptionLimiterInterface::class);
        $limiter->expects(self::never())->method('recordFailure');

        $this->apply(
            $this->applyRequest(),
            $this->applicatorThrowing(new GiftCardsNotAcceptedOnOrderException()),
            $limiter,
        );
    }

    private function applicatorThrowing(\Throwable $exception): GiftCardApplicatorInterface
    {
        $applicator = $this->createMock(GiftCardApplicatorInterface::class);
        $applicator->method('apply')->willThrowException($exception);

        return $applicator;
    }

    /** @param bool $newlyApplied whether the card was actually added, or was already on the cart */
    private function applicatorReturning(bool $newlyApplied): GiftCardApplicatorInterface
    {
        $applicator = $this->createMock(GiftCardApplicatorInterface::class);
        $applicator->method('apply')->willReturn($newlyApplied);

        return $applicator;
    }

    private function limiterBlocking(bool $blocked): GiftCardRedemptionLimiterInterface
    {
        $limiter = $this->createMock(GiftCardRedemptionLimiterInterface::class);
        $limiter->method('isBlocked')->willReturn($blocked);

        return $limiter;
    }

    /**
     * A request carrying a submitted code and a session to put the answer in.
     */
    private function applyRequest(string $code = 'GIFT-NOPE'): Request
    {
        $request = new Request(request: ['madcoders_sylius_gift_card_code' => ['code' => $code]]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    /** @return list<string> */
    private function flashes(Request $request, string $type): array
    {
        $session = $request->getSession();
        self::assertInstanceOf(Session::class, $session);

        /** @var list<string> $messages */
        $messages = $session->getFlashBag()->peek($type);

        return $messages;
    }

    private function apply(
        Request $request,
        ?GiftCardApplicatorInterface $applicator = null,
        ?GiftCardRedemptionLimiterInterface $limiter = null,
    ): \Symfony\Component\HttpFoundation\RedirectResponse {
        $cart = $this->createMock(\Madcoders\SyliusGiftCardPlugin\Model\OrderInterface::class);
        $cart->method('getId')->willReturn(1);

        $cartContext = $this->createMock(CartContextInterface::class);
        $cartContext->method('getCart')->willReturn($cart);

        $controller = new GiftCardCartController(
            $cartContext,
            $applicator ?? $this->createMock(GiftCardApplicatorInterface::class),
            $this->createSubmittedCodeFormFactory($request),
            $this->createMock(ObjectManager::class),
            $this->createUrlGenerator(),
            $this->createMock(CsrfTokenManagerInterface::class),
            $limiter ?? $this->limiterBlocking(false),
        );

        return $controller->applyAction($request);
    }

    /**
     * A form factory whose form reports the code the request carries.
     *
     * The controller reads the submitted code through the form type, so a unit test of what it does
     * with the answer has to stand in for the form rather than for the request parsing.
     */
    private function createSubmittedCodeFormFactory(Request $request): FormFactoryInterface
    {
        /** @var array{code?: string} $submitted */
        $submitted = $request->request->all()['madcoders_sylius_gift_card_code'] ?? [];

        $codeField = $this->createMock(FormInterface::class);
        $codeField->method('getData')->willReturn($submitted['code'] ?? '');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->with('code')->willReturn($codeField);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        return $formFactory;
    }

    private function removeWithReturnTo(?string $returnTo): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        // Driven through removeAction with a bad CSRF token: that path returns the redirect without
        // needing a cart, a form or an applicator, so the test says something about the redirect
        // and nothing about anything else.
        $parameters = ['_token' => 'not-the-real-token'];

        if (null !== $returnTo) {
            $parameters['_return_to'] = $returnTo;
        }

        return $this->removeWithParameters($parameters);
    }

    /** @param array<string, mixed> $parameters */
    private function removeWithParameters(
        array $parameters,
        bool $cartHasId = true,
    ): \Symfony\Component\HttpFoundation\RedirectResponse {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(false);

        $cart = $this->createMock(\Madcoders\SyliusGiftCardPlugin\Model\OrderInterface::class);
        $cart->method('getId')->willReturn($cartHasId ? 1 : null);

        $cartContext = $this->createMock(CartContextInterface::class);
        $cartContext->method('getCart')->willReturn($cart);

        $controller = new GiftCardCartController(
            $cartContext,
            $this->createMock(GiftCardApplicatorInterface::class),
            $this->createMock(FormFactoryInterface::class),
            $this->createMock(ObjectManager::class),
            $this->createUrlGenerator(),
            $csrfTokenManager,
        );

        return $controller->removeAction(new Request(request: $parameters), 'GIFT-A');
    }

    private function createUrlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $route): string => match ($route) {
            'sylius_shop_cart_summary' => '/cart',
            'sylius_shop_checkout_address' => '/checkout/address',
            'sylius_shop_checkout_select_shipping' => '/checkout/select-shipping',
            'sylius_shop_checkout_select_payment' => '/checkout/select-payment',
            'sylius_shop_checkout_complete' => '/checkout/complete',
            default => throw new \InvalidArgumentException(sprintf(
                'The controller generated an unexpected route "%s" - only the whitelist should ever be reached.',
                $route,
            )),
        });

        return $urlGenerator;
    }
}
