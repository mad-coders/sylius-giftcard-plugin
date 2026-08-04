<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use Sylius\Behat\Page\Admin\Crud\CreatePage as BaseCreatePage;

final class CreatePage extends BaseCreatePage implements CreatePageInterface
{
    public function specifyCode(string $code): void
    {
        $this->getDocument()->fillField('Code', $code);
    }

    public function specifyInitialAmount(string $amount): void
    {
        $this->getDocument()->fillField('Initial amount', $amount);
    }

    public function chooseChannel(string $channelName): void
    {
        $this->getDocument()->selectFieldOption('Channel', $channelName);
    }
}
