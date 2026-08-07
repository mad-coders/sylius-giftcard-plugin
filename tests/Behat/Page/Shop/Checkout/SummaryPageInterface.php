<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Checkout;

use FriendsOfBehat\PageObjectExtension\Page\PageInterface;

interface SummaryPageInterface extends PageInterface
{
    public function getGiftCardTotal(): string;

    public function getAmountToPay(): string;

    public function hasGiftCardTotal(): bool;
}
