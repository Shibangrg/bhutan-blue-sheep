<?php
/**
 * Creates the eleven Blue Sheep Business Manager tables.
 *
 * Migration 1 is prepared and tested for the verified local MySQL 8.4.0
 * development environment. This does NOT certify compatibility with the
 * legacy production MariaDB environment. Do not run in production until
 * the hosting database/PHP targets, backup, and recovery plan are verified.
 */

namespace BlueSheep\BusinessManager\Database\Migrations;

use BlueSheep\BusinessManager\Database\MigrationInterface;
use RuntimeException;

class CreateBusinessTables implements MigrationInterface
{
    /**
     * Return this migration's schema version.
     */
    public function getVersion(): int
    {
        return 1;
    }

    /**
     * Create the eleven business tables.
     *
     * MySQL DDL can implicitly commit. If this method fails part-way through,
     * the migration must be inspected and recovered manually before retrying.
     */
    public function up(): void
    {
        global $wpdb;

        $this->assertValidPrefix();
        $tables = $this->getTables();
        $this->assertNoExistingTables($tables);

        $definitions = $this->getCreateStatements($tables);

        foreach ($definitions as $statement) {
            $this->execute($statement);
        }

        // Add this circular relationship only after both tables exist.
        $this->execute(
            'ALTER TABLE `' . $tables['quotations'] . '` '
            . 'ADD CONSTRAINT `' . $this->foreignKeyName('quotation_current_version') . '` '
            . 'FOREIGN KEY (`current_version_id`) '
            . 'REFERENCES `' . $tables['quotation_versions'] . '` (`id`) '
            . 'ON DELETE RESTRICT ON UPDATE RESTRICT'
        );

        $this->verifyCreatedTables($tables);
    }

    /**
     * Drop the eleven tables only when all are present and empty.
     *
     * This is intentionally restrictive. Business records must never be
     * removed by a routine migration rollback.
     */
    public function down(): void
    {
        $this->assertValidPrefix();
        $tables = $this->getTables();
        $this->assertAllTablesExist($tables);
        $this->assertAllTablesEmpty($tables);

        // Remove the quotation -> current version side of the circular link.
        $this->execute(
            'ALTER TABLE `' . $tables['quotations'] . '` '
            . 'DROP FOREIGN KEY `' . $this->foreignKeyName('quotation_current_version') . '`'
        );

        // Drop dependent tables before their referenced tables.
        $dropOrder = array(
            'notification_log',
            'activity_log',
            'access_tokens',
            'documents',
            'payments',
            'bookings',
            'quotation_items',
            'quotation_versions',
            'quotations',
            'enquiries',
            'customers',
        );

        foreach ($dropOrder as $key) {
            $this->execute('DROP TABLE `' . $tables[$key] . '`');
        }
    }

    /**
     * Return fully prefixed table names. The WordPress prefix is never assumed.
     */
    private function getTables(): array
    {
        global $wpdb;

        $prefix = (string) $wpdb->prefix;

        return array(
            'customers' => $prefix . 'bs_customers',
            'enquiries' => $prefix . 'bs_enquiries',
            'quotations' => $prefix . 'bs_quotations',
            'quotation_versions' => $prefix . 'bs_quotation_versions',
            'quotation_items' => $prefix . 'bs_quotation_items',
            'bookings' => $prefix . 'bs_bookings',
            'payments' => $prefix . 'bs_payments',
            'documents' => $prefix . 'bs_documents',
            'access_tokens' => $prefix . 'bs_access_tokens',
            'activity_log' => $prefix . 'bs_activity_log',
            'notification_log' => $prefix . 'bs_notification_log',
        );
    }

    /**
     * Validate the configured WordPress prefix before using it in identifiers.
     */
    private function assertValidPrefix(): void
    {
        global $wpdb;

        if (!isset($wpdb->prefix) || !preg_match('/^[A-Za-z0-9_]+$/', (string) $wpdb->prefix)) {
            throw new RuntimeException('The WordPress database prefix contains unsupported characters.');
        }

        foreach ($this->getTables() as $table) {
            if (strlen($table) > 64) {
                throw new RuntimeException('A generated business table name exceeds MySQL’s 64-character identifier limit.');
            }
        }
    }

    /**
     * Produce short, database-unique foreign-key constraint names.
     */
    private function foreignKeyName(string $name): string
    {
        global $wpdb;

        $prefixHash = substr(hash('sha256', (string) $wpdb->prefix), 0, 8);

        return 'fk_bs_' . $prefixHash . '_' . $name;
    }

    /**
     * Return the table creation statements in dependency order.
     */
    private function getCreateStatements(array $t): array
    {
        $fk = function ($name) {
            return $this->foreignKeyName($name);
        };

        $options = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 '
            . 'COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC';

        return array(
            // 1. Customers
            'CREATE TABLE `' . $t['customers'] . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `full_name` VARCHAR(190) NOT NULL,
                `email` VARCHAR(254) NOT NULL,
                `phone` VARCHAR(50) NULL,
                `country` VARCHAR(100) NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_email` (`email`)
            )' . $options,

            // 2. Enquiries
            'CREATE TABLE `' . $t['enquiries'] . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `customer_id` BIGINT UNSIGNED NOT NULL,
                `enquiry_type` VARCHAR(30) NOT NULL,
                `tour_id` BIGINT UNSIGNED NULL,
                `preferred_start_date` DATE NULL,
                `preferred_end_date` DATE NULL,
                `dates_flexible` TINYINT(1) NOT NULL DEFAULT 0,
                `traveller_count` BIGINT UNSIGNED NULL,
                `adults_count` BIGINT UNSIGNED NULL,
                `children_count` BIGINT UNSIGNED NULL,
                `destinations` LONGTEXT NULL,
                `interests` LONGTEXT NULL,
                `budget_range` VARCHAR(100) NULL,
                `additional_requirements` TEXT NULL,
                `status` VARCHAR(40) NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_customer_id` (`customer_id`),
                KEY `idx_tour_id` (`tour_id`),
                KEY `idx_status_created_at` (`status`, `created_at`),
                CONSTRAINT `' . $fk('enquiries_customer') . '`
                    FOREIGN KEY (`customer_id`) REFERENCES `' . $t['customers'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT
            )' . $options,

            // 3. Quotations. current_version_id is linked after versions exists.
            'CREATE TABLE `' . $t['quotations'] . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `enquiry_id` BIGINT UNSIGNED NOT NULL,
                `customer_id` BIGINT UNSIGNED NOT NULL,
                `reference_number` VARCHAR(50) NOT NULL,
                `current_version_id` BIGINT UNSIGNED NULL,
                `status` VARCHAR(40) NOT NULL,
                `created_by` BIGINT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_reference_number` (`reference_number`),
                KEY `idx_enquiry_id` (`enquiry_id`),
                KEY `idx_customer_id` (`customer_id`),
                KEY `idx_current_version_id` (`current_version_id`),
                KEY `idx_status` (`status`),
                KEY `idx_created_at` (`created_at`),
                CONSTRAINT `' . $fk('quotations_enquiry') . '`
                    FOREIGN KEY (`enquiry_id`) REFERENCES `' . $t['enquiries'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT `' . $fk('quotations_customer') . '`
                    FOREIGN KEY (`customer_id`) REFERENCES `' . $t['customers'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT
            )' . $options,

            // 4. Quotation versions
            'CREATE TABLE `' . $t['quotation_versions'] . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `quotation_id` BIGINT UNSIGNED NOT NULL,
                `version_number` BIGINT UNSIGNED NOT NULL,
                `currency` CHAR(3) NOT NULL,
                `base_currency` CHAR(3) NULL,
                `exchange_rate` DECIMAL(20,10) NULL,
                `exchange_rate_source` VARCHAR(190) NULL,
                `exchange_rate_date` DATE NULL,
                `subtotal` DECIMAL(19,4) NOT NULL,
                `discount` DECIMAL(19,4) NOT NULL,
                `total_amount` DECIMAL(19,4) NOT NULL,
                `deposit_type` VARCHAR(20) NULL,
                `deposit_value` DECIMAL(19,4) NULL,
                `balance_due_date` DATE NULL,
                `cancellation_terms` TEXT NULL,
                `terms_and_conditions` TEXT NULL,
                `valid_until` DATETIME NULL,
                `accepted_at` DATETIME NULL,
                `acceptance_method` VARCHAR(40) NULL,
                `acceptance_reference` VARCHAR(255) NULL,
                `created_at` DATETIME NOT NULL,
                `status` VARCHAR(40) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_quotation_version` (`quotation_id`, `version_number`),
                KEY `idx_valid_until` (`valid_until`),
                KEY `idx_accepted_at` (`accepted_at`),
                KEY `idx_status` (`status`),
                CONSTRAINT `' . $fk('quotation_versions_quotation') . '`
                    FOREIGN KEY (`quotation_id`) REFERENCES `' . $t['quotations'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT
            )' . $options,

            // 5. Quotation items
            'CREATE TABLE `' . $t['quotation_items'] . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `quotation_version_id` BIGINT UNSIGNED NOT NULL,
                `item_type` VARCHAR(40) NOT NULL,
                `day_number` BIGINT UNSIGNED NULL,
                `title` VARCHAR(190) NOT NULL,
                `description` TEXT NULL,
                `quantity` DECIMAL(10,2) NOT NULL,
                `unit_amount` DECIMAL(19,4) NOT NULL,
                `total_amount` DECIMAL(19,4) NOT NULL,
                `sort_order` BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_item_type` (`item_type`),
                KEY `idx_version_sort` (`quotation_version_id`, `sort_order`),
                CONSTRAINT `' . $fk('quotation_items_version') . '`
                    FOREIGN KEY (`quotation_version_id`) REFERENCES `' . $t['quotation_versions'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT
            )' . $options,

            // 6. Bookings
            'CREATE TABLE `' . $t['bookings'] . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `customer_id` BIGINT UNSIGNED NOT NULL,
                `quotation_id` BIGINT UNSIGNED NOT NULL,
                `accepted_version_id` BIGINT UNSIGNED NOT NULL,
                `booking_reference` VARCHAR(40) NOT NULL,
                `travel_start_date` DATE NOT NULL,
                `travel_end_date` DATE NOT NULL,
                `traveller_count` INT UNSIGNED NOT NULL,
                `status` VARCHAR(40) NOT NULL,
                `confirmed_at` DATETIME NULL,
                `confirmation_exception_by` BIGINT UNSIGNED NULL,
                `confirmation_exception_reason` TEXT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_booking_reference` (`booking_reference`),
                KEY `idx_status_travel_start` (`status`, `travel_start_date`),
                CONSTRAINT `' . $fk('bookings_customer') . '`
                    FOREIGN KEY (`customer_id`) REFERENCES `' . $t['customers'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT `' . $fk('bookings_quotation') . '`
                    FOREIGN KEY (`quotation_id`) REFERENCES `' . $t['quotations'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT `' . $fk('bookings_accepted_version') . '`
                    FOREIGN KEY (`accepted_version_id`) REFERENCES `' . $t['quotation_versions'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT
            )' . $options,

            // 7. Payments
            'CREATE TABLE `' . $t['payments'] . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `booking_id` BIGINT UNSIGNED NOT NULL,
                `payment_type` VARCHAR(20) NOT NULL,
                `related_payment_id` BIGINT UNSIGNED NULL,
                `amount` DECIMAL(19,4) NOT NULL,
                `currency` CHAR(3) NOT NULL,
                `quotation_currency_equivalent` DECIMAL(19,4) NULL,
                `exchange_rate` DECIMAL(20,10) NULL,
                `exchange_rate_source` VARCHAR(255) NULL,
                `exchange_rate_date` DATE NULL,
                `payment_method` VARCHAR(40) NOT NULL,
                `payment_date` DATE NOT NULL,
                `reference` VARCHAR(255) NULL,
                `verification_status` VARCHAR(40) NOT NULL,
                `verified_by` BIGINT UNSIGNED NULL,
                `verified_at` DATETIME NULL,
                `verification_reason` TEXT NULL,
                `evidence_reference` VARCHAR(500) NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_verification_status` (`verification_status`),
                CONSTRAINT `' . $fk('payments_booking') . '`
                    FOREIGN KEY (`booking_id`) REFERENCES `' . $t['bookings'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT `' . $fk('payments_related_payment') . '`
                    FOREIGN KEY (`related_payment_id`) REFERENCES `' . $t['payments'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT
            )' . $options,

            // 8. Documents, including the approved payment_id relationship.
            'CREATE TABLE `' . $t['documents'] . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `customer_id` BIGINT UNSIGNED NOT NULL,
                `quotation_id` BIGINT UNSIGNED NULL,
                `booking_id` BIGINT UNSIGNED NULL,
                `payment_id` BIGINT UNSIGNED NULL,
                `document_type` VARCHAR(40) NOT NULL,
                `file_reference` VARCHAR(500) NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `' . $fk('documents_customer') . '`
                    FOREIGN KEY (`customer_id`) REFERENCES `' . $t['customers'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT `' . $fk('documents_quotation') . '`
                    FOREIGN KEY (`quotation_id`) REFERENCES `' . $t['quotations'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT `' . $fk('documents_booking') . '`
                    FOREIGN KEY (`booking_id`) REFERENCES `' . $t['bookings'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT `' . $fk('documents_payment') . '`
                    FOREIGN KEY (`payment_id`) REFERENCES `' . $t['payments'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT
            )' . $options,

            // 9. Guest access tokens. Resource links are polymorphic.
            'CREATE TABLE `' . $t['access_tokens'] . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `token_hash` VARCHAR(255) NOT NULL,
                `customer_id` BIGINT UNSIGNED NOT NULL,
                `entity_type` VARCHAR(40) NOT NULL,
                `entity_id` BIGINT UNSIGNED NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `revoked_at` DATETIME NULL,
                `last_used_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL,
                `created_by` BIGINT UNSIGNED NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_token_hash` (`token_hash`),
                KEY `idx_customer_id` (`customer_id`),
                KEY `idx_entity` (`entity_type`, `entity_id`),
                KEY `idx_expiry_revocation` (`expires_at`, `revoked_at`),
                CONSTRAINT `' . $fk('access_tokens_customer') . '`
                    FOREIGN KEY (`customer_id`) REFERENCES `' . $t['customers'] . '` (`id`)
                    ON DELETE RESTRICT ON UPDATE RESTRICT
            )' . $options,

            // 10. Activity log. Polymorphic entity references are application-validated.
            'CREATE TABLE `' . $t['activity_log'] . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NULL,
                `entity_type` VARCHAR(40) NOT NULL,
                `entity_id` BIGINT UNSIGNED NOT NULL,
                `action` VARCHAR(80) NOT NULL,
                `description` TEXT NULL,
                `created_at` DATETIME NOT NULL,
                `previous_status` VARCHAR(40) NULL,
                `new_status` VARCHAR(40) NULL,
                `reason` TEXT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_entity_created` (`entity_type`, `entity_id`, `created_at`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_created_at` (`created_at`)
            )' . $options,

            // 11. Notification log. Related entity references are polymorphic.
            'CREATE TABLE `' . $t['notification_log'] . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `event_type` VARCHAR(80) NOT NULL,
                `related_entity_type` VARCHAR(40) NULL,
                `related_entity_id` BIGINT UNSIGNED NULL,
                `recipient_reference` VARCHAR(255) NULL,
                `delivery_status` VARCHAR(30) NOT NULL,
                `provider_reference` VARCHAR(255) NULL,
                `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_attempt_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_delivery_created` (`delivery_status`, `created_at`),
                KEY `idx_event_type` (`event_type`),
                KEY `idx_related_entity` (`related_entity_type`, `related_entity_id`),
                KEY `idx_created_at` (`created_at`)
            )' . $options,
        );
    }

    /**
     * Execute one DDL statement and surface the database error.
     */
    private function execute(string $sql): void
    {
        global $wpdb;

        $result = $wpdb->query($sql);

        if ($result === false) {
            $detail = !empty($wpdb->last_error) ? $wpdb->last_error : 'Unknown database error.';
            throw new RuntimeException('Business-table migration failed: ' . $detail);
        }
    }

    /**
     * Ensure none of the eleven target tables exists before the first run.
     */
    private function assertNoExistingTables(array $tables): void
    {
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                throw new RuntimeException(
                    'Migration stopped because table ' . $table . ' already exists. '
                    . 'Inspect the schema and migration log before proceeding.'
                );
            }
        }
    }

    /**
     * Ensure all expected tables exist before attempting a rollback.
     */
    private function assertAllTablesExist(array $tables): void
    {
        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                throw new RuntimeException(
                    'Rollback stopped because expected table ' . $table . ' is missing. '
                    . 'A partial migration requires manual recovery.'
                );
            }
        }
    }

    /**
     * Check whether a table exists in the current database.
     */
    private function tableExists(string $table): bool
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                $table
            )
        );

        return (int) $count === 1;
    }

    /**
     * Verify table settings, expected columns, declared indexes, and foreign keys.
     */
    private function verifyCreatedTables(array $tables): void
    {
        global $wpdb;

        $expectedColumns = array(
            'customers' => array(
                'id', 'full_name', 'email', 'phone', 'country', 'created_at', 'updated_at',
            ),
            'enquiries' => array(
                'id', 'customer_id', 'enquiry_type', 'tour_id',
                'preferred_start_date', 'preferred_end_date', 'dates_flexible',
                'traveller_count', 'adults_count', 'children_count',
                'destinations', 'interests', 'budget_range',
                'additional_requirements', 'status', 'created_at', 'updated_at',
            ),
            'quotations' => array(
                'id', 'enquiry_id', 'customer_id', 'reference_number',
                'current_version_id', 'status', 'created_by', 'created_at', 'updated_at',
            ),
            'quotation_versions' => array(
                'id', 'quotation_id', 'version_number', 'currency', 'base_currency',
                'exchange_rate', 'exchange_rate_source', 'exchange_rate_date',
                'subtotal', 'discount', 'total_amount', 'deposit_type', 'deposit_value',
                'balance_due_date', 'cancellation_terms', 'terms_and_conditions',
                'valid_until', 'accepted_at', 'acceptance_method',
                'acceptance_reference', 'created_at', 'status',
            ),
            'quotation_items' => array(
                'id', 'quotation_version_id', 'item_type', 'day_number',
                'title', 'description', 'quantity', 'unit_amount',
                'total_amount', 'sort_order',
            ),
            'bookings' => array(
                'id', 'customer_id', 'quotation_id', 'accepted_version_id',
                'booking_reference', 'travel_start_date', 'travel_end_date',
                'traveller_count', 'status', 'confirmed_at',
                'confirmation_exception_by', 'confirmation_exception_reason',
                'created_at', 'updated_at',
            ),
            'payments' => array(
                'id', 'booking_id', 'payment_type', 'related_payment_id',
                'amount', 'currency', 'quotation_currency_equivalent',
                'exchange_rate', 'exchange_rate_source', 'exchange_rate_date',
                'payment_method', 'payment_date', 'reference',
                'verification_status', 'verified_by', 'verified_at',
                'verification_reason', 'evidence_reference', 'created_at',
            ),
            'documents' => array(
                'id', 'customer_id', 'quotation_id', 'booking_id', 'payment_id',
                'document_type', 'file_reference', 'created_at',
            ),
            'access_tokens' => array(
                'id', 'token_hash', 'customer_id', 'entity_type', 'entity_id',
                'expires_at', 'revoked_at', 'last_used_at', 'created_at', 'created_by',
            ),
            'activity_log' => array(
                'id', 'user_id', 'entity_type', 'entity_id', 'action',
                'description', 'created_at', 'previous_status', 'new_status', 'reason',
            ),
            'notification_log' => array(
                'id', 'event_type', 'related_entity_type', 'related_entity_id',
                'recipient_reference', 'delivery_status', 'provider_reference',
                'attempt_count', 'last_attempt_at', 'created_at',
            ),
        );

        // Verify SQL types, nullability, defaults, and AUTO_INCREMENT attributes.
        $requiredColumn = function ($type, $extra = '') {
            return array(
                'type' => strtolower($type),
                'nullable' => 'NO',
                'default' => null,
                'extra' => strtolower($extra),
            );
        };

        $optionalColumn = function ($type) {
            return array(
                'type' => strtolower($type),
                'nullable' => 'YES',
                'default' => null,
                'extra' => '',
            );
        };

        $requiredDefaultColumn = function ($type, $default) {
            return array(
                'type' => strtolower($type),
                'nullable' => 'NO',
                'default' => (string) $default,
                'extra' => '',
            );
        };

        $expectedColumnMetadata = array(
            'customers' => array(
                'id' => $requiredColumn('bigint unsigned', 'auto_increment'),
                'full_name' => $requiredColumn('varchar(190)'),
                'email' => $requiredColumn('varchar(254)'),
                'phone' => $optionalColumn('varchar(50)'),
                'country' => $optionalColumn('varchar(100)'),
                'created_at' => $requiredColumn('datetime'),
                'updated_at' => $requiredColumn('datetime'),
            ),
            'enquiries' => array(
                'id' => $requiredColumn('bigint unsigned', 'auto_increment'),
                'customer_id' => $requiredColumn('bigint unsigned'),
                'enquiry_type' => $requiredColumn('varchar(30)'),
                'tour_id' => $optionalColumn('bigint unsigned'),
                'preferred_start_date' => $optionalColumn('date'),
                'preferred_end_date' => $optionalColumn('date'),
                // Current DDL default is false (0); DATABASE-SCHEMA.md v0.2 does not specify this default.
                // Confirm this business rule before executing the migration.
                'dates_flexible' => $requiredDefaultColumn('tinyint(1)', 0),
                'traveller_count' => $optionalColumn('bigint unsigned'),
                'adults_count' => $optionalColumn('bigint unsigned'),
                'children_count' => $optionalColumn('bigint unsigned'),
                'destinations' => $optionalColumn('longtext'),
                'interests' => $optionalColumn('longtext'),
                'budget_range' => $optionalColumn('varchar(100)'),
                'additional_requirements' => $optionalColumn('text'),
                'status' => $requiredColumn('varchar(40)'),
                'created_at' => $requiredColumn('datetime'),
                'updated_at' => $requiredColumn('datetime'),
            ),
            'quotations' => array(
                'id' => $requiredColumn('bigint unsigned', 'auto_increment'),
                'enquiry_id' => $requiredColumn('bigint unsigned'),
                'customer_id' => $requiredColumn('bigint unsigned'),
                'reference_number' => $requiredColumn('varchar(50)'),
                'current_version_id' => $optionalColumn('bigint unsigned'),
                'status' => $requiredColumn('varchar(40)'),
                'created_by' => $requiredColumn('bigint unsigned'),
                'created_at' => $requiredColumn('datetime'),
                'updated_at' => $requiredColumn('datetime'),
            ),
            'quotation_versions' => array(
                'id' => $requiredColumn('bigint unsigned', 'auto_increment'),
                'quotation_id' => $requiredColumn('bigint unsigned'),
                'version_number' => $requiredColumn('bigint unsigned'),
                'currency' => $requiredColumn('char(3)'),
                'base_currency' => $optionalColumn('char(3)'),
                'exchange_rate' => $optionalColumn('decimal(20,10)'),
                'exchange_rate_source' => $optionalColumn('varchar(190)'),
                'exchange_rate_date' => $optionalColumn('date'),
                'subtotal' => $requiredColumn('decimal(19,4)'),
                'discount' => $requiredColumn('decimal(19,4)'),
                'total_amount' => $requiredColumn('decimal(19,4)'),
                'deposit_type' => $optionalColumn('varchar(20)'),
                'deposit_value' => $optionalColumn('decimal(19,4)'),
                'balance_due_date' => $optionalColumn('date'),
                'cancellation_terms' => $optionalColumn('text'),
                'terms_and_conditions' => $optionalColumn('text'),
                'valid_until' => $optionalColumn('datetime'),
                'accepted_at' => $optionalColumn('datetime'),
                'acceptance_method' => $optionalColumn('varchar(40)'),
                'acceptance_reference' => $optionalColumn('varchar(255)'),
                'created_at' => $requiredColumn('datetime'),
                'status' => $requiredColumn('varchar(40)'),
            ),
            'quotation_items' => array(
                'id' => $requiredColumn('bigint unsigned', 'auto_increment'),
                'quotation_version_id' => $requiredColumn('bigint unsigned'),
                'item_type' => $requiredColumn('varchar(40)'),
                'day_number' => $optionalColumn('bigint unsigned'),
                'title' => $requiredColumn('varchar(190)'),
                'description' => $optionalColumn('text'),
                'quantity' => $requiredColumn('decimal(10,2)'),
                'unit_amount' => $requiredColumn('decimal(19,4)'),
                'total_amount' => $requiredColumn('decimal(19,4)'),
                'sort_order' => $requiredColumn('bigint unsigned'),
            ),
            'bookings' => array(
                'id' => $requiredColumn('bigint unsigned', 'auto_increment'),
                'customer_id' => $requiredColumn('bigint unsigned'),
                'quotation_id' => $requiredColumn('bigint unsigned'),
                'accepted_version_id' => $requiredColumn('bigint unsigned'),
                'booking_reference' => $requiredColumn('varchar(40)'),
                'travel_start_date' => $requiredColumn('date'),
                'travel_end_date' => $requiredColumn('date'),
                'traveller_count' => $requiredColumn('int unsigned'),
                'status' => $requiredColumn('varchar(40)'),
                'confirmed_at' => $optionalColumn('datetime'),
                'confirmation_exception_by' => $optionalColumn('bigint unsigned'),
                'confirmation_exception_reason' => $optionalColumn('text'),
                'created_at' => $requiredColumn('datetime'),
                'updated_at' => $requiredColumn('datetime'),
            ),
            'payments' => array(
                'id' => $requiredColumn('bigint unsigned', 'auto_increment'),
                'booking_id' => $requiredColumn('bigint unsigned'),
                'payment_type' => $requiredColumn('varchar(20)'),
                'related_payment_id' => $optionalColumn('bigint unsigned'),
                'amount' => $requiredColumn('decimal(19,4)'),
                'currency' => $requiredColumn('char(3)'),
                'quotation_currency_equivalent' => $optionalColumn('decimal(19,4)'),
                'exchange_rate' => $optionalColumn('decimal(20,10)'),
                'exchange_rate_source' => $optionalColumn('varchar(255)'),
                'exchange_rate_date' => $optionalColumn('date'),
                'payment_method' => $requiredColumn('varchar(40)'),
                'payment_date' => $requiredColumn('date'),
                'reference' => $optionalColumn('varchar(255)'),
                'verification_status' => $requiredColumn('varchar(40)'),
                'verified_by' => $optionalColumn('bigint unsigned'),
                'verified_at' => $optionalColumn('datetime'),
                'verification_reason' => $optionalColumn('text'),
                'evidence_reference' => $optionalColumn('varchar(500)'),
                'created_at' => $requiredColumn('datetime'),
            ),
            'documents' => array(
                'id' => $requiredColumn('bigint unsigned', 'auto_increment'),
                'customer_id' => $requiredColumn('bigint unsigned'),
                'quotation_id' => $optionalColumn('bigint unsigned'),
                'booking_id' => $optionalColumn('bigint unsigned'),
                'payment_id' => $optionalColumn('bigint unsigned'),
                'document_type' => $requiredColumn('varchar(40)'),
                'file_reference' => $requiredColumn('varchar(500)'),
                'created_at' => $requiredColumn('datetime'),
            ),
            'access_tokens' => array(
                'id' => $requiredColumn('bigint unsigned', 'auto_increment'),
                'token_hash' => $requiredColumn('varchar(255)'),
                'customer_id' => $requiredColumn('bigint unsigned'),
                'entity_type' => $requiredColumn('varchar(40)'),
                'entity_id' => $requiredColumn('bigint unsigned'),
                'expires_at' => $requiredColumn('datetime'),
                'revoked_at' => $optionalColumn('datetime'),
                'last_used_at' => $optionalColumn('datetime'),
                'created_at' => $requiredColumn('datetime'),
                'created_by' => $optionalColumn('bigint unsigned'),
            ),
            'activity_log' => array(
                'id' => $requiredColumn('bigint unsigned', 'auto_increment'),
                'user_id' => $optionalColumn('bigint unsigned'),
                'entity_type' => $requiredColumn('varchar(40)'),
                'entity_id' => $requiredColumn('bigint unsigned'),
                'action' => $requiredColumn('varchar(80)'),
                'description' => $optionalColumn('text'),
                'created_at' => $requiredColumn('datetime'),
                'previous_status' => $optionalColumn('varchar(40)'),
                'new_status' => $optionalColumn('varchar(40)'),
                'reason' => $optionalColumn('text'),
            ),
            'notification_log' => array(
                'id' => $requiredColumn('bigint unsigned', 'auto_increment'),
                'event_type' => $requiredColumn('varchar(80)'),
                'related_entity_type' => $optionalColumn('varchar(40)'),
                'related_entity_id' => $optionalColumn('bigint unsigned'),
                'recipient_reference' => $optionalColumn('varchar(255)'),
                'delivery_status' => $requiredColumn('varchar(30)'),
                'provider_reference' => $optionalColumn('varchar(255)'),
                'attempt_count' => $requiredDefaultColumn('int unsigned', 0),
                'last_attempt_at' => $optionalColumn('datetime'),
                'created_at' => $requiredColumn('datetime'),
            ),
        );

        // These are the indexes explicitly declared in the migration.
        // InnoDB may also create supporting indexes automatically for foreign keys.
        $expectedIndexes = array(
            'customers' => array(
                'PRIMARY' => array(array('id'), 0),
                'idx_email' => array(array('email'), 1),
            ),
            'enquiries' => array(
                'PRIMARY' => array(array('id'), 0),
                'idx_customer_id' => array(array('customer_id'), 1),
                'idx_tour_id' => array(array('tour_id'), 1),
                'idx_status_created_at' => array(array('status', 'created_at'), 1),
            ),
            'quotations' => array(
                'PRIMARY' => array(array('id'), 0),
                'uq_reference_number' => array(array('reference_number'), 0),
                'idx_enquiry_id' => array(array('enquiry_id'), 1),
                'idx_customer_id' => array(array('customer_id'), 1),
                'idx_current_version_id' => array(array('current_version_id'), 1),
                'idx_status' => array(array('status'), 1),
                'idx_created_at' => array(array('created_at'), 1),
            ),
            'quotation_versions' => array(
                'PRIMARY' => array(array('id'), 0),
                'uq_quotation_version' => array(array('quotation_id', 'version_number'), 0),
                'idx_valid_until' => array(array('valid_until'), 1),
                'idx_accepted_at' => array(array('accepted_at'), 1),
                'idx_status' => array(array('status'), 1),
            ),
            'quotation_items' => array(
                'PRIMARY' => array(array('id'), 0),
                'idx_item_type' => array(array('item_type'), 1),
                'idx_version_sort' => array(array('quotation_version_id', 'sort_order'), 1),
            ),
            'bookings' => array(
                'PRIMARY' => array(array('id'), 0),
                'uq_booking_reference' => array(array('booking_reference'), 0),
                'idx_status_travel_start' => array(array('status', 'travel_start_date'), 1),
            ),
            'payments' => array(
                'PRIMARY' => array(array('id'), 0),
                'idx_verification_status' => array(array('verification_status'), 1),
            ),
            'documents' => array(
                'PRIMARY' => array(array('id'), 0),
            ),
            'access_tokens' => array(
                'PRIMARY' => array(array('id'), 0),
                'uq_token_hash' => array(array('token_hash'), 0),
                'idx_customer_id' => array(array('customer_id'), 1),
                'idx_entity' => array(array('entity_type', 'entity_id'), 1),
                'idx_expiry_revocation' => array(array('expires_at', 'revoked_at'), 1),
            ),
            'activity_log' => array(
                'PRIMARY' => array(array('id'), 0),
                'idx_entity_created' => array(array('entity_type', 'entity_id', 'created_at'), 1),
                'idx_user_id' => array(array('user_id'), 1),
                'idx_created_at' => array(array('created_at'), 1),
            ),
            'notification_log' => array(
                'PRIMARY' => array(array('id'), 0),
                'idx_delivery_created' => array(array('delivery_status', 'created_at'), 1),
                'idx_event_type' => array(array('event_type'), 1),
                'idx_related_entity' => array(array('related_entity_type', 'related_entity_id'), 1),
                'idx_created_at' => array(array('created_at'), 1),
            ),
        );

        // Each entry contains: constraint suffix, local column, referenced table key.
        $expectedForeignKeys = array(
            'customers' => array(),
            'enquiries' => array(
                array('enquiries_customer', 'customer_id', 'customers'),
            ),
            'quotations' => array(
                array('quotations_enquiry', 'enquiry_id', 'enquiries'),
                array('quotations_customer', 'customer_id', 'customers'),
                array('quotation_current_version', 'current_version_id', 'quotation_versions'),
            ),
            'quotation_versions' => array(
                array('quotation_versions_quotation', 'quotation_id', 'quotations'),
            ),
            'quotation_items' => array(
                array('quotation_items_version', 'quotation_version_id', 'quotation_versions'),
            ),
            'bookings' => array(
                array('bookings_customer', 'customer_id', 'customers'),
                array('bookings_quotation', 'quotation_id', 'quotations'),
                array('bookings_accepted_version', 'accepted_version_id', 'quotation_versions'),
            ),
            'payments' => array(
                array('payments_booking', 'booking_id', 'bookings'),
                array('payments_related_payment', 'related_payment_id', 'payments'),
            ),
            'documents' => array(
                array('documents_customer', 'customer_id', 'customers'),
                array('documents_quotation', 'quotation_id', 'quotations'),
                array('documents_booking', 'booking_id', 'bookings'),
                array('documents_payment', 'payment_id', 'payments'),
            ),
            'access_tokens' => array(
                array('access_tokens_customer', 'customer_id', 'customers'),
            ),
            'activity_log' => array(),
            'notification_log' => array(),
        );

        foreach ($tables as $key => $table) {
            // 1. Verify storage engine, collation, and row format.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT ENGINE, TABLE_COLLATION, ROW_FORMAT
                     FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                    $table
                ),
                ARRAY_A
            );

            if (!is_array($row)) {
                throw new RuntimeException(
                    'Post-migration verification could not find table ' . $table . '.'
                );
            }

            if (strtoupper((string) $row['ENGINE']) !== 'INNODB') {
                throw new RuntimeException('Unexpected storage engine on ' . $table . '.');
            }

            if (strtolower((string) $row['TABLE_COLLATION']) !== 'utf8mb4_unicode_ci') {
                throw new RuntimeException('Unexpected collation on ' . $table . '.');
            }

            if (strtolower((string) $row['ROW_FORMAT']) !== 'dynamic') {
                throw new RuntimeException('Unexpected row format on ' . $table . '.');
            }

            // 2. Verify names, order, SQL types, nullability, defaults, and extras.
            $columnRows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT COLUMN_NAME, LOWER(COLUMN_TYPE) AS COLUMN_TYPE,
                            IS_NULLABLE, COLUMN_DEFAULT, LOWER(EXTRA) AS EXTRA
                     FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
                     ORDER BY ORDINAL_POSITION',
                    $table
                ),
                ARRAY_A
            );

            if (!is_array($columnRows)) {
                throw new RuntimeException('Column metadata could not be read for ' . $table . '.');
            }

            $actualColumns = array();
            $actualColumnMetadata = array();

            foreach ($columnRows as $columnRow) {
                $columnName = $columnRow['COLUMN_NAME'];
                $actualColumns[] = $columnName;

                $columnDefault = $columnRow['COLUMN_DEFAULT'];
                if ($columnDefault !== null) {
                    $columnDefault = (string) $columnDefault;
                }

                $actualColumnMetadata[$columnName] = array(
                    'type' => strtolower((string) $columnRow['COLUMN_TYPE']),
                    'nullable' => strtoupper((string) $columnRow['IS_NULLABLE']),
                    'default' => $columnDefault,
                    'extra' => strtolower(trim((string) $columnRow['EXTRA'])),
                );
            }

            if ($actualColumns !== $expectedColumns[$key]) {
                throw new RuntimeException(
                    'Column names or order do not match the expected definition for ' . $table . '.'
                );
            }

            foreach ($expectedColumnMetadata[$key] as $columnName => $expectedDefinition) {
                if (!isset($actualColumnMetadata[$columnName])) {
                    throw new RuntimeException(
                        'Missing column metadata for ' . $table . '.' . $columnName . '.'
                    );
                }

                if ($actualColumnMetadata[$columnName] !== $expectedDefinition) {
                    throw new RuntimeException(
                        'Column metadata mismatch for ' . $table . '.' . $columnName
                        . '. Expected type, nullability, default, and extra attributes do not match.'
                    );
                }
            }

            // 3. Verify every explicitly declared index and its column order.
            $indexRows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT INDEX_NAME, NON_UNIQUE,
                            GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR \',\') AS INDEX_COLUMNS
                     FROM information_schema.STATISTICS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
                     GROUP BY INDEX_NAME, NON_UNIQUE',
                    $table
                ),
                ARRAY_A
            );

            if (!is_array($indexRows)) {
                throw new RuntimeException('Index metadata could not be read for ' . $table . '.');
            }

            $actualIndexes = array();

            foreach ($indexRows as $indexRow) {
                $actualIndexes[$indexRow['INDEX_NAME']] = array(
                    'columns' => explode(',', (string) $indexRow['INDEX_COLUMNS']),
                    'non_unique' => (int) $indexRow['NON_UNIQUE'],
                );
            }

            foreach ($expectedIndexes[$key] as $indexName => $definition) {
                if (!isset($actualIndexes[$indexName])) {
                    throw new RuntimeException(
                        'Missing expected index ' . $indexName . ' on ' . $table . '.'
                    );
                }

                if (
                    $actualIndexes[$indexName]['columns'] !== $definition[0]
                    || $actualIndexes[$indexName]['non_unique'] !== $definition[1]
                ) {
                    throw new RuntimeException(
                        'Index definition mismatch for ' . $indexName . ' on ' . $table . '.'
                    );
                }
            }

            // 4. Verify that the table has exactly the expected foreign keys.
            $actualForeignKeys = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT
                        kcu.CONSTRAINT_NAME,
                        kcu.COLUMN_NAME,
                        kcu.REFERENCED_TABLE_NAME,
                        kcu.REFERENCED_COLUMN_NAME,
                        rc.DELETE_RULE,
                        rc.UPDATE_RULE
                     FROM information_schema.KEY_COLUMN_USAGE AS kcu
                     INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS AS rc
                        ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                        AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                        AND rc.TABLE_NAME = kcu.TABLE_NAME
                     WHERE kcu.CONSTRAINT_SCHEMA = DATABASE()
                        AND kcu.TABLE_NAME = %s
                        AND kcu.REFERENCED_TABLE_NAME IS NOT NULL',
                    $table
                ),
                ARRAY_A
            );

            if (!is_array($actualForeignKeys)) {
                throw new RuntimeException('Foreign-key metadata could not be read for ' . $table . '.');
            }

            $actualForeignKeyMap = array();

            foreach ($actualForeignKeys as $foreignKeyRow) {
                $actualForeignKeyMap[$foreignKeyRow['CONSTRAINT_NAME']] = $foreignKeyRow;
            }

            $expectedForeignKeyNames = array();

            foreach ($expectedForeignKeys[$key] as $foreignKey) {
                $expectedForeignKeyNames[] = $this->foreignKeyName($foreignKey[0]);
            }

            $actualForeignKeyNames = array_keys($actualForeignKeyMap);
            sort($actualForeignKeyNames);
            sort($expectedForeignKeyNames);

            if ($actualForeignKeyNames !== $expectedForeignKeyNames) {
                throw new RuntimeException(
                    'Foreign-key constraint list mismatch for ' . $table . '.'
                );
            }

            // 5. Verify each foreign key's source, target, and restrictive rules.
            foreach ($expectedForeignKeys[$key] as $foreignKey) {
                list($name, $column, $referencedTableKey) = $foreignKey;

                $constraintName = $this->foreignKeyName($name);
                $referencedTable = $tables[$referencedTableKey];
                $actualForeignKey = $actualForeignKeyMap[$constraintName];

                if (
                    $actualForeignKey['COLUMN_NAME'] !== $column
                    || $actualForeignKey['REFERENCED_TABLE_NAME'] !== $referencedTable
                    || $actualForeignKey['REFERENCED_COLUMN_NAME'] !== 'id'
                    || strtoupper((string) $actualForeignKey['DELETE_RULE']) !== 'RESTRICT'
                    || strtoupper((string) $actualForeignKey['UPDATE_RULE']) !== 'RESTRICT'
                ) {
                    throw new RuntimeException(
                        'Foreign-key verification failed for ' . $constraintName . '.'
                    );
                }
            }
        }
    }

    /**
     * Refuse rollback if any business table contains records.
     */
    private function assertAllTablesEmpty(array $tables): void
    {
        global $wpdb;

        foreach ($tables as $table) {
            $count = $wpdb->get_var('SELECT COUNT(*) FROM `' . $table . '`');

            if ($count === null || (int) $count !== 0) {
                throw new RuntimeException(
                    'Rollback stopped because table ' . $table . ' is not confirmed empty. '
                    . 'Business data will not be deleted by this migration.'
                );
            }
        }
    }
}
