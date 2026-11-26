<?php

namespace BlueSheep\BusinessManager\Modules\Enquiries;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validates and processes enquiry status transitions.
 */
final class EnquiryStatusService
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
    private string $enquiry_table;

    /**
     * Activity log table name.
     */
    private string $activity_table;

    /**
     * Permitted enquiry status transitions.
     */
    private const TRANSITIONS = [
        'new' => [
            'under_review',
            'declined',
            'closed',
        ],
        'under_review' => [
            'awaiting_customer_information',
            'in_quotation',
            'declined',
            'closed',
        ],
        'awaiting_customer_information' => [
            'under_review',
            'declined',
            'closed',
        ],
        'in_quotation' => [
            'quoted',
            'awaiting_customer_information',
            'declined',
            'closed',
        ],
        'quoted' => [
            'in_quotation',
            'converted',
            'declined',
            'closed',
        ],
        'converted' => [],
        'declined' => [],
        'closed' => [],
    ];

    /**
     * Initialize the status service.
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->enquiry_table = $wpdb->prefix . 'bs_enquiries';
        $this->activity_table = $wpdb->prefix . 'bs_activity_log';
    }

    /**
     * Update an enquiry status and record the activity.
     *
     * @param int    $enquiry_id Enquiry ID.
     * @param string $new_status Requested status.
     * @param string $reason     Reason for the transition.
     *
     * @return true|\WP_Error
     */
    public function updateStatus(
        int $enquiry_id,
        string $new_status,
        string $reason = ''
    ) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'permission_denied',
                __('You do not have permission to update enquiry statuses.', 'blue-sheep-business-manager')
            );
        }

        if ($enquiry_id <= 0) {
            return new \WP_Error(
                'invalid_enquiry',
                __('Invalid enquiry ID.', 'blue-sheep-business-manager')
            );
        }

        if (!array_key_exists($new_status, self::TRANSITIONS)) {
            return new \WP_Error(
                'invalid_status',
                __('The requested enquiry status is invalid.', 'blue-sheep-business-manager')
            );
        }

        $reason = sanitize_textarea_field($reason);

        // These transitions require completed quotation or booking workflows.
        if (in_array($new_status, ['quoted', 'converted'], true)) {
            return new \WP_Error(
                'workflow_incomplete',
                __('This status requires the quotation or booking workflow to be completed first.', 'blue-sheep-business-manager')
            );
        }

        $user_id = get_current_user_id();
        $timestamp = current_time('mysql', true);

        $this->wpdb->query('START TRANSACTION');

        try {
            // Lock the enquiry row to prevent concurrent status changes.
            $enquiry = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT id, status
                    FROM {$this->enquiry_table}
                    WHERE id = %d
                    FOR UPDATE",
                    $enquiry_id
                ),
                ARRAY_A
            );

            if (!is_array($enquiry)) {
                $this->wpdb->query('ROLLBACK');

                return new \WP_Error(
                    'enquiry_not_found',
                    __('The requested enquiry could not be found.', 'blue-sheep-business-manager')
                );
            }

            $previous_status = $enquiry['status'];

            if (!isset(self::TRANSITIONS[$previous_status])) {
                $this->wpdb->query('ROLLBACK');

                return new \WP_Error(
                    'unknown_current_status',
                    __('The current enquiry status is not recognized.', 'blue-sheep-business-manager')
                );
            }

            if (!in_array(
                $new_status,
                self::TRANSITIONS[$previous_status],
                true
            )) {
                $this->wpdb->query('ROLLBACK');

                return new \WP_Error(
                    'transition_not_allowed',
                    __('This enquiry status transition is not permitted.', 'blue-sheep-business-manager')
                );
            }

            $updated = $this->wpdb->update(
                $this->enquiry_table,
                [
                    'status' => $new_status,
                    'updated_at' => $timestamp,
                ],
                [
                    'id' => $enquiry_id,
                    'status' => $previous_status,
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                    '%s',
                ]
            );

            if ($updated !== 1) {
                throw new \RuntimeException(
                    'The enquiry status could not be updated.'
                );
            }

            $display_previous = ucwords(
                str_replace('_', ' ', $previous_status)
            );

            $display_new = ucwords(
                str_replace('_', ' ', $new_status)
            );

            $description = sprintf(
                'Enquiry status changed from %s to %s.',
                $display_previous,
                $display_new
            );

            $logged = $this->wpdb->insert(
                $this->activity_table,
                [
                    'user_id' => $user_id,
                    'entity_type' => 'enquiry',
                    'entity_id' => $enquiry_id,
                    'action' => 'status_changed',
                    'description' => $description,
                    'previous_status' => $previous_status,
                    'new_status' => $new_status,
                    'reason' => $reason !== '' ? $reason : null,
                    'created_at' => $timestamp,
                ],
                [
                    '%d',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($logged !== 1) {
                throw new \RuntimeException(
                    'The enquiry activity could not be recorded.'
                );
            }

            $this->wpdb->query('COMMIT');

            return true;
        } catch (\Throwable $exception) {
            $this->wpdb->query('ROLLBACK');

            error_log(
                'Blue Sheep Business Manager: Enquiry status update failed. '
                . $exception->getMessage()
            );

            return new \WP_Error(
                'status_update_failed',
                __('The enquiry status could not be updated. No changes were saved.', 'blue-sheep-business-manager')
            );
        }
    }

    /**
     * Retrieve permitted next statuses for an enquiry.
     *
     * @param string $current_status Current enquiry status.
     *
     * @return array
     */
    public function getAvailableTransitions(string $current_status): array
    {
        $transitions = self::TRANSITIONS[$current_status] ?? [];

        // These statuses require other business modules.
        return array_values(
            array_diff($transitions, ['quoted', 'converted'])
        );
    }
}
