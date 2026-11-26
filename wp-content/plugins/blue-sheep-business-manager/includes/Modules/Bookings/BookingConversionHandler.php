<?php

namespace BlueSheep\BusinessManager\Modules\Bookings;

defined('ABSPATH') || exit;

/**
 * Handles administrator requests to convert accepted quotations
 * into bookings.
 */
class BookingConversionHandler
{
    /**
     * Nonce action used by the booking conversion form.
     */
    public const NONCE_ACTION = 'bsbm_convert_accepted_quotation';

    /**
     * Register WordPress hooks.
     */
    public function register(): void
    {
        add_action(
            'admin_post_bsbm_convert_accepted_quotation',
            [$this, 'handleConversion']
        );
    }

    /**
     * Process a booking conversion request.
     */
    public function handleConversion(): void
    {
        /*
         * Only administrators with the required capability
         * can convert quotations.
         */
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You are not allowed to perform this action.', 'blue-sheep-business-manager'),
                esc_html__('Permission denied', 'blue-sheep-business-manager'),
                ['response' => 403]
            );
        }

        /*
         * Validate request method.
         */
        if (
            !isset($_SERVER['REQUEST_METHOD'])
            || strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST'
        ) {
            wp_die(
                esc_html__('Invalid request method.', 'blue-sheep-business-manager'),
                esc_html__('Invalid request', 'blue-sheep-business-manager'),
                ['response' => 405]
            );
        }

        /*
         * Verify nonce.
         */
        check_admin_referer(self::NONCE_ACTION);

        /*
         * Retrieve and validate quotation ID.
         */
        $quotation_id = isset($_POST['quotation_id'])
            ? absint(wp_unslash($_POST['quotation_id']))
            : 0;

        if ($quotation_id <= 0) {
            $this->redirectToQuotation(0, 'invalid_request');
        }

        /*
         * Require explicit administrator confirmation.
         */
        $confirmation = isset($_POST['confirm_booking_details'])
            ? sanitize_text_field(wp_unslash($_POST['confirm_booking_details']))
            : '';

        if ($confirmation !== '1') {
            $this->redirectToQuotation($quotation_id, 'confirmation_required');
        }

        /*
         * Retrieve travel details.
         */
        $travel_start_date = isset($_POST['travel_start_date'])
            ? sanitize_text_field(wp_unslash($_POST['travel_start_date']))
            : '';

        $travel_end_date = isset($_POST['travel_end_date'])
            ? sanitize_text_field(wp_unslash($_POST['travel_end_date']))
            : '';

        $traveller_count = isset($_POST['traveller_count'])
            ? absint(wp_unslash($_POST['traveller_count']))
            : 0;

        $reason = isset($_POST['reason'])
            ? sanitize_textarea_field(wp_unslash($_POST['reason']))
            : '';

        /*
         * Validate required booking details before calling
         * the service.
         */
        if (
            $travel_start_date === ''
            || $travel_end_date === ''
            || $traveller_count < 1
        ) {
            $this->redirectToQuotation($quotation_id, 'invalid_booking_details');
        }

        /*
         * Convert the accepted quotation into a booking.
         */
        $booking_service = new BookingService();

        $result = $booking_service->createBooking(
            $quotation_id,
            $travel_start_date,
            $travel_end_date,
            $traveller_count,
            $reason
        );

        if (is_wp_error($result)) {
            $this->redirectToQuotation(
                $quotation_id,
                'failed',
                $result->get_error_code()
            );
        }

        /*
         * Redirect after successful conversion.
         */
        $this->redirectToQuotation(
            $quotation_id,
            'created',
            '',
            (int) $result
        );
    }

    /**
     * Redirect to the quotation detail page.
     *
     * @param int    $quotation_id Quotation ID.
     * @param string $result       Result identifier.
     * @param string $error_code   Optional error code.
     * @param int    $booking_id   Optional created booking ID.
     */
    private function redirectToQuotation(
        int $quotation_id,
        string $result,
        string $error_code = '',
        int $booking_id = 0
    ): void {
        $url = admin_url('admin.php?page=bsbm-quotations');

        if ($quotation_id > 0) {
            $url = add_query_arg(
                'view',
                $quotation_id,
                $url
            );
        }

        $url = add_query_arg(
            'booking_result',
            sanitize_key($result),
            $url
        );

        if ($error_code !== '') {
            $url = add_query_arg(
                'booking_error',
                sanitize_key($error_code),
                $url
            );
        }

        if ($booking_id > 0) {
            $url = add_query_arg(
                'booking_id',
                $booking_id,
                $url
            );
        }

        wp_safe_redirect($url);
        exit;
    }
}
