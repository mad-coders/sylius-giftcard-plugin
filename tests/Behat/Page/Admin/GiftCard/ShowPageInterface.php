<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPageInterface;

interface ShowPageInterface extends SymfonyPageInterface
{
    public function getBalance(): string;

    public function getPurchaser(): string;

    public function getRedeemer(): string;

    public function countTransactions(): int;

    public function adjustBalance(): void;
}
