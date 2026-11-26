<?php

namespace BlueSheep\BusinessManager\Modules\Enquiries;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieves activity history for customer enquiries.
 */
final class EnquiryActivityRepository
{
    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Activity log table name.
     */
    private string $table;

    /**
     * Initialize the activity repository.
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'bs_activity_log';
    }

    /**
     * Retrieve activity history for an enquiry.
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

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    id,
                    user_id,
                    entity_type,
                    entity_id,
                    action,
                    description,
                    previous_status,
                    new_status,
                    reason,
                    created_at
                FROM {$this->table}
                WHERE entity_type = %s
                  AND entity_id = %d
                ORDER BY created_at DESC, id DESC",
                'enquiry',
                $enquiry_id
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }
}
