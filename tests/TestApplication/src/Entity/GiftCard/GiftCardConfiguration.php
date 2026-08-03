<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Entity\GiftCard;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration as BaseGiftCardConfiguration;

#[ORM\Entity]
#[ORM\Table(name: 'madcoders_gift_card__configuration')]
class GiftCardConfiguration extends BaseGiftCardConfiguration
{
}
