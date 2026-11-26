<?php

namespace BlueSheep\BusinessManager\Modules\Customers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles database operations for customer records.
 */
final class CustomerRepository
{
    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Customer table name.
     */
    private string $table;

    /**
     * Initialize the customer repository.
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'bs_customers';
    }

    /**
     * Retrieve customers with pagination and optional search.
     *
     * @param int    $limit  Number of records to retrieve.
     * @param int    $offset Number of records to skip.
     * @param string $search Search term.
     *
     * @return array
     */
    public function getAll(
        int $limit = 20,
        int $offset = 0,
        string $search = ''
    ): array {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        if ($search !== '') {
            $like = '%' . $this->wpdb->esc_like($search) . '%';

            $sql = $this->wpdb->prepare(
                "SELECT id, full_name, email, phone, country, created_at
                FROM {$this->table}
                WHERE full_name LIKE %s
                   OR email LIKE %s
                   OR phone LIKE %s
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
                $like,
                $like,
                $like,
                $limit,
                $offset
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT id, full_name, email, phone, country, created_at
                FROM {$this->table}
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Retrieve a customer by ID.
     *
     * @param int $customer_id Customer ID.
     *
     * @return array|null
     */
    public function getById(int $customer_id): ?array
    {
        if ($customer_id <= 0) {
            return null;
        }

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, full_name, email, phone, country, created_at, updated_at
                FROM {$this->table}
                WHERE id = %d",
                $customer_id
            ),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    /**
     * Count customer records matching an optional search.
     *
     * @param string $search Search term.
     *
     * @return int
     */
    public function count(string $search = ''): int
    {
        if ($search !== '') {
            $like = '%' . $this->wpdb->esc_like($search) . '%';

            $sql = $this->wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$this->table}
                WHERE full_name LIKE %s
                   OR email LIKE %s
                   OR phone LIKE %s",
                $like,
                $like,
                $like
            );
        } else {
            $sql = "SELECT COUNT(*) FROM {$this->table}";
        }

        return (int) $this->wpdb->get_var($sql);
    }
}
