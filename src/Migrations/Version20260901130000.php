<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lets a channel decide how a customer chooses a gift card's amount, and lets the customer leave a
 * message with it.
 *
 * Written against the Schema API rather than raw SQL so the same migration works on MySQL, MariaDB
 * and PostgreSQL - all three are covered by CI.
 *
 * The defaults are chosen so an existing shop keeps behaving exactly as it did: `fixed` mode means
 * the amount still comes from the product's price, and no order item has a chosen amount or a
 * message.
 */
final class Version20260901130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-channel gift card amount modes, and the amount and message a customer chooses.';
    }

    public function up(Schema $schema): void
    {
        $configuration = $schema->getTable('madcoders_gift_card__configuration');

        // The literal, not GiftCardAmountMode::Fixed->value: a migration is a record of what was
        // done to a database and must not change meaning when the enum is renamed.
        $configuration->addColumn('amount_mode', 'string', [
            'length' => 32,
            'default' => 'fixed',
        ]);
        // Nullable, and with no default: MySQL refuses a default on a JSON column, and a column
        // added to a table that already has rows needs one or the other. Null and an empty array
        // both mean "this channel offers no presets", which GiftCardConfiguration resolves.
        $configuration->addColumn('amount_presets', 'json', ['notnull' => false]);
        $configuration->addColumn('minimum_amount', 'integer', ['notnull' => false]);
        $configuration->addColumn('maximum_amount', 'integer', ['notnull' => false]);

        // Added by the plugin's OrderItemTrait: what the customer asked for on this line.
        $orderItem = $schema->getTable('sylius_order_item');
        $orderItem->addColumn('gift_card_amount', 'integer', ['notnull' => false]);
        // `text`, like the gift card's own message column, rather than a varchar sized to the
        // 255-character limit. The limit is a validation rule; a column that also enforces it turns
        // any writer that skips validation into either a crash or a message cut mid-character.
        $orderItem->addColumn('gift_card_message', 'text', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $configuration = $schema->getTable('madcoders_gift_card__configuration');
        $configuration->dropColumn('amount_mode');
        $configuration->dropColumn('amount_presets');
        $configuration->dropColumn('minimum_amount');
        $configuration->dropColumn('maximum_amount');

        $orderItem = $schema->getTable('sylius_order_item');
        $orderItem->dropColumn('gift_card_amount');
        $orderItem->dropColumn('gift_card_message');
    }
}
