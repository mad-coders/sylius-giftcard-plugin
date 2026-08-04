<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use Sylius\Behat\Page\SyliusPageInterface;

interface ShowPageInterface extends SyliusPageInterface
{
    public function getBalance(): string;

    public function getPurchaser(): string;

    public function getRedeemer(): string;

    public function countTransactions(): int;

    public function adjustBalance(): void;
}
