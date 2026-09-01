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

    public function chooseSaleMode(string $saleMode): void;

    public function chooseAmountMode(string $mode): void;

    /** The presets as an operator types them: a comma-separated list in major units. */
    public function specifyAmountPresets(string $presets): void;

    public function specifyAmountBounds(string $minimum, string $maximum): void;

    /** Every validation message on the form, joined - the amount rules can fail on several fields. */
    public function getValidationMessages(): string;

    public function getCodeLengthValidationMessage(): string;
}
