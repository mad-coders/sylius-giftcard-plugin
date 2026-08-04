<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPageInterface;

interface AdjustBalancePageInterface extends SymfonyPageInterface
{
    public function adjust(string $direction, string $amount): void;

    public function getCurrentBalance(): string;
}
