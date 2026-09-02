<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCard;

/**
 * What the create and update pages have in common.
 *
 * Both render the same form type, so the scenarios about what the form refuses read the same on
 * either. This lets a context step say "the expiry date was refused" once instead of once per page,
 * and stops the two drifting into asserting different things about the same rule.
 */
interface GiftCardFormPageInterface
{
    public function specifyExpiryDate(string $expiresAt): void;

    /** Every field validation message on the form, joined. */
    public function getValidationMessages(): string;

    /** The validation messages rendered against one named field, joined. */
    public function getFieldValidationMessage(string $label): string;
}
