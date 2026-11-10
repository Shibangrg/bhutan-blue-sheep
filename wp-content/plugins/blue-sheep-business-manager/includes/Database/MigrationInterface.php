<?php

namespace BlueSheep\BusinessManager\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Defines the contract for database migrations.
 */
interface MigrationInterface {

    /**
     * Return the migration version.
     */
    public function getVersion(): int;

    /**
     * Apply the migration.
     */
    public function up(): void;

    /**
     * Reverse the migration where safely supported.
     */
    public function down(): void;
}