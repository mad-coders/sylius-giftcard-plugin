<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Account;

use Sylius\Behat\Page\Shop\PageInterface as ShopPageInterface;

interface GiftCardIndexPageInterface extends ShopPageInterface
{
    /** @return list<string> */
    public function getRedeemedGiftCardCodes(): array;

    /** @return list<string> */
    public function getPurchasedGiftCardCodes(): array;

    public function getBalanceOf(string $code): string;

    public function openBalanceHistoryOf(string $code): void;
}
