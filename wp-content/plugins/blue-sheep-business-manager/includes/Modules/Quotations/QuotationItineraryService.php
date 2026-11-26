<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

use RuntimeException;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages itinerary days within quotation versions.
 *
 * Itinerary content is stored separately from financial quotation items.
 */
final class QuotationItineraryService
{
    private $wpdb;

    private string $quotation_table;
    private string $version_table;
    private string $itinerary_table;
    private string $activity_table;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->quotation_table = $wpdb->prefix . 'bs_quotations';
        $this->version_table = $wpdb->prefix . 'bs_quotation_versions';
        $this->itinerary_table = $wpdb->prefix . 'bs_quotation_itinerary_days';
        $this->activity_table = $wpdb->prefix . 'bs_activity_log';
    }

    /**
     * Add an itinerary day to a draft quotation version.
     *
     * @return int|WP_Error
     */
    public function addDay(int $version_id, array $input)
    {
        if (!$this->canManage()) {
            return $this->error(
                'permission_denied',
                'You do not have permission to manage quotation itineraries.'
            );
        }

        $data = $this->validateDay($input);

        if (is_wp_error($data)) {
            return $data;
        }

        return $this->runTransaction(function () use ($version_id, $data) {
            $version = $this->lockDraftVersion($version_id);

            $existing_days = $this->getDaysForValidation($version_id);

            $this->assertDateSequence(
                $existing_days,
                $data,
                null
            );

            $sort_order = isset($data['sort_order'])
                ? $data['sort_order']
                : (int) $this->wpdb->get_var(
                    $this->wpdb->prepare(
                        "SELECT COALESCE(MAX(sort_order), 0) + 1
                        FROM {$this->itinerary_table}
                        WHERE quotation_version_id = %d",
                        $version_id
                    )
                );

            if ($this->wpdb->last_error !== '') {
                throw new RuntimeException(
                    'The itinerary day order could not be determined.'
                );
            }

            $inserted = $this->wpdb->insert(
                $this->itinerary_table,
                array(
                    'quotation_version_id' => $version_id,
                    'day_number' => $data['day_number'],
                    'itinerary_date' => $data['itinerary_date'],
                    'title' => $data['title'],
                    'start_location' => $data['start_location'],
                    'end_location' => $data['end_location'],
                    'route_description' => $data['route_description'],
                    'estimated_travel_minutes' => $data['estimated_travel_minutes'],
                    'activities' => $data['activities'],
                    'overnight_location' => $data['overnight_location'],
                    'meal_arrangements' => $data['meal_arrangements'],
                    'description' => $data['description'],
                    'sort_order' => $sort_order,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                )
            );

            if ($inserted !== 1) {
                throw new RuntimeException(
                    'The itinerary day could not be created: '
                    . $this->wpdb->last_error
                );
            }

            $day_id = (int) $this->wpdb->insert_id;

            $this->logActivity(
                (int) $version['quotation_id'],
                'quotation_itinerary_day_added',
                'Itinerary day ' . $data['day_number'] . ' added.',
                $version_id
            );

            return $day_id;
        });
    }

    /**
     * Update an existing itinerary day.
     *
     * @return bool|WP_Error
     */
    public function updateDay(
        int $version_id,
        int $day_id,
        array $input
    ) {
        if (!$this->canManage()) {
            return $this->error(
                'permission_denied',
                'You do not have permission to manage quotation itineraries.'
            );
        }

        if ($day_id <= 0) {
            return $this->error(
                'invalid_itinerary_day',
                'A valid itinerary day is required.'
            );
        }

        $data = $this->validateDay($input);

        if (is_wp_error($data)) {
            return $data;
        }

        return $this->runTransaction(function () use (
            $version_id,
            $day_id,
            $data
        ) {
            $version = $this->lockDraftVersion($version_id);

            $existing_day = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT *
                    FROM {$this->itinerary_table}
                    WHERE id = %d
                    AND quotation_version_id = %d
                    FOR UPDATE",
                    $day_id,
                    $version_id
                ),
                ARRAY_A
            );

            if ($this->wpdb->last_error !== '') {
                throw new RuntimeException(
                    'The itinerary day could not be retrieved.'
                );
            }

            if (!is_array($existing_day)) {
                throw new RuntimeException(
                    'The itinerary day does not belong to this quotation version.'
                );
            }

            $existing_days = $this->getDaysForValidation(
                $version_id,
                $day_id
            );

            $this->assertDateSequence(
                $existing_days,
                $data,
                $day_id
            );

            $updated = $this->wpdb->update(
                $this->itinerary_table,
                array(
                    'day_number' => $data['day_number'],
                    'itinerary_date' => $data['itinerary_date'],
                    'title' => $data['title'],
                    'start_location' => $data['start_location'],
                    'end_location' => $data['end_location'],
                    'route_description' => $data['route_description'],
                    'estimated_travel_minutes' => $data['estimated_travel_minutes'],
                    'activities' => $data['activities'],
                    'overnight_location' => $data['overnight_location'],
                    'meal_arrangements' => $data['meal_arrangements'],
                    'description' => $data['description'],
                    'sort_order' => $data['sort_order'],
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ),
                array(
                    'id' => $day_id,
                    'quotation_version_id' => $version_id,
                )
            );

            if ($updated === false) {
                throw new RuntimeException(
                    'The itinerary day could not be updated: '
                    . $this->wpdb->last_error
                );
            }

            $this->logActivity(
                (int) $version['quotation_id'],
                'quotation_itinerary_day_updated',
                'Itinerary day ' . $data['day_number'] . ' updated.',
                $version_id
            );

            return true;
        });
    }

    /**
     * Delete an itinerary day from a draft quotation version.
     *
     * @return bool|WP_Error
     */
    public function deleteDay(int $version_id, int $day_id)
    {
        if (!$this->canManage()) {
            return $this->error(
                'permission_denied',
                'You do not have permission to manage quotation itineraries.'
            );
        }

        if ($day_id <= 0) {
            return $this->error(
                'invalid_itinerary_day',
                'A valid itinerary day is required.'
            );
        }

        return $this->runTransaction(function () use (
            $version_id,
            $day_id
        ) {
            $version = $this->lockDraftVersion($version_id);

            $day = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT id, day_number
                    FROM {$this->itinerary_table}
                    WHERE id = %d
                    AND quotation_version_id = %d
                    FOR UPDATE",
                    $day_id,
                    $version_id
                ),
                ARRAY_A
            );

            if ($this->wpdb->last_error !== '') {
                throw new RuntimeException(
                    'The itinerary day could not be retrieved.'
                );
            }

            if (!is_array($day)) {
                throw new RuntimeException(
                    'The itinerary day does not belong to this quotation version.'
                );
            }

            $deleted = $this->wpdb->delete(
                $this->itinerary_table,
                array(
                    'id' => $day_id,
                    'quotation_version_id' => $version_id,
                ),
                array('%d', '%d')
            );

            if ($deleted !== 1) {
                throw new RuntimeException(
                    'The itinerary day could not be deleted.'
                );
            }

            $this->logActivity(
                (int) $version['quotation_id'],
                'quotation_itinerary_day_deleted',
                'Itinerary day ' . $day['day_number'] . ' deleted.',
                $version_id
            );

            return true;
        });
    }

    /**
     * Retrieve all itinerary days for a quotation version.
     *
     * @return array|WP_Error
     */
    public function getDays(int $version_id)
    {
        if ($version_id <= 0) {
            return $this->error(
                'invalid_version',
                'A valid quotation version is required.'
            );
        }

        $days = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT *
                FROM {$this->itinerary_table}
                WHERE quotation_version_id = %d
                ORDER BY sort_order ASC, day_number ASC, id ASC",
                $version_id
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            return $this->error(
                'itinerary_retrieval_failed',
                'The quotation itinerary could not be retrieved.'
            );
        }

        return is_array($days) ? $days : array();
    }

    /**
     * Retrieve one itinerary day.
     *
     * @return array|null|WP_Error
     */
    public function getDay(int $version_id, int $day_id)
    {
        if ($version_id <= 0 || $day_id <= 0) {
            return $this->error(
                'invalid_itinerary_day',
                'A valid quotation version and itinerary day are required.'
            );
        }

        $day = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT *
                FROM {$this->itinerary_table}
                WHERE id = %d
                AND quotation_version_id = %d
                LIMIT 1",
                $day_id,
                $version_id
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            return $this->error(
                'itinerary_day_retrieval_failed',
                'The itinerary day could not be retrieved.'
            );
        }

        return is_array($day) ? $day : null;
    }

    /**
     * Validate submitted itinerary day data.
     *
     * @return array|WP_Error
     */
    private function validateDay(array $input)
    {
        $day_number = isset($input['day_number'])
            ? filter_var($input['day_number'], FILTER_VALIDATE_INT)
            : false;

        if ($day_number === false || $day_number <= 0) {
            return $this->error(
                'invalid_day_number',
                'The itinerary day number must be a positive integer.'
            );
        }

        $title = isset($input['title'])
            ? sanitize_text_field($input['title'])
            : '';

        if ($title === '' || mb_strlen($title, 'UTF-8') > 190) {
            return $this->error(
                'invalid_itinerary_title',
                'Enter an itinerary title of no more than 190 characters.'
            );
        }

        $itinerary_date = isset($input['itinerary_date'])
            ? trim((string) $input['itinerary_date'])
            : '';

        if ($itinerary_date !== '') {
            $date = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $itinerary_date
            );

            if (
                !$date
                || $date->format('Y-m-d') !== $itinerary_date
            ) {
                return $this->error(
                    'invalid_itinerary_date',
                    'Enter a valid itinerary date in YYYY-MM-DD format.'
                );
            }
        } else {
            $itinerary_date = null;
        }

        $estimated_travel_minutes = null;

        if (
            isset($input['estimated_travel_minutes'])
            && $input['estimated_travel_minutes'] !== ''
        ) {
            $estimated_travel_minutes = filter_var(
                $input['estimated_travel_minutes'],
                FILTER_VALIDATE_INT
            );

            if (
                $estimated_travel_minutes === false
                || $estimated_travel_minutes < 0
            ) {
                return $this->error(
                    'invalid_travel_duration',
                    'Estimated travel time must be a non-negative whole number of minutes.'
                );
            }
        }

        $sort_order = isset($input['sort_order'])
            ? filter_var($input['sort_order'], FILTER_VALIDATE_INT)
            : 0;

        if ($sort_order === false || $sort_order < 0) {
            return $this->error(
                'invalid_sort_order',
                'The itinerary order must be a non-negative integer.'
            );
        }

        $activities = $this->normalizeActivities(
            $input['activities'] ?? array()
        );

        if (is_wp_error($activities)) {
            return $activities;
        }

        return array(
            'day_number' => $day_number,
            'itinerary_date' => $itinerary_date,
            'title' => $title,
            'start_location' => $this->sanitizeOptionalText(
                $input['start_location'] ?? '',
                190
            ),
            'end_location' => $this->sanitizeOptionalText(
                $input['end_location'] ?? '',
                190
            ),
            'route_description' => sanitize_textarea_field(
                $input['route_description'] ?? ''
            ),
            'estimated_travel_minutes' => $estimated_travel_minutes,
            'activities' => $activities,
            'overnight_location' => $this->sanitizeOptionalText(
                $input['overnight_location'] ?? '',
                190
            ),
            'meal_arrangements' => sanitize_textarea_field(
                $input['meal_arrangements'] ?? ''
            ),
            'description' => sanitize_textarea_field(
                $input['description'] ?? ''
            ),
            'sort_order' => $sort_order,
        );
    }

    /**
     * Normalize activities into a JSON array of strings.
     *
     * Accepts either an array or a newline-separated textarea value.
     *
     * @return string|WP_Error
     */
    private function normalizeActivities($activities)
    {
        if (is_string($activities)) {
            $activities = trim($activities);

            if ($activities === '') {
                return wp_json_encode(array());
            }

            $decoded = json_decode($activities, true);

            if (is_array($decoded)) {
                $activities = $decoded;
            } else {
                $activities = preg_split('/\r\n|\r|\n/', $activities);
            }
        }

        if (!is_array($activities)) {
            return $this->error(
                'invalid_activities',
                'Activities must be provided as a list of text entries.'
            );
        }

        $normalized = array();

        foreach ($activities as $activity) {
            if (!is_scalar($activity)) {
                return $this->error(
                    'invalid_activity_entry',
                    'Each activity must be a text value.'
                );
            }

            $activity = sanitize_text_field((string) $activity);

            if ($activity !== '') {
                $normalized[] = $activity;
            }
        }

        $json = wp_json_encode(array_values($normalized));

        if ($json === false) {
            return $this->error(
                'activities_encoding_failed',
                'The itinerary activities could not be processed.'
            );
        }

        return $json;
    }

    /**
     * Sanitize an optional text field with a maximum length.
     */
    private function sanitizeOptionalText($value, int $max_length): ?string
    {
        $value = sanitize_text_field((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max_length, 'UTF-8');
    }

    /**
     * Retrieve existing days for date-sequence validation.
     *
     * The excluded day is omitted when updating an existing record.
     */
    private function getDaysForValidation(
        int $version_id,
        ?int $exclude_day_id = null
    ): array {
        $sql = $this->wpdb->prepare(
            "SELECT id, day_number, itinerary_date
            FROM {$this->itinerary_table}
            WHERE quotation_version_id = %d",
            $version_id
        );

        if ($exclude_day_id !== null) {
            $sql .= $this->wpdb->prepare(
                ' AND id != %d',
                $exclude_day_id
            );
        }

        $sql .= ' ORDER BY day_number ASC';

        $days = $this->wpdb->get_results($sql, ARRAY_A);

        if ($this->wpdb->last_error !== '') {
            throw new RuntimeException(
                'Existing itinerary dates could not be validated.'
            );
        }

        return is_array($days) ? $days : array();
    }

    /**
     * Ensure assigned dates follow itinerary day order.
     *
     * Unassigned dates are permitted for flexible travel plans.
     */
    private function assertDateSequence(
        array $existing_days,
        array $candidate,
        ?int $exclude_day_id
    ): void {
        $days = $existing_days;

        $days[] = array(
            'id' => $exclude_day_id,
            'day_number' => $candidate['day_number'],
            'itinerary_date' => $candidate['itinerary_date'],
        );

        usort($days, function ($a, $b) {
            return (int) $a['day_number'] <=> (int) $b['day_number'];
        });

        $previous_date = null;
        $used_day_numbers = array();

        foreach ($days as $day) {
            $number = (int) $day['day_number'];

            if (isset($used_day_numbers[$number])) {
                throw new RuntimeException(
                    'Each itinerary day must have a unique day number.'
                );
            }

            $used_day_numbers[$number] = true;

            $date = $day['itinerary_date'] ?? null;

            if ($date === null || $date === '') {
                continue;
            }

            if (
                $previous_date !== null
                && $date <= $previous_date
            ) {
                throw new RuntimeException(
                    'Itinerary dates must follow chronological day order.'
                );
            }

            $previous_date = $date;
        }
    }

    /**
     * Lock and validate the current draft quotation version.
     */
    private function lockDraftVersion(int $version_id): array
    {
        if ($version_id <= 0) {
            throw new RuntimeException(
                'A valid quotation version is required.'
            );
        }

        $version = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT
                    v.id,
                    v.quotation_id,
                    v.status,
                    q.current_version_id
                FROM {$this->version_table} AS v
                INNER JOIN {$this->quotation_table} AS q
                    ON q.id = v.quotation_id
                WHERE v.id = %d
                FOR UPDATE",
                $version_id
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            throw new RuntimeException(
                'The quotation version could not be retrieved.'
            );
        }

        if (!is_array($version)) {
            throw new RuntimeException(
                'The quotation version could not be found.'
            );
        }

        if ((int) $version['current_version_id'] !== $version_id) {
            throw new RuntimeException(
                'Only the current quotation version can be edited.'
            );
        }

        if ($version['status'] !== 'draft') {
            throw new RuntimeException(
                'Only draft quotation versions can be edited.'
            );
        }

        return $version;
    }

    /**
     * Record an itinerary activity against its quotation.
     */
    private function logActivity(
        int $quotation_id,
        string $action,
        string $description,
        int $version_id
    ): void {
        $result = $this->wpdb->insert(
            $this->activity_table,
            array(
                'user_id' => get_current_user_id(),
                'entity_type' => 'quotation',
                'entity_id' => $quotation_id,
                'action' => $action,
                'description' => $description
                    . ' Version ID: ' . $version_id,
                'created_at' => gmdate('Y-m-d H:i:s'),
            )
        );

        if ($result !== 1) {
            throw new RuntimeException(
                'The itinerary activity could not be recorded: '
                . $this->wpdb->last_error
            );
        }
    }

    /**
     * Check whether the current administrator can manage quotations.
     */
    private function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Execute a database operation inside a transaction.
     *
     * @return mixed|WP_Error
     */
    private function runTransaction(callable $callback)
    {
        $started = $this->wpdb->query('START TRANSACTION');

        if ($started === false) {
            return $this->error(
                'transaction_start_failed',
                'The itinerary operation could not be started.'
            );
        }

        try {
            $result = $callback();

            $committed = $this->wpdb->query('COMMIT');

            if ($committed === false) {
                throw new RuntimeException(
                    'The itinerary transaction could not be committed.'
                );
            }

            return $result;
        } catch (Throwable $exception) {
            $this->wpdb->query('ROLLBACK');

            return $this->error(
                'itinerary_operation_failed',
                $exception->getMessage()
            );
        }
    }

    /**
     * Create a WordPress error.
     */
    private function error(string $code, string $message): WP_Error
    {
        return new \WP_Error($code, $message);
    }
}
