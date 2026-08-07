<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\Order;

use FriendsOfBehat\PageObjectExtension\Page\PageInterface;

interface ShowPageInterface extends PageInterface
{
    public function getGiftCardTotal(): string;

    public function getAmountToPay(): string;

    public function hasGiftCardTotal(): bool;

    public function hasGiftCard(string $code): bool;
}
