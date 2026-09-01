<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Checkout;

use FriendsOfBehat\PageObjectExtension\Page\PageInterface;

interface SummaryPageInterface extends PageInterface
{
    /** Presses Sylius' own confirmation button, so the checkout-complete constraints run. */
    public function confirmOrder(): void;

    /** Whatever the `sylius_checkout_complete` validation group refused, or an empty string. */
    public function getValidationErrors(): string;

    public function getGiftCardTotal(): string;

    public function getAmountToPay(): string;

    public function hasGiftCardTotal(): bool;

    public function hasGiftCardPanel(): bool;

    public function applyGiftCard(string $code): void;

    public function removeGiftCard(string $code): void;

    /** @return list<string> */
    public function getAppliedGiftCardCodes(): array;
}
