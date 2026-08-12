<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Controller\Admin;

use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Controller\Admin\AdjustGiftCardBalanceController;
use Madcoders\SyliusGiftCardPlugin\Modifier\GiftCardBalanceModifierInterface;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

/**
 * The authorization guard on the one admin action that moves money.
 *
 * It is checked in the controller rather than left to the firewall alone, because a host importing
 * the admin routes under a different prefix would otherwise expose it. That makes the check the
 * plugin's own responsibility, and it had no test.
 */
final class AdjustGiftCardBalanceControllerTest extends TestCase
{
    public function testItRefusesACallerWithoutTheRequiredRole(): void
    {
        $repository = $this->createMock(GiftCardRepositoryInterface::class);
        // The card must never even be looked up: whether a given id exists is not something an
        // unauthorized caller gets to learn.
        $repository->expects(self::never())->method('find');

        $controller = $this->createController($repository, granted: false);

        $this->expectException(AccessDeniedHttpException::class);

        $controller(new Request(), 1);
    }

    public function testItChecksTheRoleTheHostConfigured(): void
    {
        // Hosts with a finer permission model raise the required role; the controller must ask about
        // that role and not a hardcoded one.
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects(self::once())
            ->method('isGranted')
            ->with('ROLE_GIFT_CARD_TREASURER')
            ->willReturn(false)
        ;

        $controller = $this->createController(
            $this->createMock(GiftCardRepositoryInterface::class),
            granted: false,
            authorizationChecker: $authorizationChecker,
            requiredRole: 'ROLE_GIFT_CARD_TREASURER',
        );

        $this->expectException(AccessDeniedHttpException::class);

        $controller(new Request(), 1);
    }

    public function testAnAuthorizedCallerAskingForAnUnknownCardGetsANotFound(): void
    {
        // Proves the guard is passed rather than silently swallowing everything: an authorized
        // caller reaches the lookup and gets the ordinary 404.
        $repository = $this->createMock(GiftCardRepositoryInterface::class);
        $repository->expects(self::once())->method('find')->with(404)->willReturn(null);

        $controller = $this->createController($repository, granted: true);

        $this->expectException(NotFoundHttpException::class);

        $controller(new Request(), 404);
    }

    private function createController(
        GiftCardRepositoryInterface $repository,
        bool $granted,
        ?AuthorizationCheckerInterface $authorizationChecker = null,
        string $requiredRole = 'ROLE_ADMINISTRATION_ACCESS',
    ): AdjustGiftCardBalanceController {
        if (null === $authorizationChecker) {
            $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
            $authorizationChecker->method('isGranted')->willReturn($granted);
        }

        return new AdjustGiftCardBalanceController(
            $repository,
            $this->createMock(GiftCardBalanceModifierInterface::class),
            $this->createMock(FormFactoryInterface::class),
            $this->createMock(ObjectManager::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(Environment::class),
            $authorizationChecker,
            $requiredRole,
        );
    }
}
