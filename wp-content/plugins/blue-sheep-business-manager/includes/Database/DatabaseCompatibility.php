<?php

namespace BlueSheep\BusinessManager\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Detects the database and PHP environment.
 *
 * This class only inspects the environment.
 * It does not create or modify database tables.
 */
final class DatabaseCompatibility {

    /**
     * Inspect the current environment.
     *
     * @return array<string, mixed>
     */
    public function inspect(): array {

        global $wpdb;

        $database_version = $wpdb->get_var('SELECT VERSION()');

        $is_mariadb = is_string($database_version)
            && stripos($database_version, 'mariadb') !== false;

        $database_engine = $is_mariadb ? 'MariaDB' : 'MySQL';

        $charset_collate = $wpdb->get_charset_collate();

        return array(
            'php_version' => PHP_VERSION,
            'database_engine' => $database_engine,
            'database_version' => $database_version ?: 'Unknown',
            'database_charset' => $wpdb->charset,
            'database_collation' => $wpdb->collate,
            'charset_collate' => $charset_collate,
            'utf8mb4_supported' => $wpdb->has_cap('utf8mb4'),
        );
    }
}