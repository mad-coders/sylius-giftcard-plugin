<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use Sylius\Behat\Page\Admin\Crud\UpdatePageInterface as BaseUpdatePageInterface;

interface UpdatePageInterface extends BaseUpdatePageInterface, GiftCardFormPageInterface
{
    public function isCodeEditable(): bool;

    public function getCode(): string;

    public function isInitialAmountEditable(): bool;

    public function disable(): void;
}
