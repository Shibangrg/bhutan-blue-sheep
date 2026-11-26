<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles database queries for quotation records.
 */
final class QuotationRepository
{
    /**
     * WordPress database instance.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Quotation table name.
     *
     * @var string
     */
    private $quotation_table;

    /**
     * Customer table name.
     *
     * @var string
     */
    private $customer_table;

    /**
     * Enquiry table name.
     *
     * @var string
     */
    private $enquiry_table;

    /**
     * Quotation version table name.
     *
     * @var string
     */
    private $version_table;

    /**
     * Initialize the repository.
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->quotation_table = $wpdb->prefix . 'bs_quotations';
        $this->customer_table = $wpdb->prefix . 'bs_customers';
        $this->enquiry_table = $wpdb->prefix . 'bs_enquiries';
        $this->version_table = $wpdb->prefix . 'bs_quotation_versions';
    }

    /**
     * Retrieve quotations with customer and current-version details.
     *
     * Supported status filters:
     * - sent: Only sent quotations.
     * - not_sent: Quotations that have not been sent.
     * - empty: All quotations.
     *
     * @param int    $limit         Maximum number of records.
     * @param int    $offset        Starting record offset.
     * @param string $search        Search term.
     * @param string $status_filter Optional status filter.
     *
     * @return array
     */
    public function getAll(
        int $limit = 20,
        int $offset = 0,
        string $search = '',
        string $status_filter = ''
    ): array {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $conditions = [];
        $params = [];

        if ($search !== '') {
            $like = '%' . $this->wpdb->esc_like($search) . '%';

            $conditions[] = '(
                q.reference_number LIKE %s
                OR c.full_name LIKE %s
                OR c.email LIKE %s
                OR q.status LIKE %s
            )';

            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status_filter === 'sent') {
            $conditions[] = 'q.status = %s';
            $params[] = 'sent';
        } elseif ($status_filter === 'not_sent') {
            $conditions[] = 'q.status <> %s';
            $params[] = 'sent';
        }

        $where = !empty($conditions)
            ? 'WHERE ' . implode(' AND ', $conditions)
            : '';

        $sql = "
            SELECT
                q.id,
                q.enquiry_id,
                q.customer_id,
                q.reference_number,
                q.current_version_id,
                q.status,
                q.created_by,
                q.created_at,
                q.updated_at,
                c.full_name AS customer_name,
                c.email AS customer_email,
                v.version_number AS current_version_number,
                v.currency,
                v.subtotal,
                v.discount,
                v.total_amount,
                v.valid_until
            FROM {$this->quotation_table} AS q
            INNER JOIN {$this->customer_table} AS c
                ON c.id = q.customer_id
            LEFT JOIN {$this->version_table} AS v
                ON v.id = q.current_version_id
                AND v.quotation_id = q.id
            {$where}
            ORDER BY q.created_at DESC, q.id DESC
            LIMIT %d OFFSET %d
        ";

        $params[] = $limit;
        $params[] = $offset;

        $prepared_sql = $this->wpdb->prepare($sql, $params);

        return $this->wpdb->get_results(
            $prepared_sql,
            ARRAY_A
        ) ?: [];
    }

    /**
     * Retrieve a quotation by its ID.
     *
     * @param int $quotation_id Quotation ID.
     *
     * @return array|null
     */
    public function getById(int $quotation_id): ?array
    {
        if ($quotation_id <= 0) {
            return null;
        }

        $sql = "
            SELECT
                q.*,
                c.full_name AS customer_name,
                c.email AS customer_email,
                c.phone AS customer_phone,
                c.country AS customer_country,
                e.status AS enquiry_status,
                v.version_number AS current_version_number,
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
                v.status AS version_status
            FROM {$this->quotation_table} AS q
            INNER JOIN {$this->customer_table} AS c
                ON c.id = q.customer_id
            INNER JOIN {$this->enquiry_table} AS e
                ON e.id = q.enquiry_id
                AND e.customer_id = q.customer_id
            LEFT JOIN {$this->version_table} AS v
                ON v.id = q.current_version_id
                AND v.quotation_id = q.id
            WHERE q.id = %d
            LIMIT 1
        ";

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $quotation_id),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    /**
     * Retrieve quotations associated with an enquiry.
     *
     * @param int $enquiry_id Enquiry ID.
     *
     * @return array
     */
    public function getByEnquiryId(int $enquiry_id): array
    {
        if ($enquiry_id <= 0) {
            return [];
        }

        $sql = "
            SELECT
                q.id,
                q.enquiry_id,
                q.customer_id,
                q.reference_number,
                q.current_version_id,
                q.status,
                q.created_at,
                q.updated_at,
                v.version_number AS current_version_number,
                v.currency,
                v.total_amount,
                v.valid_until
            FROM {$this->quotation_table} AS q
            LEFT JOIN {$this->version_table} AS v
                ON v.id = q.current_version_id
                AND v.quotation_id = q.id
            WHERE q.enquiry_id = %d
            ORDER BY q.created_at DESC, q.id DESC
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $enquiry_id),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Count quotations matching search and optional status filters.
     *
     * Supported status filters:
     * - sent: Only sent quotations.
     * - not_sent: Quotations that have not been sent.
     * - empty: All quotations.
     *
     * @param string $search        Search term.
     * @param string $status_filter Optional status filter.
     *
     * @return int
     */
    public function count(
        string $search = '',
        string $status_filter = ''
    ): int {
        $conditions = [];
        $params = [];

        if ($search !== '') {
            $like = '%' . $this->wpdb->esc_like($search) . '%';

            $conditions[] = '(
                q.reference_number LIKE %s
                OR c.full_name LIKE %s
                OR c.email LIKE %s
                OR q.status LIKE %s
            )';

            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status_filter === 'sent') {
            $conditions[] = 'q.status = %s';
            $params[] = 'sent';
        } elseif ($status_filter === 'not_sent') {
            $conditions[] = 'q.status <> %s';
            $params[] = 'sent';
        }

        $where = !empty($conditions)
            ? 'WHERE ' . implode(' AND ', $conditions)
            : '';

        $sql = "
            SELECT COUNT(*)
            FROM {$this->quotation_table} AS q
            INNER JOIN {$this->customer_table} AS c
                ON c.id = q.customer_id
            {$where}
        ";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return (int) $this->wpdb->get_var($sql);
    }
}
