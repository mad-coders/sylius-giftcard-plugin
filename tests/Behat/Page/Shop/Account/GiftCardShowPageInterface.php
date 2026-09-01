<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Account;

use Sylius\Behat\Page\Shop\PageInterface as ShopPageInterface;

interface GiftCardShowPageInterface extends ShopPageInterface
{
    public function getBalance(): string;

    /** The message the buyer left on the card, or null when it carries none. */
    public function getCustomMessage(): ?string;

    public function countTransactions(): int;
}
