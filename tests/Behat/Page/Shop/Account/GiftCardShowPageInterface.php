<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Account;

use Sylius\Behat\Page\Shop\PageInterface as ShopPageInterface;

interface GiftCardShowPageInterface extends ShopPageInterface
{
    public function getBalance(): string;

    public function countTransactions(): int;
}
