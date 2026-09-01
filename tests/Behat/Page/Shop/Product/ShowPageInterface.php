<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Product;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPageInterface;

interface ShowPageInterface extends SymfonyPageInterface
{
    /** Whether the page offers a choice of amount at all. */
    public function hasAmountChoice(): bool;

    /**
     * The amounts offered as selectable options, as the customer reads them.
     *
     * @return list<string>
     */
    public function getAmountOptions(): array;

    /** Whether every offered amount is a radio input, rather than an option in a dropdown. */
    public function amountOptionsAreSelectable(): bool;

    public function hasFreeAmountField(): bool;

    /** The help text under the free amount field, which names the bounds. */
    public function getFreeAmountHelp(): string;

    public function hasMessageField(): bool;

    /** The `maxlength` the message field advertises, or null when it advertises none. */
    public function getMessageMaxLength(): ?int;

    public function getMessageHelp(): string;
}
