<?php

namespace BlueSheep\BusinessManager\Database\Migrations;

use BlueSheep\BusinessManager\Database\MigrationInterface;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds WordPress Media Library image support to itinerary days.
 *
 * Migration version: 3
 *
 * Stores one optional attachment ID per itinerary day.
 * Image files remain managed by the WordPress Media Library.
 */
final class AddItineraryDayImageAttachment implements MigrationInterface
{
    public function getVersion(): int
    {
        return 3;
    }

    /**
     * Add the optional image attachment column.
     */
    public function up(): void
    {
        $this->assertValidPrefix();

        $table = $this->getItineraryTable();

        if (!$this->tableExists($table)) {
            throw new RuntimeException(
                'Migration stopped because the itinerary days table does not exist.'
            );
        }

        if ($this->columnExists($table, 'image_attachment_id')) {
            throw new RuntimeException(
                'Migration stopped because the image attachment column already exists. '
                . 'Inspect the existing column before proceeding.'
            );
        }

        $sql = 'ALTER TABLE `' . $table . '`
            ADD COLUMN `image_attachment_id` BIGINT UNSIGNED NULL
            AFTER `description`';

        $this->execute($sql);

        $this->verifyColumn($table);
    }

    /**
     * Remove the image attachment column only when it contains no references.
     *
     * Existing image associations must not be silently discarded.
     */
    public function down(): void
    {
        $this->assertValidPrefix();

        $table = $this->getItineraryTable();

        if (!$this->tableExists($table)) {
            throw new RuntimeException(
                'Rollback stopped because the itinerary days table does not exist.'
            );
        }

        if (!$this->columnExists($table, 'image_attachment_id')) {
            throw new RuntimeException(
                'Rollback stopped because the image attachment column does not exist.'
            );
        }

        global $wpdb;

        $count = $wpdb->get_var(
            'SELECT COUNT(*)
            FROM `' . $table . '`
            WHERE `image_attachment_id` IS NOT NULL'
        );

        if ($count === null || (int) $count !== 0) {
            throw new RuntimeException(
                'Rollback stopped because itinerary image references exist. '
                . 'Existing image associations will not be deleted.'
            );
        }

        $this->execute(
            'ALTER TABLE `' . $table . '`
            DROP COLUMN `image_attachment_id`'
        );
    }

    /**
     * Return the fully prefixed itinerary table name.
     */
    private function getItineraryTable(): string
    {
        global $wpdb;

        return (string) $wpdb->prefix . 'bs_quotation_itinerary_days';
    }

    /**
     * Validate the WordPress database prefix and table name.
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

        if (strlen($this->getItineraryTable()) > 64) {
            throw new RuntimeException(
                'The itinerary table name exceeds MySQL’s 64-character identifier limit.'
            );
        }
    }

    /**
     * Check whether the itinerary table exists.
     */
    private function tableExists(string $table): bool
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = %s',
                $table
            )
        );

        return (int) $count === 1;
    }

    /**
     * Check whether a column exists in the itinerary table.
     */
    private function columnExists(string $table, string $column): bool
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = %s
                AND COLUMN_NAME = %s',
                $table,
                $column
            )
        );

        return (int) $count === 1;
    }

    /**
     * Execute a database statement and surface database errors.
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
                'Itinerary image migration failed: ' . $detail
            );
        }
    }

    /**
     * Verify the new column's type and nullability.
     */
    private function verifyColumn(string $table): void
    {
        global $wpdb;

        $column = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT COLUMN_TYPE, IS_NULLABLE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = %s
                AND COLUMN_NAME = %s',
                $table,
                'image_attachment_id'
            ),
            ARRAY_A
        );

        if (!is_array($column)) {
            throw new RuntimeException(
                'Migration verification could not find the image attachment column.'
            );
        }

        if (strtolower((string) $column['COLUMN_TYPE']) !== 'bigint unsigned') {
            throw new RuntimeException(
                'Migration verification found an unexpected image attachment column type.'
            );
        }

        if (strtoupper((string) $column['IS_NULLABLE']) !== 'YES') {
            throw new RuntimeException(
                'Migration verification found that the image attachment column is not nullable.'
            );
        }
    }
}
