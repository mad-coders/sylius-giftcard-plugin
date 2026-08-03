<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Exception;

final class GiftCardNotFoundException extends GiftCardException
{
    public function __construct(private readonly string $giftCardCode)
    {
        parent::__construct(sprintf('There is no gift card with code "%s".', $giftCardCode));
    }

    public function getGiftCardCode(): string
    {
        return $this->giftCardCode;
    }
}
