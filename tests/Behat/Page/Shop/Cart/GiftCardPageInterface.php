<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Cart;

use Sylius\Behat\Page\Shop\PageInterface as ShopPageInterface;

interface GiftCardPageInterface extends ShopPageInterface
{
    public function applyGiftCard(string $code): void;

    public function removeGiftCard(string $code): void;

    /** @return list<string> */
    public function getAppliedGiftCardCodes(): array;

    public function getGiftCardBalance(string $code): string;

    public function getAmountToPay(): string;

    public function getGiftCardTotal(): string;

    public function hasGiftCardTotal(): bool;

    public function getOrderTotal(): string;

    /** How many separate lines the cart holds - not the total quantity. */
    public function countItems(): int;

    /**
     * The unit price shown against each line, in the order the cart lists them.
     *
     * @return list<string>
     */
    public function getItemUnitPrices(): array;

    public function getItemsTotal(): string;
}
