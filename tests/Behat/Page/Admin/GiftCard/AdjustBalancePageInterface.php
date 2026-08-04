<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use Sylius\Behat\Page\SyliusPageInterface;

interface AdjustBalancePageInterface extends SyliusPageInterface
{
    public function adjust(string $direction, string $amount): void;

    public function getCurrentBalance(): string;
}
