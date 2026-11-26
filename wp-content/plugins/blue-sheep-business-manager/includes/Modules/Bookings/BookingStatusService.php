<?php

namespace BlueSheep\BusinessManager\Modules\Bookings;

defined('ABSPATH') || exit;

/**
 * Manages booking status transitions and activity logging.
 */
class BookingStatusService
{
    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Database table names.
     */
    private $bookings_table;
    private $activity_log_table;

    /**
     * Permitted booking status transitions.
     */
    private const ALLOWED_TRANSITIONS = [
        'pending_confirmation' => [
            'confirmed',
            'cancelled',
        ],
        'confirmed' => [
            'in_progress',
            'cancelled',
        ],
        'in_progress' => [
            'completed',
        ],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * Initialize the service.
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->bookings_table = $wpdb->prefix . 'bs_bookings';
        $this->activity_log_table = $wpdb->prefix . 'bs_activity_log';
    }

    /**
     * Change the status of a booking.
     *
     * @param int    $booking_id Booking ID.
     * @param string $new_status Requested status.
     * @param string $reason     Administrative reason.
     *
     * @return bool|\WP_Error
     */
    public function updateStatus(
        int $booking_id,
        string $new_status,
        string $reason = ''
    ) {
        if ($booking_id <= 0) {
            return new \WP_Error(
                'invalid_booking_id',
                'A valid booking ID is required.'
            );
        }

        $new_status = sanitize_key($new_status);
        $reason = sanitize_textarea_field($reason);

        if (!array_key_exists($new_status, [
            'pending_confirmation' => true,
            'confirmed' => true,
            'in_progress' => true,
            'completed' => true,
            'cancelled' => true,
        ])) {
            return new \WP_Error(
                'invalid_booking_status',
                'The requested booking status is invalid.'
            );
        }

        if ($new_status === 'cancelled' && $reason === '') {
            return new \WP_Error(
                'cancellation_reason_required',
                'A reason is required when cancelling a booking.'
            );
        }

        if ($this->wpdb->query('START TRANSACTION') === false) {
            return new \WP_Error(
                'transaction_start_failed',
                'Unable to start the booking status transaction.'
            );
        }

        try {
            /*
             * Lock the booking row to prevent concurrent updates.
             */
            $booking = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "
                    SELECT id, status
                    FROM {$this->bookings_table}
                    WHERE id = %d
                    FOR UPDATE
                    ",
                    $booking_id
                ),
                ARRAY_A
            );

            if (!$booking) {
                throw new \RuntimeException(
                    'The requested booking could not be found.'
                );
            }

            $previous_status = (string) $booking['status'];

            if (!isset(self::ALLOWED_TRANSITIONS[$previous_status])) {
                throw new \RuntimeException(
                    'The booking has an unsupported current status.'
                );
            }

            if (
                !in_array(
                    $new_status,
                    self::ALLOWED_TRANSITIONS[$previous_status],
                    true
                )
            ) {
                throw new \RuntimeException(
                    sprintf(
                        'The booking cannot transition from %s to %s.',
                        $previous_status,
                        $new_status
                    )
                );
            }

            $now = current_time('mysql', true);

            /*
             * Update the booking.
             */
            $update_data = [
                'status' => $new_status,
                'updated_at' => $now,
            ];

            $formats = [
                '%s',
                '%s',
            ];

            /*
             * Record the confirmation timestamp only when
             * the booking is confirmed.
             */
            if ($new_status === 'confirmed') {
                $update_data['confirmed_at'] = $now;
                $formats[] = '%s';
            }

            $updated = $this->wpdb->update(
                $this->bookings_table,
                $update_data,
                ['id' => $booking_id],
                $formats,
                ['%d']
            );

            if ($updated !== 1) {
                throw new \RuntimeException(
                    'The booking status could not be updated.'
                );
            }

            /*
             * Record the status transition in the activity log.
             */
            $user_id = get_current_user_id();

            $action = $new_status === 'cancelled'
                ? 'booking_cancelled'
                : 'booking_status_changed';

            $description = sprintf(
                'Booking status changed from %s to %s.',
                $previous_status,
                $new_status
            );

            $logged = $this->wpdb->insert(
                $this->activity_log_table,
                [
                    'user_id' => $user_id > 0 ? $user_id : null,
                    'entity_type' => 'booking',
                    'entity_id' => $booking_id,
                    'action' => $action,
                    'description' => $description,
                    'created_at' => $now,
                    'previous_status' => $previous_status,
                    'new_status' => $new_status,
                    'reason' => $reason !== '' ? $reason : null,
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

            if ($logged === false) {
                throw new \RuntimeException(
                    'The booking activity record could not be created.'
                );
            }

            /*
             * Commit the status change and activity record together.
             */
            if ($this->wpdb->query('COMMIT') === false) {
                throw new \RuntimeException(
                    'The booking status transaction could not be committed.'
                );
            }

            return true;
        } catch (\Throwable $exception) {
            $this->wpdb->query('ROLLBACK');

            return new \WP_Error(
                'booking_status_update_failed',
                $exception->getMessage()
            );
        }
    }

    /**
     * Retrieve permitted next statuses for a booking.
     *
     * @param string $current_status Current booking status.
     * @return array
     */
    public function getAllowedTransitions(string $current_status): array
    {
        return self::ALLOWED_TRANSITIONS[$current_status] ?? [];
    }
}
