<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stops a gift card being paid for with a gift card.
 *
 * Runs immediately after the mandatory-expiry migration, and belongs with it: an expiry date a
 * holder can renew for free by rolling one card into the next is not an expiry date. See
 * docs/adr-log/0016-a-gift-card-does-not-buy-a-gift-card.md.
 *
 * Unlike the sale mode added in Version20260901120000, the default here deliberately changes what
 * an existing shop does. The previous behaviour was the hole, so leaving unconfigured channels in
 * it would mean the fix protected only the shops that had already read the release notes.
 */
final class Version20260902110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the per-channel gift card tender mode, defaulting to goods_only, so a gift card no longer pays '
            . 'for a gift card. This changes behaviour for every channel, including unconfigured ones.';
    }

    public function up(Schema $schema): void
    {
        $configuration = $schema->getTable('madcoders_gift_card__configuration');

        // The literal, not GiftCardTenderMode::GoodsOnly->value: a migration is a record of what was
        // done to a database on a particular day, and must not quietly start doing something else
        // when the enum is renamed. The default is needed as well as wanted - the column is added to
        // a table that already has rows.
        $configuration->addColumn('tender_mode', 'string', [
            'length' => 16,
            'default' => 'goods_only',
        ]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('madcoders_gift_card__configuration')->dropColumn('tender_mode');
    }
}
