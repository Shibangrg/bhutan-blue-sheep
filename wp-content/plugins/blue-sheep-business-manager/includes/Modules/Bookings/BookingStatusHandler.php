<?php

namespace BlueSheep\BusinessManager\Modules\Bookings;

defined('ABSPATH') || exit;

/**
 * Handles administrator booking status update requests.
 */
final class BookingStatusHandler
{
    /**
     * Nonce action.
     */
    public const NONCE_ACTION = 'bsbm_update_booking_status';

    /**
     * Nonce field.
     */
    private const NONCE_FIELD = 'bsbm_booking_status_nonce';

    /**
     * Booking status service.
     */
    private BookingStatusService $status_service;

    /**
     * Initialize the handler.
     */
    public function __construct(
        ?BookingStatusService $status_service = null
    ) {
        $this->status_service = $status_service ?? new BookingStatusService();
    }

    /**
     * Register the WordPress action.
     */
    public function register(): void
    {
        add_action(
            'admin_post_bsbm_update_booking_status',
            [$this, 'handle']
        );
    }

    /**
     * Process a booking status update.
     */
    public function handle(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You are not allowed to update bookings.', 'blue-sheep-business-manager'),
                '',
                ['response' => 403]
            );
        }

        check_admin_referer(
            self::NONCE_ACTION,
            self::NONCE_FIELD
        );

        $booking_id = isset($_POST['booking_id'])
            ? absint(wp_unslash($_POST['booking_id']))
            : 0;

        $new_status = isset($_POST['new_status'])
            ? sanitize_key(wp_unslash($_POST['new_status']))
            : '';

        $reason = isset($_POST['reason'])
            ? sanitize_textarea_field(wp_unslash($_POST['reason']))
            : '';

        if ($booking_id <= 0 || $new_status === '') {
            $this->redirect(
                $booking_id,
                'invalid_request'
            );
        }

        $result = $this->status_service->updateStatus(
            $booking_id,
            $new_status,
            $reason
        );

        if (is_wp_error($result)) {
            $this->redirect(
                $booking_id,
                'failed',
                $result->get_error_code()
            );
        }

        $this->redirect(
            $booking_id,
            'updated'
        );
    }

    /**
     * Redirect to the booking details page.
     *
     * @param int    $booking_id Booking ID.
     * @param string $result     Result identifier.
     * @param string $error      Optional error code.
     */
    private function redirect(
        int $booking_id,
        string $result,
        string $error = ''
    ): void {
        $url = add_query_arg(
            [
                'page' => 'bsbm-bookings',
                'view' => $booking_id,
                'booking_status_result' => $result,
            ],
            admin_url('admin.php')
        );

        if ($error !== '') {
            $url = add_query_arg(
                'booking_status_error',
                sanitize_key($error),
                $url
            );
        }

        wp_safe_redirect($url);
        exit;
    }
}
