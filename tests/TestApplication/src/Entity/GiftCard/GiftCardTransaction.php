<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Entity\GiftCard;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTransaction as BaseGiftCardTransaction;

#[ORM\Entity]
#[ORM\Table(name: 'madcoders_gift_card__gift_card_transaction')]
class GiftCardTransaction extends BaseGiftCardTransaction
{
}
