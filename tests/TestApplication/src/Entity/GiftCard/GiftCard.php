<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Entity\GiftCard;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCard as BaseGiftCard;

/**
 * The concrete entity for the plugin's GiftCard mapped superclass.
 *
 * A host application declares the same three classes; see docs/INSTALLATION.md.
 */
#[ORM\Entity]
#[ORM\Table(name: 'madcoders_gift_card__gift_card')]
class GiftCard extends BaseGiftCard
{
}
