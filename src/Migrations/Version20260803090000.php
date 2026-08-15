<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the gift card tables and adds the gift card flag to the product table.
 *
 * Written against the Schema API rather than raw SQL so the same migration works on MySQL, MariaDB
 * and PostgreSQL - all three are covered by CI.
 *
 * Indexes carry the names Doctrine's ORM derives from the table and column names rather than
 * readable ones, so that `doctrine:schema:validate` reports a host application in sync after
 * migrating.
 */
final class Version20260803090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add gift cards, their transaction ledger and per-channel configuration.';
    }

    public function up(Schema $schema): void
    {
        $giftCard = $schema->createTable('madcoders_gift_card__gift_card');
        $giftCard->addColumn('id', 'integer', ['autoincrement' => true]);
        $giftCard->addColumn('channel_id', 'integer', ['notnull' => false]);
        $giftCard->addColumn('purchaser_id', 'integer', ['notnull' => false]);
        $giftCard->addColumn('redeemer_id', 'integer', ['notnull' => false]);
        $giftCard->addColumn('order_item_unit_id', 'integer', ['notnull' => false]);
        $giftCard->addColumn('code', 'string', ['length' => 64]);
        $giftCard->addColumn('currency_code', 'string', ['length' => 3, 'notnull' => false]);
        $giftCard->addColumn('initial_amount', 'integer', ['notnull' => false]);
        $giftCard->addColumn('amount', 'integer');
        $giftCard->addColumn('enabled', 'boolean');
        $giftCard->addColumn('expires_at', 'datetime', ['notnull' => false]);
        $giftCard->addColumn('origin', 'string', ['length' => 16]);
        $giftCard->addColumn('custom_message', 'text', ['notnull' => false]);
        $giftCard->addColumn('created_at', 'datetime', ['notnull' => false]);
        $giftCard->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $giftCard->setPrimaryKey(['id']);
        $giftCard->addUniqueIndex(['code'], 'UNIQ_2283EBF977153098');
        $giftCard->addUniqueIndex(['order_item_unit_id'], 'UNIQ_2283EBF9F720C233');
        $giftCard->addIndex(['channel_id'], 'IDX_2283EBF972F5A1AA');
        $giftCard->addIndex(['purchaser_id'], 'IDX_2283EBF9ED255ED6');
        $giftCard->addIndex(['redeemer_id'], 'IDX_2283EBF97831853');
        $giftCard->addForeignKeyConstraint('sylius_channel', ['channel_id'], ['id'], ['onDelete' => 'SET NULL']);
        $giftCard->addForeignKeyConstraint('sylius_customer', ['purchaser_id'], ['id'], ['onDelete' => 'SET NULL']);
        $giftCard->addForeignKeyConstraint('sylius_customer', ['redeemer_id'], ['id'], ['onDelete' => 'SET NULL']);
        $giftCard->addForeignKeyConstraint('sylius_order_item_unit', ['order_item_unit_id'], ['id'], ['onDelete' => 'SET NULL']);

        $transaction = $schema->createTable('madcoders_gift_card__gift_card_transaction');
        $transaction->addColumn('id', 'integer', ['autoincrement' => true]);
        $transaction->addColumn('gift_card_id', 'integer');
        $transaction->addColumn('order_id', 'integer', ['notnull' => false]);
        $transaction->addColumn('type', 'string', ['length' => 16]);
        $transaction->addColumn('amount', 'integer');
        $transaction->addColumn('balance_after', 'integer');
        $transaction->addColumn('created_at', 'datetime', ['notnull' => false]);
        $transaction->setPrimaryKey(['id']);
        $transaction->addIndex(['gift_card_id'], 'IDX_508F5F6E2696A98F');
        $transaction->addIndex(['order_id'], 'IDX_508F5F6E8D9F6D38');
        $transaction->addForeignKeyConstraint('madcoders_gift_card__gift_card', ['gift_card_id'], ['id'], ['onDelete' => 'CASCADE']);
        $transaction->addForeignKeyConstraint('sylius_order', ['order_id'], ['id'], ['onDelete' => 'SET NULL']);

        $configuration = $schema->createTable('madcoders_gift_card__configuration');
        $configuration->addColumn('id', 'integer', ['autoincrement' => true]);
        $configuration->addColumn('channel_id', 'integer');
        $configuration->addColumn('code_length', 'integer');
        $configuration->addColumn('code_prefix', 'string', ['length' => 16, 'notnull' => false]);
        $configuration->addColumn('validity_period', 'string', ['length' => 64, 'notnull' => false]);
        $configuration->addColumn('enabled', 'boolean');
        $configuration->addColumn('created_at', 'datetime', ['notnull' => false]);
        $configuration->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $configuration->setPrimaryKey(['id']);
        $configuration->addUniqueIndex(['channel_id'], 'UNIQ_656B30A672F5A1AA');
        $configuration->addForeignKeyConstraint('sylius_channel', ['channel_id'], ['id'], ['onDelete' => 'CASCADE']);

        // Owning side of Order <-> GiftCard: the cards being spent on an order.
        $orderGiftCards = $schema->createTable('madcoders_gift_card__order_gift_cards');
        $orderGiftCards->addColumn('order_id', 'integer');
        $orderGiftCards->addColumn('gift_card_id', 'integer');
        $orderGiftCards->setPrimaryKey(['order_id', 'gift_card_id']);
        $orderGiftCards->addIndex(['order_id'], 'IDX_6F51B26F8D9F6D38');
        $orderGiftCards->addIndex(['gift_card_id'], 'IDX_6F51B26F2696A98F');
        $orderGiftCards->addForeignKeyConstraint('sylius_order', ['order_id'], ['id'], ['onDelete' => 'CASCADE']);
        $orderGiftCards->addForeignKeyConstraint('madcoders_gift_card__gift_card', ['gift_card_id'], ['id'], ['onDelete' => 'CASCADE']);

        // Marks a product as a gift card product. Added by the plugin's ProductTrait.
        $schema->getTable('sylius_product')->addColumn('gift_card', 'boolean', ['default' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('madcoders_gift_card__order_gift_cards');
        $schema->dropTable('madcoders_gift_card__gift_card_transaction');
        $schema->dropTable('madcoders_gift_card__configuration');
        $schema->dropTable('madcoders_gift_card__gift_card');

        $schema->getTable('sylius_product')->dropColumn('gift_card');
    }
}
