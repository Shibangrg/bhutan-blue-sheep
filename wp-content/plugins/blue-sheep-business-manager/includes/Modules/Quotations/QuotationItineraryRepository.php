<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles database operations for quotation itinerary days.
 */
final class QuotationItineraryRepository
{
    /**
     * Get the itinerary days table name.
     */
    private function getTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bs_quotation_itinerary_days';
    }

    /**
     * Retrieve all itinerary days for a quotation version.
     *
     * @return array
     */
    public function getByVersionId(int $version_id): array
    {
        global $wpdb;

        if ($version_id <= 0) {
            return array();
        }

        $table = $this->getTableName();

        $sql = $wpdb->prepare(
            "SELECT *
            FROM `{$table}`
            WHERE quotation_version_id = %d
            ORDER BY sort_order ASC, day_number ASC, id ASC",
            $version_id
        );

        $results = $wpdb->get_results($sql, ARRAY_A);

        return is_array($results) ? $results : array();
    }

    /**
     * Retrieve an individual itinerary day.
     *
     * @return array|null
     */
    public function getById(int $day_id): ?array
    {
        global $wpdb;

        if ($day_id <= 0) {
            return null;
        }

        $table = $this->getTableName();

        $sql = $wpdb->prepare(
            "SELECT *
            FROM `{$table}`
            WHERE id = %d
            LIMIT 1",
            $day_id
        );

        $result = $wpdb->get_row($sql, ARRAY_A);

        return is_array($result) ? $result : null;
    }

    /**
     * Create a new itinerary day.
     *
     * The supplied data must contain the required database fields.
     *
     * @return int Inserted record ID.
     *
     * @throws RuntimeException
     */
    public function create(array $data): int
    {
        global $wpdb;

        $table = $this->getTableName();

        $result = $wpdb->insert(
            $table,
            $data
        );

        if ($result === false) {
            $detail = !empty($wpdb->last_error)
                ? $wpdb->last_error
                : 'Unknown database error.';

            throw new RuntimeException(
                'Unable to create the quotation itinerary day: ' . $detail
            );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update an existing itinerary day.
     *
     * @return bool False only when the database operation fails.
     *
     * @throws RuntimeException
     */
    public function update(int $day_id, array $data): bool
    {
        global $wpdb;

        if ($day_id <= 0) {
            return false;
        }

        if (empty($data)) {
            return true;
        }

        $table = $this->getTableName();

        $result = $wpdb->update(
            $table,
            $data,
            array(
                'id' => $day_id,
            ),
            null,
            array('%d')
        );

        if ($result === false) {
            $detail = !empty($wpdb->last_error)
                ? $wpdb->last_error
                : 'Unknown database error.';

            throw new RuntimeException(
                'Unable to update the quotation itinerary day: ' . $detail
            );
        }

        return true;
    }

    /**
     * Delete an itinerary day.
     *
     * @return bool False only when the database operation fails.
     *
     * @throws RuntimeException
     */
    public function delete(int $day_id): bool
    {
        global $wpdb;

        if ($day_id <= 0) {
            return false;
        }

        $table = $this->getTableName();

        $result = $wpdb->delete(
            $table,
            array(
                'id' => $day_id,
            ),
            array('%d')
        );

        if ($result === false) {
            $detail = !empty($wpdb->last_error)
                ? $wpdb->last_error
                : 'Unknown database error.';

            throw new RuntimeException(
                'Unable to delete the quotation itinerary day: ' . $detail
            );
        }

        return true;
    }

    /**
     * Count itinerary days belonging to a quotation version.
     */
    public function countByVersionId(int $version_id): int
    {
        global $wpdb;

        if ($version_id <= 0) {
            return 0;
        }

        $table = $this->getTableName();

        $sql = $wpdb->prepare(
            "SELECT COUNT(*)
            FROM `{$table}`
            WHERE quotation_version_id = %d",
            $version_id
        );

        return (int) $wpdb->get_var($sql);
    }
}
