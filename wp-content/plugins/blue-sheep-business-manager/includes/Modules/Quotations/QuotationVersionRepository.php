<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles database queries for quotation versions.
 */
final class QuotationVersionRepository
{
    /**
     * WordPress database instance.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Quotation version table name.
     *
     * @var string
     */
    private $version_table;

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

        $this->version_table = $wpdb->prefix . 'bs_quotation_versions';
        $this->item_table = $wpdb->prefix . 'bs_quotation_items';
    }

    /**
     * Retrieve all versions belonging to a quotation.
     *
     * @param int $quotation_id Quotation ID.
     *
     * @return array
     */
    public function getByQuotationId(int $quotation_id): array
    {
        if ($quotation_id <= 0) {
            return [];
        }

        $sql = "
            SELECT
                v.id,
                v.quotation_id,
                v.version_number,
                v.currency,
                v.base_currency,
                v.exchange_rate,
                v.exchange_rate_source,
                v.exchange_rate_date,
                v.subtotal,
                v.discount,
                v.total_amount,
                v.deposit_type,
                v.deposit_value,
                v.balance_due_date,
                v.cancellation_terms,
                v.terms_and_conditions,
                v.valid_until,
                v.accepted_at,
                v.acceptance_method,
                v.acceptance_reference,
                v.created_at,
                v.status,
                COUNT(i.id) AS item_count
            FROM {$this->version_table} AS v
            LEFT JOIN {$this->item_table} AS i
                ON i.quotation_version_id = v.id
            WHERE v.quotation_id = %d
            GROUP BY v.id
            ORDER BY v.version_number DESC, v.id DESC
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $quotation_id),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Retrieve a specific quotation version.
     *
     * @param int $version_id Quotation version ID.
     *
     * @return array|null
     */
    public function getById(int $version_id): ?array
    {
        if ($version_id <= 0) {
            return null;
        }

        $sql = "
            SELECT
                v.*,
                q.reference_number,
                q.customer_id,
                q.enquiry_id,
                q.current_version_id
            FROM {$this->version_table} AS v
            INNER JOIN {$this->wpdb->prefix}bs_quotations AS q
                ON q.id = v.quotation_id
            WHERE v.id = %d
            LIMIT 1
        ";

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $version_id),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    /**
     * Retrieve the latest version number for a quotation.
     *
     * Returns zero when the quotation has no versions.
     *
     * @param int $quotation_id Quotation ID.
     *
     * @return int
     */
    public function getLatestVersionNumber(int $quotation_id): int
    {
        if ($quotation_id <= 0) {
            return 0;
        }

        $version_number = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT MAX(version_number)
                 FROM {$this->version_table}
                 WHERE quotation_id = %d",
                $quotation_id
            )
        );

        return (int) $version_number;
    }

    /**
     * Retrieve a version with its associated quotation items.
     *
     * @param int $version_id Quotation version ID.
     *
     * @return array|null
     */
    public function getVersionWithItems(int $version_id): ?array
    {
        $version = $this->getById($version_id);

        if ($version === null) {
            return null;
        }

        $items = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    id,
                    quotation_version_id,
                    item_type,
                    day_number,
                    title,
                    description,
                    quantity,
                    unit_amount,
                    total_amount,
                    sort_order
                 FROM {$this->item_table}
                 WHERE quotation_version_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $version_id
            ),
            ARRAY_A
        );

        $version['items'] = is_array($items) ? $items : [];

        return $version;
    }
}
