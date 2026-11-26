<?php

namespace BlueSheep\BusinessManager\Database\Migrations;

use BlueSheep\BusinessManager\Database\MigrationInterface;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds database-level duplicate notification protection.
 *
 * Migration 3.
 *
 * Prevents multiple notification records for the same event
 * and related entity. Multiple delivery attempts should update
 * the existing notification record's attempt_count.
 */
final class AddNotificationDeduplicationIndex implements MigrationInterface
{
    private const INDEX_NAME = 'uq_notification_event_entity';

    /**
     * Return this migration's schema version.
     */
    public function getVersion(): int
    {
        return 3;
    }

    /**
     * Add the unique notification deduplication index.
     */
    public function up(): void
    {
        global $wpdb;

        $table = $this->getNotificationTable();

        if (!$this->tableExists($table)) {
            throw new RuntimeException(
                'Notification deduplication migration stopped because the notification table does not exist.'
            );
        }

        if ($this->indexExists($table)) {
            throw new RuntimeException(
                'Notification deduplication index already exists. Inspect the schema before proceeding.'
            );
        }

        // Check for existing duplicate event/entity combinations.
        $duplicate_count = $wpdb->get_var(
            "SELECT COUNT(*)
             FROM (
                 SELECT
                     event_type,
                     related_entity_type,
                     related_entity_id
                 FROM `{$table}`
                 WHERE related_entity_type IS NOT NULL
                   AND related_entity_id IS NOT NULL
                 GROUP BY
                     event_type,
                     related_entity_type,
                     related_entity_id
                 HAVING COUNT(*) > 1
             ) AS duplicate_notifications"
        );

        if ($duplicate_count === null) {
            throw new RuntimeException(
                'Unable to check existing notification records for duplicates.'
            );
        }

        if ((int) $duplicate_count > 0) {
            throw new RuntimeException(
                'Notification deduplication migration stopped because duplicate event/entity records exist. '
                . 'Review the notification log before retrying.'
            );
        }

        $result = $wpdb->query(
            "ALTER TABLE `{$table}`
             ADD UNIQUE KEY `" . self::INDEX_NAME . "` (
                 `event_type`,
                 `related_entity_type`,
                 `related_entity_id`
             )"
        );

        if ($result === false) {
            throw new RuntimeException(
                'Unable to add the notification deduplication index: '
                . $wpdb->last_error
            );
        }

        if (!$this->indexExists($table)) {
            throw new RuntimeException(
                'Notification deduplication index creation could not be verified.'
            );
        }
    }

    /**
     * Remove the notification deduplication index.
     *
     * This does not delete notification records.
     */
    public function down(): void
    {
        global $wpdb;

        $table = $this->getNotificationTable();

        if (!$this->tableExists($table)) {
            throw new RuntimeException(
                'Cannot remove the notification index because the notification table does not exist.'
            );
        }

        if (!$this->indexExists($table)) {
            return;
        }

        $result = $wpdb->query(
            "ALTER TABLE `{$table}`
             DROP INDEX `" . self::INDEX_NAME . "`"
        );

        if ($result === false) {
            throw new RuntimeException(
                'Unable to remove the notification deduplication index: '
                . $wpdb->last_error
            );
        }

        if ($this->indexExists($table)) {
            throw new RuntimeException(
                'Notification deduplication index removal could not be verified.'
            );
        }
    }

    /**
     * Return the prefixed notification table name.
     */
    private function getNotificationTable(): string
    {
        global $wpdb;

        $prefix = (string) $wpdb->prefix;

        if (
            $prefix === '' ||
            !preg_match('/^[A-Za-z0-9_]+$/', $prefix)
        ) {
            throw new RuntimeException(
                'The WordPress database prefix is invalid.'
            );
        }

        $table = $prefix . 'bs_notification_log';

        if (strlen($table) > 64) {
            throw new RuntimeException(
                'The notification table name exceeds MySQL’s identifier limit.'
            );
        }

        return $table;
    }

    /**
     * Check whether the notification table exists.
     */
    private function tableExists(string $table): bool
    {
        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = %s',
                $table
            )
        );

        return (int) $result === 1;
    }

    /**
     * Check whether the deduplication index exists.
     */
    private function indexExists(string $table): bool
    {
        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = %s
                   AND INDEX_NAME = %s',
                $table,
                self::INDEX_NAME
            )
        );

        return (int) $result > 0;
    }
}
