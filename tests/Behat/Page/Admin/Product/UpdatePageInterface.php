<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\Product;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPageInterface;

interface UpdatePageInterface extends SymfonyPageInterface
{
    public function saveUnchanged(): void;

    public function isGiftCardChecked(): bool;

    public function hasGiftCardField(): bool;
}
