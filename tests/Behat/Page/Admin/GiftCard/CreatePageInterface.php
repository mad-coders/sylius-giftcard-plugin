<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use Sylius\Behat\Page\Admin\Crud\CreatePageInterface as BaseCreatePageInterface;

interface CreatePageInterface extends BaseCreatePageInterface, GiftCardFormPageInterface
{
    public function specifyCode(string $code): void;

    public function specifyInitialAmount(string $amount): void;

    public function chooseChannel(string $channelName): void;

    /** What the form offers as the expiry date, as an ISO-ish string the browser would submit. */
    public function getExpiryDate(): string;
}
