<?php

namespace BlueSheep\BusinessManager\Modules\Enquiries;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles database operations for enquiry records.
 */
final class EnquiryRepository
{
    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Enquiry table name.
     */
    private string $table;

    /**
     * Customer table name.
     */
    private string $customer_table;

    /**
     * Initialize the enquiry repository.
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'bs_enquiries';
        $this->customer_table = $wpdb->prefix . 'bs_customers';
    }

    /**
     * Retrieve enquiries with pagination, search, and optional status filtering.
     *
     * Supported status filters:
     * - closed: Only closed enquiries.
     * - not_closed: All enquiries except closed enquiries.
     *
     * @param int    $limit        Number of records to retrieve.
     * @param int    $offset       Number of records to skip.
     * @param string $search       Search term.
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
                c.full_name LIKE %s
                OR c.email LIKE %s
                OR e.enquiry_type LIKE %s
                OR e.status LIKE %s
            )';

            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status_filter === 'closed') {
            $conditions[] = 'e.status = %s';
            $params[] = 'closed';
        } elseif ($status_filter === 'not_closed') {
            $conditions[] = 'e.status <> %s';
            $params[] = 'closed';
        }

        $where = !empty($conditions)
            ? 'WHERE ' . implode(' AND ', $conditions)
            : '';

        $sql = "
            SELECT
                e.id,
                e.customer_id,
                e.enquiry_type,
                e.tour_id,
                e.preferred_start_date,
                e.preferred_end_date,
                e.dates_flexible,
                e.traveller_count,
                e.adults_count,
                e.children_count,
                e.destinations,
                e.interests,
                e.budget_range,
                e.status,
                e.created_at,
                e.updated_at,
                c.full_name AS customer_name,
                c.email AS customer_email,
                c.phone AS customer_phone
            FROM {$this->table} AS e
            INNER JOIN {$this->customer_table} AS c
                ON e.customer_id = c.id
            {$where}
            ORDER BY e.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $params[] = $limit;
        $params[] = $offset;

        $prepared_sql = $this->wpdb->prepare($sql, $params);

        $results = $this->wpdb->get_results(
            $prepared_sql,
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Retrieve an enquiry by ID.
     *
     * @param int $enquiry_id Enquiry ID.
     *
     * @return array|null
     */
    public function getById(int $enquiry_id): ?array
    {
        if ($enquiry_id <= 0) {
            return null;
        }

        $sql = $this->wpdb->prepare(
            "
            SELECT
                e.*,
                c.full_name AS customer_name,
                c.email AS customer_email,
                c.phone AS customer_phone,
                c.country AS customer_country
            FROM {$this->table} AS e
            INNER JOIN {$this->customer_table} AS c
                ON e.customer_id = c.id
            WHERE e.id = %d
            LIMIT 1
            ",
            $enquiry_id
        );

        $result = $this->wpdb->get_row(
            $sql,
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    /**
     * Count enquiries matching search and optional status filters.
     *
     * Supported status filters:
     * - closed: Only closed enquiries.
     * - not_closed: All enquiries except closed enquiries.
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
                c.full_name LIKE %s
                OR c.email LIKE %s
                OR e.enquiry_type LIKE %s
                OR e.status LIKE %s
            )';

            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status_filter === 'closed') {
            $conditions[] = 'e.status = %s';
            $params[] = 'closed';
        } elseif ($status_filter === 'not_closed') {
            $conditions[] = 'e.status <> %s';
            $params[] = 'closed';
        }

        $where = !empty($conditions)
            ? 'WHERE ' . implode(' AND ', $conditions)
            : '';

        $sql = "
            SELECT COUNT(*)
            FROM {$this->table} AS e
            INNER JOIN {$this->customer_table} AS c
                ON e.customer_id = c.id
            {$where}
        ";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return (int) $this->wpdb->get_var($sql);
    }
}
