<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Makes every gift card expire. See docs/adr-log/0015-every-gift-card-expires.md.
 *
 * The interesting half is the back-fill. Cards issued before this release may have no expiry date at
 * all, and the column is about to refuse null, so each of them is given one before the constraint
 * lands: the card's own creation date plus its channel's validity period, or a year where the
 * channel has no usable period. Written against the Schema API and per-row SQL rather than a
 * database-specific date expression, because CI covers MySQL, MariaDB and PostgreSQL and none of
 * them agrees with the others on how to add "6 months" to a column.
 */
final class Version20260902100000 extends AbstractMigration
{
    private const string GIFT_CARD_TABLE = 'madcoders_gift_card__gift_card';

    private const string CONFIGURATION_TABLE = 'madcoders_gift_card__configuration';

    /**
     * The literal, not GiftCardExpiryCalculator::DEFAULT_VALIDITY_PERIOD: a migration is a record
     * of what was done to a database on a particular day, and must not quietly start doing
     * something else when the constant changes.
     */
    private const string DEFAULT_VALIDITY_PERIOD = '1 year';

    public function getDescription(): string
    {
        return 'Every gift card expires: back-fills a date on cards that had none, from the card\'s creation date '
            . 'plus its channel\'s validity period (a year where the channel has no usable one), then makes '
            . 'expires_at NOT NULL.';
    }

    public function up(Schema $schema): void
    {
        $this->backfillExpiryDates();

        $expiresAt = $schema->getTable(self::GIFT_CARD_TABLE)->getColumn('expires_at');
        $expiresAt->setNotnull(true);

        // The default has to be cleared as well, and it is not cosmetic. Introspecting a MySQL
        // column declared `DATETIME DEFAULT NULL` gives DBAL the *string* "NULL" as the default,
        // which it happily re-emits - harmless while the column is nullable, fatal the moment it is
        // not: `CHANGE expires_at expires_at DATETIME DEFAULT 'NULL' NOT NULL` is error 1067,
        // "Invalid default value". PostgreSQL never showed this, because it emits a bare
        // `ALTER ... SET NOT NULL`. Passing PHP null makes the platform omit the clause entirely.
        $expiresAt->setDefault(null);
    }

    public function down(Schema $schema): void
    {
        // Deliberately not undoing the back-fill. The dates written above are the ones the shop has
        // since been quoting to customers, and inventing "these ones were guessed" to erase them
        // would take money off cards that are now in circulation with a printed expiry.
        $expiresAt = $schema->getTable(self::GIFT_CARD_TABLE)->getColumn('expires_at');
        $expiresAt->setNotnull(false);
        $expiresAt->setDefault(null);
    }

    /**
     * Gives every card that has no expiry date one, before the column stops accepting null.
     *
     * Safe to run on a populated table, and safe to run on an empty one. Every write goes through
     * addSql() with bound parameters, so the migration still shows its work under --dry-run.
     *
     * **The batching bounds the reads, not the memory.** Rows come back 500 at a time so no single
     * result set is enormous, but every UPDATE is queued in AbstractMigration::$plannedSql and none
     * of them is executed until up() returns - so a table with a million null expiry dates holds a
     * million statements before anything runs. That is a real ceiling; it is just far above any
     * plausible number of gift cards, which is why this is written the simple way and said out loud
     * rather than solved.
     *
     * **Timezones.** created_at is read as a string, parsed in PHP's default timezone and written
     * back the same way, so the arithmetic is wall-clock and never converts between zones: a card
     * created at 09:00 gets an expiry at 09:00, whatever PHP and the database each think that
     * instant is. A PHP/database timezone mismatch therefore cannot shift a back-filled date. It
     * can shift the already-expired *count* below, which compares against PHP's idea of now - that
     * number is a report, and the operator's own query in docs/INSTALLATION.md is the authority.
     *
     * It reports what it did through the migration logger, including how many cards it dated into
     * the past. That number matters: a card issued three years ago in a channel with a one-year
     * period comes out already expired, which is what "measured from the card's creation date"
     * means and is money a holder can no longer spend.
     *
     * **Do not rely on seeing that report on the console.** Whether it surfaces depends entirely on
     * where the host application routes the migrations logger, and in a stock Sylius application it
     * goes nowhere - verified, not assumed. The number an operator actually needs is one query, and
     * docs/INSTALLATION.md gives it to them. Tell the holders before running this, not after.
     */
    private function backfillExpiryDates(): void
    {
        $validityPeriods = $this->validityPeriodsByChannel();
        $now = new \DateTimeImmutable();

        $dated = 0;
        $alreadyExpired = 0;
        $lastId = 0;

        while (true) {
            /** @var list<array{id: int|string, channel_id: int|string|null, created_at: string|null}> $rows */
            $rows = $this->connection->fetchAllAssociative(
                sprintf(
                    'SELECT id, channel_id, created_at FROM %s WHERE expires_at IS NULL AND id > ? ORDER BY id ASC LIMIT 500',
                    self::GIFT_CARD_TABLE,
                ),
                [$lastId],
            );

            if ([] === $rows) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = (int) $row['id'];

                $expiresAt = $this->expiryFor(
                    $row['created_at'],
                    $validityPeriods[(int) ($row['channel_id'] ?? 0)] ?? null,
                );

                $this->addSql(
                    sprintf('UPDATE %s SET expires_at = ? WHERE id = ?', self::GIFT_CARD_TABLE),
                    [$expiresAt->format('Y-m-d H:i:s'), $lastId],
                );

                ++$dated;

                if ($expiresAt < $now) {
                    ++$alreadyExpired;
                }
            }
        }

        if (0 === $dated) {
            $this->write('No gift cards were missing an expiry date; nothing to back-fill.');

            return;
        }

        $this->write(sprintf(
            'Gave an expiry date to %d gift card(s) that had none, measured from each card\'s creation date. '
            . 'Of those, %d are already expired because their channel\'s validity period had run out before today.',
            $dated,
            $alreadyExpired,
        ));
    }

    /**
     * Every channel's configured validity period, keyed by channel id.
     *
     * One query rather than one per card: the number of channels is small and the number of cards
     * is not.
     *
     * @return array<int, string|null>
     */
    private function validityPeriodsByChannel(): array
    {
        /** @var list<array{channel_id: int|string, validity_period: string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT channel_id, validity_period FROM %s', self::CONFIGURATION_TABLE),
        );

        $periods = [];

        foreach ($rows as $row) {
            $periods[(int) $row['channel_id']] = $row['validity_period'];
        }

        return $periods;
    }

    /**
     * The card's creation date plus its channel's period, falling back at every step.
     *
     * A card with no creation date, no channel, a blank period or one that cannot be parsed still
     * comes out with a date, because the whole point of this migration is that afterwards every
     * card has one.
     */
    private function expiryFor(?string $createdAt, ?string $validityPeriod): \DateTimeImmutable
    {
        try {
            $from = new \DateTimeImmutable($createdAt ?? 'now');
        } catch (\Throwable) {
            $from = new \DateTimeImmutable();
        }

        foreach ([$validityPeriod, self::DEFAULT_VALIDITY_PERIOD] as $period) {
            if (null === $period || '' === trim($period)) {
                continue;
            }

            try {
                $expiresAt = $from->add(\DateInterval::createFromDateString($period));
            } catch (\Throwable) {
                // Unparseable periods are reported differently across PHP versions, so the failure
                // is caught rather than the class - and the loop falls through to the default.
                continue;
            }

            if ($expiresAt > $from) {
                return $expiresAt;
            }
        }

        // Only reachable if the default period above stopped parsing, which would be a broken PHP
        // rather than a broken shop. A card still gets a date.
        return $from->add(new \DateInterval('P1Y'));
    }
}
