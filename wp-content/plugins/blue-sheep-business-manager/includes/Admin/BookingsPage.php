<?php

namespace BlueSheep\BusinessManager\Admin;

use BlueSheep\BusinessManager\Modules\Bookings\BookingRepository;
use BlueSheep\BusinessManager\Modules\Bookings\BookingStatusService;
use BlueSheep\BusinessManager\Modules\Bookings\BookingStatusHandler;

defined('ABSPATH') || exit;

/**
 * Renders the Business Manager bookings administration page.
 */
final class BookingsPage
{
    /**
     * Booking repository.
     *
     * @var BookingRepository
     */
    private BookingRepository $booking_repository;

    /**
     * Records displayed per page.
     */
    private const PER_PAGE = 20;

    /**
     * Initialize the page.
     */
    public function __construct()
    {
        $this->booking_repository = new BookingRepository();
    }

    /**
     * Render the bookings page.
     */
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You are not allowed to access this page.', 'blue-sheep-business-manager')
            );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Bookings', 'blue-sheep-business-manager') . '</h1>';

        $booking_id = isset($_GET['view'])
            ? absint(wp_unslash($_GET['view']))
            : 0;

        if ($booking_id > 0) {
            $this->renderDetails($booking_id);
        } else {
            $this->renderListing();
        }

        echo '</div>';
    }

    /**
     * Render the booking listing.
     */
    private function renderListing(): void
    {
        $search = isset($_GET['booking_search'])
            ? sanitize_text_field(wp_unslash($_GET['booking_search']))
            : '';

        $status_filter = isset($_GET['booking_status'])
            ? sanitize_key(wp_unslash($_GET['booking_status']))
            : '';

        $current_page = isset($_GET['paged'])
            ? max(1, absint(wp_unslash($_GET['paged'])))
            : 1;

        $offset = ($current_page - 1) * self::PER_PAGE;

        $bookings = $this->booking_repository->getAll(
            self::PER_PAGE,
            $offset,
            $search,
            $status_filter
        );

        $total_bookings = $this->booking_repository->count(
            $search,
            $status_filter
        );

        $total_pages = (int) ceil(
            $total_bookings / self::PER_PAGE
        );

        echo '<form method="get" style="margin:20px 0;">';

        echo '<input type="hidden" name="page" value="bsbm-bookings">';

        echo '<input type="search" name="booking_search" value="' .
            esc_attr($search) .
            '" placeholder="' .
            esc_attr__('Search booking reference or customer', 'blue-sheep-business-manager') .
            '" style="min-width:280px;">';

        echo '<select name="booking_status">';

        $statuses = [
            '' => __('All statuses', 'blue-sheep-business-manager'),
            'pending_confirmation' => __('Pending Confirmation', 'blue-sheep-business-manager'),
            'confirmed' => __('Confirmed', 'blue-sheep-business-manager'),
            'in_progress' => __('In Progress', 'blue-sheep-business-manager'),
            'completed' => __('Completed', 'blue-sheep-business-manager'),
            'cancelled' => __('Cancelled', 'blue-sheep-business-manager'),
        ];

        foreach ($statuses as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' .
                selected($status_filter, $value, false) .
                '>' . esc_html($label) . '</option>';
        }

        echo '</select>';

        submit_button(
            __('Filter Bookings', 'blue-sheep-business-manager'),
            'secondary',
            'filter_action',
            false
        );

        echo '</form>';

        echo '<p>';
        echo esc_html__('Total bookings:', 'blue-sheep-business-manager');
        echo ' <strong>' . esc_html((string) $total_bookings) . '</strong>';
        echo '</p>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';

        $columns = [
            __('Booking Reference', 'blue-sheep-business-manager'),
            __('Customer', 'blue-sheep-business-manager'),
            __('Quotation', 'blue-sheep-business-manager'),
            __('Travel Dates', 'blue-sheep-business-manager'),
            __('Travellers', 'blue-sheep-business-manager'),
            __('Status', 'blue-sheep-business-manager'),
            __('Created', 'blue-sheep-business-manager'),
            __('Action', 'blue-sheep-business-manager'),
        ];

        foreach ($columns as $column) {
            echo '<th>' . esc_html($column) . '</th>';
        }

        echo '</tr></thead><tbody>';

        if (empty($bookings)) {
            echo '<tr><td colspan="8">';
            echo esc_html__('No bookings found.', 'blue-sheep-business-manager');
            echo '</td></tr>';
        } else {
            foreach ($bookings as $booking) {
                $booking_id = absint($booking['id'] ?? 0);

                $details_url = add_query_arg(
                    [
                        'page' => 'bsbm-bookings',
                        'view' => $booking_id,
                    ],
                    admin_url('admin.php')
                );

                echo '<tr>';

                echo '<td><strong>' .
                    esc_html((string) ($booking['booking_reference'] ?? '')) .
                    '</strong></td>';

                echo '<td>';
                echo esc_html((string) ($booking['customer_name'] ?? ''));
                echo '<br><small>';
                echo esc_html((string) ($booking['customer_email'] ?? ''));
                echo '</small></td>';

                echo '<td>';
                echo esc_html((string) ($booking['quotation_reference'] ?? ''));
                echo '</td>';

                echo '<td>';
                echo esc_html((string) ($booking['travel_start_date'] ?? ''));
                echo ' - ';
                echo esc_html((string) ($booking['travel_end_date'] ?? ''));
                echo '</td>';

                echo '<td>';
                echo esc_html((string) ($booking['traveller_count'] ?? ''));
                echo '</td>';

                echo '<td>';
                echo esc_html($this->formatStatus(
                    (string) ($booking['status'] ?? '')
                ));
                echo '</td>';

                echo '<td>';
                echo esc_html($this->formatDate(
                    (string) ($booking['created_at'] ?? '')
                ));
                echo '</td>';

                echo '<td><a href="' . esc_url($details_url) . '">';
                echo esc_html__('View Details', 'blue-sheep-business-manager');
                echo '</a></td>';

                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        if ($total_pages > 1) {
            $this->renderPagination(
                $current_page,
                $total_pages,
                $search,
                $status_filter
            );
        }
    }

    /**
     * Render an individual booking.
     *
     * @param int $booking_id Booking ID.
     */
    private function renderDetails(int $booking_id): void
    {
        $booking = $this->booking_repository->getById($booking_id);

        if (!$booking) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Booking not found.', 'blue-sheep-business-manager');
            echo '</p></div>';

            $this->renderBackLink();

            return;
        }

        $this->renderStatusNotices();

        echo '<p>';
        echo '<a href="' . esc_url(
            admin_url('admin.php?page=bsbm-bookings')
        ) . '">';
        echo esc_html__('Back to Bookings', 'blue-sheep-business-manager');
        echo '</a>';
        echo '</p>';

        echo '<h2>';
        echo esc_html((string) $booking['booking_reference']);
        echo '</h2>';

        echo '<table class="widefat striped" style="max-width:900px;">';
        echo '<tbody>';

        $details = [
            __('Booking Reference', 'blue-sheep-business-manager')
                => $booking['booking_reference'] ?? '',

            __('Booking Status', 'blue-sheep-business-manager')
                => $this->formatStatus((string) ($booking['status'] ?? '')),

            __('Customer Name', 'blue-sheep-business-manager')
                => $booking['customer_name'] ?? '',

            __('Customer Email', 'blue-sheep-business-manager')
                => $booking['customer_email'] ?? '',

            __('Customer Phone', 'blue-sheep-business-manager')
                => $booking['customer_phone'] ?? '',

            __('Quotation Reference', 'blue-sheep-business-manager')
                => $booking['quotation_reference'] ?? '',

            __('Enquiry ID', 'blue-sheep-business-manager')
                => $booking['enquiry_id'] ?? '',

            __('Accepted Quotation Version', 'blue-sheep-business-manager')
                => $booking['accepted_version_number'] ?? '',

            __('Travel Start Date', 'blue-sheep-business-manager')
                => $booking['travel_start_date'] ?? '',

            __('Travel End Date', 'blue-sheep-business-manager')
                => $booking['travel_end_date'] ?? '',

            __('Traveller Count', 'blue-sheep-business-manager')
                => $booking['traveller_count'] ?? '',

            __('Created At (UTC)', 'blue-sheep-business-manager')
                => $booking['created_at'] ?? '',

            __('Confirmed At (UTC)', 'blue-sheep-business-manager')
                => $booking['confirmed_at'] ?? '',
        ];

        foreach ($details as $label => $value) {
            echo '<tr>';
            echo '<th style="width:240px;">' . esc_html($label) . '</th>';
            echo '<td>' . esc_html((string) $value) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        if (!empty($booking['confirmation_exception_reason'])) {
            echo '<h3>';
            echo esc_html__('Confirmation Exception', 'blue-sheep-business-manager');
            echo '</h3>';

            echo '<p>';
            echo esc_html((string) $booking['confirmation_exception_reason']);
            echo '</p>';
        }

        $this->renderStatusForm($booking);

        $this->renderActivityHistory($booking_id);
    }

    /**
     * Display status update notices.
     */
    private function renderStatusNotices(): void
    {
        $status_result = isset($_GET['booking_status_result'])
            ? sanitize_key(wp_unslash($_GET['booking_status_result']))
            : '';

        if ($status_result === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Booking status updated successfully.', 'blue-sheep-business-manager');
            echo '</p></div>';
        } elseif ($status_result === 'failed') {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('The booking status could not be updated. Verify the requested transition and try again.', 'blue-sheep-business-manager');
            echo '</p></div>';
        } elseif ($status_result === 'invalid_request') {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('The booking status update request was invalid.', 'blue-sheep-business-manager');
            echo '</p></div>';
        }
    }

    /**
     * Render permitted booking status transitions.
     *
     * @param array $booking Booking record.
     */
    private function renderStatusForm(array $booking): void
    {
        $booking_id = absint($booking['id'] ?? 0);
        $current_status = (string) ($booking['status'] ?? '');

        if ($booking_id <= 0) {
            return;
        }

        $status_service = new BookingStatusService();

        $allowed_transitions = $status_service->getAllowedTransitions(
            $current_status
        );

        echo '<h2 style="margin-top:30px;">';
        echo esc_html__('Booking Status Management', 'blue-sheep-business-manager');
        echo '</h2>';

        echo '<div style="max-width:900px; padding:18px; border:1px solid #c3c4c7; background:#fff;">';

        echo '<p>';
        echo esc_html__('Current status:', 'blue-sheep-business-manager');
        echo ' <strong>' . esc_html($this->formatStatus($current_status)) . '</strong>';
        echo '</p>';

        if (empty($allowed_transitions)) {
            echo '<p>';
            echo esc_html__(
                'This booking has reached a terminal status. No further status changes are available.',
                'blue-sheep-business-manager'
            );
            echo '</p>';

            echo '</div>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';

        echo '<input type="hidden" name="action" value="bsbm_update_booking_status">';
        echo '<input type="hidden" name="booking_id" value="' . esc_attr((string) $booking_id) . '">';

        wp_nonce_field(
            BookingStatusHandler::NONCE_ACTION,
            'bsbm_booking_status_nonce'
        );

        echo '<table class="form-table"><tbody>';

        echo '<tr>';
        echo '<th scope="row"><label for="bsbm-new-booking-status">';
        echo esc_html__('New Status', 'blue-sheep-business-manager');
        echo '</label></th>';

        echo '<td><select name="new_status" id="bsbm-new-booking-status" required>';

        echo '<option value="">';
        echo esc_html__('Select a status', 'blue-sheep-business-manager');
        echo '</option>';

        foreach ($allowed_transitions as $status) {
            echo '<option value="' . esc_attr($status) . '">';
            echo esc_html($this->formatStatus($status));
            echo '</option>';
        }

        echo '</select></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="bsbm-booking-status-reason">';
        echo esc_html__('Reason / Notes', 'blue-sheep-business-manager');
        echo '</label></th>';
        echo '<td>';

        echo '<textarea name="reason" id="bsbm-booking-status-reason" rows="3" class="large-text" maxlength="5000"></textarea>';

        echo '<p class="description">';
        echo esc_html__(
            'A reason is required when cancelling a booking. Other status changes may include optional internal notes.',
            'blue-sheep-business-manager'
        );
        echo '</p>';

        echo '</td>';
        echo '</tr>';

        echo '</tbody></table>';

        echo '<p>';
        echo '<button type="submit" class="button button-primary">';
        echo esc_html__('Update Booking Status', 'blue-sheep-business-manager');
        echo '</button>';
        echo '</p>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Render booking activity history.
     *
     * Uses the existing bs_activity_log schema.
     *
     * @param int $booking_id Booking ID.
     */
    private function renderActivityHistory(int $booking_id): void
    {
        global $wpdb;

        $activity_table = $wpdb->prefix . 'bs_activity_log';

        $activities = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT
                    id,
                    user_id,
                    entity_type,
                    entity_id,
                    action,
                    description,
                    created_at,
                    previous_status,
                    new_status,
                    reason
                FROM {$activity_table}
                WHERE entity_type = %s
                  AND entity_id = %d
                ORDER BY created_at DESC, id DESC
                ",
                'booking',
                $booking_id
            ),
            ARRAY_A
        );

        echo '<h2 style="margin-top:30px;">';
        echo esc_html__('Booking Activity History', 'blue-sheep-business-manager');
        echo '</h2>';

        echo '<table class="widefat striped" style="max-width:1100px;">';

        echo '<thead><tr>';
        echo '<th>' . esc_html__('Date (UTC)', 'blue-sheep-business-manager') . '</th>';
        echo '<th>' . esc_html__('Action', 'blue-sheep-business-manager') . '</th>';
        echo '<th>' . esc_html__('Previous Status', 'blue-sheep-business-manager') . '</th>';
        echo '<th>' . esc_html__('New Status', 'blue-sheep-business-manager') . '</th>';
        echo '<th>' . esc_html__('Administrator', 'blue-sheep-business-manager') . '</th>';
        echo '<th>' . esc_html__('Description', 'blue-sheep-business-manager') . '</th>';
        echo '<th>' . esc_html__('Reason', 'blue-sheep-business-manager') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($activities)) {
            echo '<tr><td colspan="7">';
            echo esc_html__('No activity has been recorded for this booking.', 'blue-sheep-business-manager');
            echo '</td></tr>';
        } else {
            foreach ($activities as $activity) {
                $user_id = absint($activity['user_id'] ?? 0);
                $user = $user_id > 0 ? get_userdata($user_id) : false;

                $administrator = $user
                    ? $user->display_name
                    : __('System', 'blue-sheep-business-manager');

                echo '<tr>';

                echo '<td>';
                echo esc_html($this->formatDate(
                    (string) ($activity['created_at'] ?? '')
                ));
                echo '</td>';

                echo '<td>';
                echo esc_html(
                    ucwords(str_replace('_', ' ', (string) ($activity['action'] ?? '')))
                );
                echo '</td>';

                echo '<td>';
                echo esc_html($this->formatStatus(
                    (string) ($activity['previous_status'] ?? '')
                ));
                echo '</td>';

                echo '<td>';
                echo esc_html($this->formatStatus(
                    (string) ($activity['new_status'] ?? '')
                ));
                echo '</td>';

                echo '<td>';
                echo esc_html($administrator);
                echo '</td>';

                echo '<td>';
                echo esc_html((string) ($activity['description'] ?? ''));
                echo '</td>';

                echo '<td>';
                echo esc_html((string) ($activity['reason'] ?? ''));
                echo '</td>';

                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    /**
     * Render pagination.
     */
    private function renderPagination(
        int $current_page,
        int $total_pages,
        string $search,
        string $status_filter
    ): void {
        $base_url = add_query_arg(
            [
                'page' => 'bsbm-bookings',
                'booking_search' => $search,
                'booking_status' => $status_filter,
                'paged' => '%#%',
            ],
            admin_url('admin.php')
        );

        echo '<div class="tablenav"><div class="tablenav-pages">';

        echo wp_kses_post(
            paginate_links([
                'base' => $base_url,
                'format' => '',
                'current' => $current_page,
                'total' => $total_pages,
                'prev_text' => __('Previous', 'blue-sheep-business-manager'),
                'next_text' => __('Next', 'blue-sheep-business-manager'),
            ])
        );

        echo '</div></div>';
    }

    /**
     * Render the back link.
     */
    private function renderBackLink(): void
    {
        echo '<p><a href="' .
            esc_url(admin_url('admin.php?page=bsbm-bookings')) .
            '">';
        echo esc_html__('Back to Bookings', 'blue-sheep-business-manager');
        echo '</a></p>';
    }

    /**
     * Format booking status for display.
     */
    private function formatStatus(string $status): string
    {
        if ($status === '') {
            return 'N/A';
        }

        return ucwords(str_replace('_', ' ', $status));
    }

    /**
     * Format a UTC database date for display.
     */
    private function formatDate(string $date): string
    {
        if ($date === '') {
            return '';
        }

        $timestamp = strtotime($date . ' UTC');

        if ($timestamp === false) {
            return $date;
        }

        return wp_date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $timestamp
        );
    }
}
