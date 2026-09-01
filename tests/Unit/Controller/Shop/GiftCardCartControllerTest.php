<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Controller\Shop;

use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Madcoders\SyliusGiftCardPlugin\Controller\Shop\GiftCardCartController;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
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

    private function removeWithReturnTo(?string $returnTo): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        // Driven through removeAction with a bad CSRF token: that path returns the redirect without
        // needing a cart, a form or an applicator, so the test says something about the redirect
        // and nothing about anything else.
        $parameters = ['_token' => 'not-the-real-token'];

        if (null !== $returnTo) {
            $parameters['_return_to'] = $returnTo;
        }

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(false);

        $cartContext = $this->createMock(CartContextInterface::class);
        $cartContext->method('getCart')->willReturn($this->createMock(\Madcoders\SyliusGiftCardPlugin\Model\OrderInterface::class));

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
