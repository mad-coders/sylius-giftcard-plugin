<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the per-channel sale mode: whether the shop may sell gift cards, or only an administrator
 * may issue them.
 *
 * Written against the Schema API rather than raw SQL so the same migration works on MySQL, MariaDB
 * and PostgreSQL - all three are covered by CI.
 */
final class Version20260901120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the gift card sale mode to the per-channel configuration.';
    }

    public function up(Schema $schema): void
    {
        // The literal, not GiftCardSaleMode::Sellable->value: a migration is a record of what was
        // done to a database and must not change meaning when the enum is renamed.
        //
        // The default backfills existing rows, so a channel configured before this migration keeps
        // selling gift cards exactly as it did.
        $schema->getTable('madcoders_gift_card__configuration')
            ->addColumn('sale_mode', 'string', ['length' => 16, 'default' => 'sellable'])
        ;
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('madcoders_gift_card__configuration')->dropColumn('sale_mode');
    }
}
