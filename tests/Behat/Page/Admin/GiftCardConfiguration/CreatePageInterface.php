<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCardConfiguration;

use Sylius\Behat\Page\Admin\Crud\CreatePageInterface as BaseCreatePageInterface;

interface CreatePageInterface extends BaseCreatePageInterface
{
    public function chooseChannel(string $channelName): void;

    public function specifyCodePrefix(string $prefix): void;

    public function specifyCodeLength(int $length): void;

    public function specifyValidityPeriod(string $period): void;

    public function getCodeLengthValidationMessage(): string;
}
