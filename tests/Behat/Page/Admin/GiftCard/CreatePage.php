<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

use Sylius\Behat\Page\Admin\Crud\CreatePage as BaseCreatePage;

final class CreatePage extends BaseCreatePage implements CreatePageInterface
{
    use ReadsValidationMessagesTrait;

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

    public function getExpiryDate(): string
    {
        $value = $this->getDocument()->findField('Expires at')?->getValue();

        // A single_text date widget holds one string. Mink types getValue() loosely because a
        // multi-select holds an array, which this field never is.
        return is_string($value) ? $value : '';
    }

    public function specifyExpiryDate(string $expiresAt): void
    {
        $this->getDocument()->fillField('Expires at', $expiresAt);
    }
}
