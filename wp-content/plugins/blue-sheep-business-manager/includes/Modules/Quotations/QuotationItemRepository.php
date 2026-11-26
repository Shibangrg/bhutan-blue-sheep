<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles database queries for quotation items.
 */
final class QuotationItemRepository
{
    /**
     * WordPress database instance.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Quotation item table name.
     *
     * @var string
     */
    private $item_table;

    /**
     * Initialize the repository.
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->item_table = $wpdb->prefix . 'bs_quotation_items';
    }

    /**
     * Retrieve all items belonging to a quotation version.
     *
     * @param int $version_id Quotation version ID.
     *
     * @return array
     */
    public function getByVersionId(int $version_id): array
    {
        if ($version_id <= 0) {
            return [];
        }

        $sql = "
            SELECT *
            FROM {$this->item_table}
            WHERE quotation_version_id = %d
            ORDER BY sort_order ASC, id ASC
        ";

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $version_id),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Retrieve an individual quotation item.
     *
     * @param int $item_id Quotation item ID.
     *
     * @return array|null
     */
    public function getById(int $item_id): ?array
    {
        if ($item_id <= 0) {
            return null;
        }

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT *
                 FROM {$this->item_table}
                 WHERE id = %d
                 LIMIT 1",
                $item_id
            ),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    /**
     * Count items belonging to a quotation version.
     *
     * @param int $version_id Quotation version ID.
     *
     * @return int
     */
    public function countByVersionId(int $version_id): int
    {
        if ($version_id <= 0) {
            return 0;
        }

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$this->item_table}
                 WHERE quotation_version_id = %d",
                $version_id
            )
        );
    }
}
