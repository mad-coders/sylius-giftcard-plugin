<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCardConfiguration;

use Sylius\Behat\Page\Admin\Crud\CreatePage as BaseCreatePage;

final class CreatePage extends BaseCreatePage implements CreatePageInterface
{
    public function chooseChannel(string $channelName): void
    {
        $this->getDocument()->selectFieldOption('Channel', $channelName);
    }

    public function specifyCodePrefix(string $prefix): void
    {
        $this->getDocument()->fillField('Code prefix', $prefix);
    }

    public function specifyCodeLength(int $length): void
    {
        $this->getDocument()->fillField('Code length', (string) $length);
    }

    public function specifyValidityPeriod(string $period): void
    {
        $this->getDocument()->fillField('Validity period', $period);
    }

    public function chooseSaleMode(string $saleMode): void
    {
        $this->getDocument()->selectFieldOption('Gift card sales', $saleMode);
    }

    public function getCodeLengthValidationMessage(): string
    {
        // Matched on Bootstrap's class rather than the `<field>_errorN` id Symfony gives an error:
        // that id only exists from Symfony 7, and the plugin supports 6.4 too. Sylius' own
        // getValidationMessage() does not find it, because this form is rendered by the shared CRUD
        // template rather than a Sylius-owned one.
        $error = $this->getDocument()->find('css', '.invalid-feedback');

        return null === $error ? '' : trim($error->getText());
    }
}
