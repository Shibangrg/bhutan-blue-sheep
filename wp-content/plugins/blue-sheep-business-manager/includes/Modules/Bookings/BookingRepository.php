<?php

namespace BlueSheep\BusinessManager\Modules\Bookings;

defined('ABSPATH') || exit;

class BookingRepository
{
    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Booking table name.
     *
     * @var string
     */
    private $bookings_table;

    /**
     * Customer table name.
     *
     * @var string
     */
    private $customers_table;

    /**
     * Quotation table name.
     *
     * @var string
     */
    private $quotations_table;

    /**
     * Quotation versions table name.
     *
     * @var string
     */
    private $quotation_versions_table;

    /**
     * Enquiries table name.
     *
     * @var string
     */
    private $enquiries_table;

    /**
     * Initialize repository.
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->bookings_table = $wpdb->prefix . 'bs_bookings';
        $this->customers_table = $wpdb->prefix . 'bs_customers';
        $this->quotations_table = $wpdb->prefix . 'bs_quotations';
        $this->quotation_versions_table = $wpdb->prefix . 'bs_quotation_versions';
        $this->enquiries_table = $wpdb->prefix . 'bs_enquiries';
    }

    /**
     * Retrieve a booking by its ID.
     *
     * @param int $booking_id Booking ID.
     * @return array|null
     */
    public function getById(int $booking_id): ?array
    {
        if ($booking_id <= 0) {
            return null;
        }

        $sql = "
            SELECT
                b.*,
                c.full_name AS customer_name,
                c.email AS customer_email,
                c.phone AS customer_phone,
                q.reference_number AS quotation_reference,
                q.enquiry_id,
                qv.version_number AS accepted_version_number,
                qv.total_amount AS quotation_total,
                qv.currency AS quotation_currency
            FROM {$this->bookings_table} b
            INNER JOIN {$this->customers_table} c
                ON c.id = b.customer_id
            INNER JOIN {$this->quotations_table} q
                ON q.id = b.quotation_id
            INNER JOIN {$this->quotation_versions_table} qv
                ON qv.id = b.accepted_version_id
            WHERE b.id = %d
            LIMIT 1
        ";

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $booking_id),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    /**
     * Retrieve a booking associated with a quotation.
     *
     * @param int $quotation_id Quotation ID.
     * @return array|null
     */
    public function getByQuotationId(int $quotation_id): ?array
    {
        if ($quotation_id <= 0) {
            return null;
        }

        $sql = "
            SELECT *
            FROM {$this->bookings_table}
            WHERE quotation_id = %d
            LIMIT 1
        ";

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $quotation_id),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    /**
     * Retrieve bookings with optional search and status filtering.
     *
     * @param int    $limit         Number of records.
     * @param int    $offset        Query offset.
     * @param string $search        Search term.
     * @param string $status_filter Booking status.
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

        $where = [];
        $params = [];

        if ($status_filter !== '') {
            $where[] = 'b.status = %s';
            $params[] = $status_filter;
        }

        if ($search !== '') {
            $like = '%' . $this->wpdb->esc_like($search) . '%';

            $where[] = '(
                b.booking_reference LIKE %s
                OR c.full_name LIKE %s
                OR c.email LIKE %s
                OR q.reference_number LIKE %s
            )';

            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $sql = "
            SELECT
                b.*,
                c.full_name AS customer_name,
                c.email AS customer_email,
                q.reference_number AS quotation_reference
            FROM {$this->bookings_table} b
            INNER JOIN {$this->customers_table} c
                ON c.id = b.customer_id
            INNER JOIN {$this->quotations_table} q
                ON q.id = b.quotation_id
            {$where_sql}
            ORDER BY b.created_at DESC, b.id DESC
            LIMIT %d OFFSET %d
        ";

        $params[] = $limit;
        $params[] = $offset;

        $prepared_sql = $this->wpdb->prepare($sql, $params);
        $results = $this->wpdb->get_results($prepared_sql, ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Count bookings with optional search and status filtering.
     *
     * @param string $search        Search term.
     * @param string $status_filter Booking status.
     * @return int
     */
    public function count(
        string $search = '',
        string $status_filter = ''
    ): int {
        $where = [];
        $params = [];

        if ($status_filter !== '') {
            $where[] = 'b.status = %s';
            $params[] = $status_filter;
        }

        if ($search !== '') {
            $like = '%' . $this->wpdb->esc_like($search) . '%';

            $where[] = '(
                b.booking_reference LIKE %s
                OR c.full_name LIKE %s
                OR c.email LIKE %s
                OR q.reference_number LIKE %s
            )';

            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $sql = "
            SELECT COUNT(*)
            FROM {$this->bookings_table} b
            INNER JOIN {$this->customers_table} c
                ON c.id = b.customer_id
            INNER JOIN {$this->quotations_table} q
                ON q.id = b.quotation_id
            {$where_sql}
        ";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Insert a booking record.
     *
     * The caller is responsible for validating the data and
     * managing the transaction.
     *
     * @param array $data Booking data.
     * @return int|false Inserted booking ID or false.
     */
    public function create(array $data)
    {
        $required_fields = [
            'customer_id',
            'quotation_id',
            'accepted_version_id',
            'booking_reference',
            'travel_start_date',
            'travel_end_date',
            'traveller_count',
            'status',
        ];

        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return false;
            }
        }

        $inserted = $this->wpdb->insert(
            $this->bookings_table,
            [
                'customer_id' => (int) $data['customer_id'],
                'quotation_id' => (int) $data['quotation_id'],
                'accepted_version_id' => (int) $data['accepted_version_id'],
                'booking_reference' => sanitize_text_field($data['booking_reference']),
                'travel_start_date' => sanitize_text_field($data['travel_start_date']),
                'travel_end_date' => sanitize_text_field($data['travel_end_date']),
                'traveller_count' => (int) $data['traveller_count'],
                'status' => sanitize_key($data['status']),
                'confirmed_at' => $data['confirmed_at'] ?? null,
                'confirmation_exception_by' => isset($data['confirmation_exception_by'])
                    ? (int) $data['confirmation_exception_by']
                    : null,
                'confirmation_exception_reason' => isset($data['confirmation_exception_reason'])
                    ? sanitize_textarea_field($data['confirmation_exception_reason'])
                    : null,
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ],
            [
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($inserted === false) {
            return false;
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Update a booking status.
     *
     * @param int    $booking_id Booking ID.
     * @param string $status     New status.
     * @return bool
     */
    public function updateStatus(int $booking_id, string $status): bool
    {
        if ($booking_id <= 0 || $status === '') {
            return false;
        }

        $updated = $this->wpdb->update(
            $this->bookings_table,
            [
                'status' => sanitize_key($status),
                'updated_at' => current_time('mysql', true),
            ],
            [
                'id' => $booking_id,
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
            ]
        );

        return $updated !== false;
    }
}
