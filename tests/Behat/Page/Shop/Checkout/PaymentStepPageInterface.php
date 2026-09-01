<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Checkout;

use FriendsOfBehat\PageObjectExtension\Page\PageInterface;

interface PaymentStepPageInterface extends PageInterface
{
    public function hasGiftCardPanel(): bool;

    public function applyGiftCard(string $code): void;

    public function getAmountToPay(): string;
}
