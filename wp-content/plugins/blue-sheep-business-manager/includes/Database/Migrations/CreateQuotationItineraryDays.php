<?php

namespace BlueSheep\BusinessManager\Database\Migrations;

use BlueSheep\BusinessManager\Database\MigrationInterface;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates the quotation itinerary days table.
 *
 * Migration version: 2
 *
 * Each itinerary day belongs to a specific quotation version.
 * Financial items remain stored separately in bs_quotation_items.
 */
final class CreateQuotationItineraryDays implements MigrationInterface
{
    /**
     * Return this migration's schema version.
     */
    public function getVersion(): int
    {
        return 2;
    }

    /**
     * Create the quotation itinerary days table.
     */
    public function up(): void
    {
        global $wpdb;

        $this->assertValidPrefix();

        $tables = $this->getTables();

        if (!$this->tableExists($tables['quotation_versions'])) {
            throw new RuntimeException(
                'Migration stopped because the quotation versions table does not exist.'
            );
        }

        if ($this->tableExists($tables['itinerary_days'])) {
            throw new RuntimeException(
                'Migration stopped because the itinerary days table already exists. '
                . 'Inspect the existing table before proceeding.'
            );
        }

        $sql = 'CREATE TABLE `' . $tables['itinerary_days'] . '` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `quotation_version_id` BIGINT UNSIGNED NOT NULL,
            `day_number` BIGINT UNSIGNED NOT NULL,
            `itinerary_date` DATE NULL,
            `title` VARCHAR(190) NOT NULL,
            `start_location` VARCHAR(190) NULL,
            `end_location` VARCHAR(190) NULL,
            `route_description` TEXT NULL,
            `estimated_travel_minutes` INT UNSIGNED NULL,
            `activities` LONGTEXT NULL,
            `overnight_location` VARCHAR(190) NULL,
            `meal_arrangements` TEXT NULL,
            `description` TEXT NULL,
            `sort_order` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_version_day` (`quotation_version_id`, `day_number`),
            KEY `idx_version_sort` (`quotation_version_id`, `sort_order`),
            CONSTRAINT `' . $this->foreignKeyName('itinerary_days_version') . '`
                FOREIGN KEY (`quotation_version_id`)
                REFERENCES `' . $tables['quotation_versions'] . '` (`id`)
                ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC';

        $this->execute($sql);

        $this->verifyCreatedTable($tables['itinerary_days']);
    }

    /**
     * Drop the itinerary table only when it is confirmed empty.
     *
     * Existing itinerary data must never be deleted by a routine rollback.
     */
    public function down(): void
    {
        $this->assertValidPrefix();

        $tables = $this->getTables();
        $table = $tables['itinerary_days'];

        if (!$this->tableExists($table)) {
            throw new RuntimeException(
                'Rollback stopped because the itinerary days table does not exist.'
            );
        }

        global $wpdb;

        $count = $wpdb->get_var(
            'SELECT COUNT(*) FROM `' . $table . '`'
        );

        if ($count === null || (int) $count !== 0) {
            throw new RuntimeException(
                'Rollback stopped because the itinerary days table is not confirmed empty. '
                . 'Existing itinerary records will not be deleted.'
            );
        }

        $this->execute('DROP TABLE `' . $table . '`');
    }

    /**
     * Return fully prefixed table names.
     */
    private function getTables(): array
    {
        global $wpdb;

        $prefix = (string) $wpdb->prefix;

        return array(
            'quotation_versions' => $prefix . 'bs_quotation_versions',
            'itinerary_days' => $prefix . 'bs_quotation_itinerary_days',
        );
    }

    /**
     * Validate the WordPress database prefix and generated table names.
     */
    private function assertValidPrefix(): void
    {
        global $wpdb;

        if (
            !isset($wpdb->prefix)
            || !preg_match('/^[A-Za-z0-9_]+$/', (string) $wpdb->prefix)
        ) {
            throw new RuntimeException(
                'The WordPress database prefix contains unsupported characters.'
            );
        }

        foreach ($this->getTables() as $table) {
            if (strlen($table) > 64) {
                throw new RuntimeException(
                    'A generated table name exceeds MySQL’s 64-character identifier limit.'
                );
            }
        }
    }

    /**
     * Generate a database-unique foreign-key constraint name.
     */
    private function foreignKeyName(string $name): string
    {
        global $wpdb;

        $prefixHash = substr(
            hash('sha256', (string) $wpdb->prefix),
            0,
            8
        );

        return 'fk_bs_' . $prefixHash . '_' . $name;
    }

    /**
     * Check whether a table exists in the current database.
     */
    private function tableExists(string $table): bool
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = %s',
                $table
            )
        );

        return (int) $count === 1;
    }

    /**
     * Execute a database statement and surface any database error.
     */
    private function execute(string $sql): void
    {
        global $wpdb;

        $result = $wpdb->query($sql);

        if ($result === false) {
            $detail = !empty($wpdb->last_error)
                ? $wpdb->last_error
                : 'Unknown database error.';

            throw new RuntimeException(
                'Quotation itinerary migration failed: ' . $detail
            );
        }
    }

    /**
     * Verify the created table's engine, collation, row format and foreign key.
     */
    private function verifyCreatedTable(string $table): void
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT ENGINE, TABLE_COLLATION, ROW_FORMAT
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = %s',
                $table
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            throw new RuntimeException(
                'Migration verification could not find the itinerary days table.'
            );
        }

        if (strtoupper((string) $row['ENGINE']) !== 'INNODB') {
            throw new RuntimeException(
                'Migration verification found an unexpected storage engine.'
            );
        }

        if (
            strtolower((string) $row['TABLE_COLLATION'])
            !== 'utf8mb4_unicode_ci'
        ) {
            throw new RuntimeException(
                'Migration verification found an unexpected table collation.'
            );
        }

        if (strtolower((string) $row['ROW_FORMAT']) !== 'dynamic') {
            throw new RuntimeException(
                'Migration verification found an unexpected row format.'
            );
        }

        $constraintCount = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*)
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = %s
                AND CONSTRAINT_NAME = %s
                AND CONSTRAINT_TYPE = %s',
                $table,
                $this->foreignKeyName('itinerary_days_version'),
                'FOREIGN KEY'
            )
        );

        if ((int) $constraintCount !== 1) {
            throw new RuntimeException(
                'Migration verification could not confirm the quotation version foreign key.'
            );
        }
    }
}
