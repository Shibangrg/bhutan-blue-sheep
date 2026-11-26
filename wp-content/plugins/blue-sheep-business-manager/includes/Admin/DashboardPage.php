<?php

namespace BlueSheep\BusinessManager\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the Business Manager dashboard page.
 */
final class DashboardPage
{
    private $wpdb;

    private string $customers_table;
    private string $enquiries_table;
    private string $quotations_table;
    private string $bookings_table;

    /**
     * Initialize dashboard dependencies.
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->customers_table = $wpdb->prefix . 'bs_customers';
        $this->enquiries_table = $wpdb->prefix . 'bs_enquiries';
        $this->quotations_table = $wpdb->prefix . 'bs_quotations';
        $this->bookings_table = $wpdb->prefix . 'bs_bookings';
    }

    /**
     * Render the dashboard page.
     */
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'You do not have permission to access this page.',
                    'blue-sheep-business-manager'
                )
            );
        }

        $metrics = $this->getDashboardMetrics();

        $enquiry_statuses = $this->getStatusCounts(
            $this->enquiries_table
        );

        $quotation_statuses = $this->getStatusCounts(
            $this->quotations_table
        );

        $booking_statuses = $this->getStatusCounts(
            $this->bookings_table
        );

        $recent_enquiries = $this->getRecentEnquiries();
        $recent_quotations = $this->getRecentQuotations();
        $recent_bookings = $this->getRecentBookings();

        ?>

        <div class="wrap bsbm-dashboard">

            <h1>
                <?php
                echo esc_html__(
                    'Business Manager Dashboard',
                    'blue-sheep-business-manager'
                );
                ?>
            </h1>

            <p class="bsbm-dashboard-intro">
                <?php
                echo esc_html__(
                    'Monitor your customers, enquiries, quotations and bookings from one place.',
                    'blue-sheep-business-manager'
                );
                ?>
            </p>

            <?php $this->renderStyles(); ?>

            <!-- Summary metrics -->

            <div class="bsbm-dashboard-grid">

                <?php
                $this->renderMetricCard(
                    __('Total Customers', 'blue-sheep-business-manager'),
                    $metrics['customers'],
                    __('Customer records', 'blue-sheep-business-manager'),
                    'dashicons-groups',
                    'bsbm-card-blue',
                    'admin.php?page=bsbm-customers'
                );

                $this->renderMetricCard(
                    __('Total Enquiries', 'blue-sheep-business-manager'),
                    $metrics['enquiries'],
                    sprintf(
                        __('%d active enquiries', 'blue-sheep-business-manager'),
                        $metrics['active_enquiries']
                    ),
                    'dashicons-email',
                    'bsbm-card-purple',
                    'admin.php?page=bsbm-enquiries'
                );

                $this->renderMetricCard(
                    __('Total Quotations', 'blue-sheep-business-manager'),
                    $metrics['quotations'],
                    sprintf(
                        __('%d sent quotations', 'blue-sheep-business-manager'),
                        $metrics['sent_quotations']
                    ),
                    'dashicons-media-document',
                    'bsbm-card-orange',
                    'admin.php?page=bsbm-quotations'
                );

                $this->renderMetricCard(
                    __('Total Bookings', 'blue-sheep-business-manager'),
                    $metrics['bookings'],
                    sprintf(
                        __('%d pending confirmations', 'blue-sheep-business-manager'),
                        $metrics['pending_bookings']
                    ),
                    'dashicons-calendar-alt',
                    'bsbm-card-green',
                    'admin.php?page=bsbm-bookings'
                );
                ?>

            </div>

            <!-- Business status overview -->

            <div class="bsbm-dashboard-section">

                <h2>
                    <?php
                    echo esc_html__(
                        'Business Overview',
                        'blue-sheep-business-manager'
                    );
                    ?>
                </h2>

                <div class="bsbm-dashboard-overview-grid">

                    <div class="bsbm-dashboard-panel">

                        <h3>
                            <?php
                            echo esc_html__(
                                'Enquiry Status',
                                'blue-sheep-business-manager'
                            );
                            ?>
                        </h3>

                        <?php
                        $this->renderStatusList(
                            $enquiry_statuses,
                            [
                                'new',
                                'under_review',
                                'awaiting_customer_information',
                                'in_quotation',
                                'quoted',
                                'converted',
                                'declined',
                                'closed',
                            ]
                        );
                        ?>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=bsbm-enquiries')); ?>">
                            <?php
                            echo esc_html__(
                                'Manage Enquiries',
                                'blue-sheep-business-manager'
                            );
                            ?>
                        </a>

                    </div>

                    <div class="bsbm-dashboard-panel">

                        <h3>
                            <?php
                            echo esc_html__(
                                'Quotation Status',
                                'blue-sheep-business-manager'
                            );
                            ?>
                        </h3>

                        <?php
                        $this->renderStatusList(
                            $quotation_statuses,
                            [
                                'draft',
                                'sent',
                                'accepted',
                                'declined',
                                'expired',
                            ]
                        );
                        ?>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=bsbm-quotations')); ?>">
                            <?php
                            echo esc_html__(
                                'Manage Quotations',
                                'blue-sheep-business-manager'
                            );
                            ?>
                        </a>

                    </div>

                    <div class="bsbm-dashboard-panel">

                        <h3>
                            <?php
                            echo esc_html__(
                                'Booking Status',
                                'blue-sheep-business-manager'
                            );
                            ?>
                        </h3>

                        <?php
                        $this->renderStatusList(
                            $booking_statuses,
                            [
                                'pending_confirmation',
                                'confirmed',
                                'in_progress',
                                'completed',
                                'cancelled',
                            ]
                        );
                        ?>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=bsbm-bookings')); ?>">
                            <?php
                            echo esc_html__(
                                'Manage Bookings',
                                'blue-sheep-business-manager'
                            );
                            ?>
                        </a>

                    </div>

                </div>

            </div>

            <!-- Pending actions -->

            <div class="bsbm-dashboard-section">

                <h2>
                    <?php
                    echo esc_html__(
                        'Pending Actions',
                        'blue-sheep-business-manager'
                    );
                    ?>
                </h2>

                <div class="bsbm-dashboard-pending-grid">

                    <a
                        class="bsbm-pending-card"
                        href="<?php echo esc_url(admin_url('admin.php?page=bsbm-enquiries')); ?>"
                    >
                        <span class="dashicons dashicons-email"></span>

                        <span>
                            <strong>
                                <?php echo esc_html($metrics['active_enquiries']); ?>
                            </strong>

                            <small>
                                <?php
                                echo esc_html__(
                                    'Active enquiries',
                                    'blue-sheep-business-manager'
                                );
                                ?>
                            </small>
                        </span>
                    </a>

                    <a
                        class="bsbm-pending-card"
                        href="<?php echo esc_url(admin_url('admin.php?page=bsbm-quotations')); ?>"
                    >
                        <span class="dashicons dashicons-media-document"></span>

                        <span>
                            <strong>
                                <?php echo esc_html($metrics['draft_quotations']); ?>
                            </strong>

                            <small>
                                <?php
                                echo esc_html__(
                                    'Draft quotations',
                                    'blue-sheep-business-manager'
                                );
                                ?>
                            </small>
                        </span>
                    </a>

                    <a
                        class="bsbm-pending-card"
                        href="<?php echo esc_url(admin_url('admin.php?page=bsbm-bookings')); ?>"
                    >
                        <span class="dashicons dashicons-calendar-alt"></span>

                        <span>
                            <strong>
                                <?php echo esc_html($metrics['pending_bookings']); ?>
                            </strong>

                            <small>
                                <?php
                                echo esc_html__(
                                    'Bookings awaiting confirmation',
                                    'blue-sheep-business-manager'
                                );
                                ?>
                            </small>
                        </span>
                    </a>

                </div>

            </div>

            <!-- Recent enquiries -->

            <div class="bsbm-dashboard-section">

                <div class="bsbm-dashboard-section-heading">

                    <h2>
                        <?php
                        echo esc_html__(
                            'Recent Enquiries',
                            'blue-sheep-business-manager'
                        );
                        ?>
                    </h2>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=bsbm-enquiries')); ?>">
                        <?php
                        echo esc_html__(
                            'View All',
                            'blue-sheep-business-manager'
                        );
                        ?>
                    </a>

                </div>

                <?php
                $this->renderRecentEnquiries($recent_enquiries);
                ?>

            </div>

            <!-- Recent quotations -->

            <div class="bsbm-dashboard-section">

                <div class="bsbm-dashboard-section-heading">

                    <h2>
                        <?php
                        echo esc_html__(
                            'Recent Quotations',
                            'blue-sheep-business-manager'
                        );
                        ?>
                    </h2>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=bsbm-quotations')); ?>">
                        <?php
                        echo esc_html__(
                            'View All',
                            'blue-sheep-business-manager'
                        );
                        ?>
                    </a>

                </div>

                <?php
                $this->renderRecentQuotations($recent_quotations);
                ?>

            </div>

            <!-- Recent bookings -->

            <div class="bsbm-dashboard-section">

                <div class="bsbm-dashboard-section-heading">

                    <h2>
                        <?php
                        echo esc_html__(
                            'Recent Bookings',
                            'blue-sheep-business-manager'
                        );
                        ?>
                    </h2>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=bsbm-bookings')); ?>">
                        <?php
                        echo esc_html__(
                            'View All',
                            'blue-sheep-business-manager'
                        );
                        ?>
                    </a>

                </div>

                <?php
                $this->renderRecentBookings($recent_bookings);
                ?>

            </div>

        </div>

        <?php
    }

    /**
     * Retrieve dashboard summary metrics.
     */
    private function getDashboardMetrics(): array
    {
        $customers = $this->countRecords($this->customers_table);
        $enquiries = $this->countRecords($this->enquiries_table);
        $quotations = $this->countRecords($this->quotations_table);
        $bookings = $this->countRecords($this->bookings_table);

        return [
            'customers' => $customers,
            'enquiries' => $enquiries,
            'active_enquiries' => $this->countRecords(
                $this->enquiries_table,
                "WHERE status <> 'closed'"
            ),
            'quotations' => $quotations,
            'sent_quotations' => $this->countRecords(
                $this->quotations_table,
                "WHERE status = 'sent'"
            ),
            'draft_quotations' => $this->countRecords(
                $this->quotations_table,
                "WHERE status = 'draft'"
            ),
            'bookings' => $bookings,
            'pending_bookings' => $this->countRecords(
                $this->bookings_table,
                "WHERE status = 'pending_confirmation'"
            ),
        ];
    }

    /**
     * Count records in a business table.
     */
    private function countRecords(
        string $table,
        string $condition = ''
    ): int {
        $query = "SELECT COUNT(*) FROM {$table} {$condition}";

        return (int) $this->wpdb->get_var($query);
    }

    /**
     * Retrieve grouped record counts by status.
     */
    private function getStatusCounts(string $table): array
    {
        $results = $this->wpdb->get_results(
            "SELECT status, COUNT(*) AS total
             FROM {$table}
             GROUP BY status",
            ARRAY_A
        );

        $counts = [];

        if (!is_array($results)) {
            return $counts;
        }

        foreach ($results as $result) {
            $counts[(string) $result['status']] = (int) $result['total'];
        }

        return $counts;
    }

    /**
     * Retrieve recent enquiries.
     */
    private function getRecentEnquiries(): array
    {
        $results = $this->wpdb->get_results(
            "SELECT
                e.id,
                e.enquiry_type,
                e.status,
                e.created_at,
                c.full_name,
                c.email
             FROM {$this->enquiries_table} e
             LEFT JOIN {$this->customers_table} c
                ON c.id = e.customer_id
             ORDER BY e.created_at DESC
             LIMIT 5",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Retrieve recent quotations.
     */
    private function getRecentQuotations(): array
    {
        $results = $this->wpdb->get_results(
            "SELECT
                q.id,
                q.reference_number,
                q.status,
                q.updated_at,
                c.full_name
             FROM {$this->quotations_table} q
             LEFT JOIN {$this->customers_table} c
                ON c.id = q.customer_id
             ORDER BY q.updated_at DESC
             LIMIT 5",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Retrieve recent bookings.
     */
    private function getRecentBookings(): array
    {
        $results = $this->wpdb->get_results(
            "SELECT
                b.id,
                b.booking_reference,
                b.travel_start_date,
                b.travel_end_date,
                b.status,
                b.created_at,
                c.full_name
             FROM {$this->bookings_table} b
             LEFT JOIN {$this->customers_table} c
                ON c.id = b.customer_id
             ORDER BY b.created_at DESC
             LIMIT 5",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Render a summary metric card.
     */
    private function renderMetricCard(
        string $title,
        int $value,
        string $description,
        string $icon,
        string $color_class,
        string $url
    ): void {
        ?>

        <a
            class="bsbm-dashboard-metric <?php echo esc_attr($color_class); ?>"
            href="<?php echo esc_url(admin_url($url)); ?>"
        >

            <div class="bsbm-metric-icon">
                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
            </div>

            <div class="bsbm-metric-content">

                <span class="bsbm-metric-title">
                    <?php echo esc_html($title); ?>
                </span>

                <strong>
                    <?php echo esc_html(number_format_i18n($value)); ?>
                </strong>

                <small>
                    <?php echo esc_html($description); ?>
                </small>

            </div>

        </a>

        <?php
    }

    /**
     * Render status counts.
     */
    private function renderStatusList(
        array $counts,
        array $statuses
    ): void {
        ?>

        <ul class="bsbm-status-list">

            <?php foreach ($statuses as $status) : ?>

                <li>

                    <span>
                        <?php echo esc_html($this->formatStatus($status)); ?>
                    </span>

                    <strong>
                        <?php echo esc_html($counts[$status] ?? 0); ?>
                    </strong>

                </li>

            <?php endforeach; ?>

        </ul>

        <?php
    }

    /**
     * Render recent enquiries table.
     */
    private function renderRecentEnquiries(array $enquiries): void
    {
        if (empty($enquiries)) {
            $this->renderEmptyState(
                __('No enquiries have been recorded yet.', 'blue-sheep-business-manager')
            );
            return;
        }
        ?>

        <table class="widefat striped bsbm-dashboard-table">

            <thead>
                <tr>
                    <th><?php esc_html_e('Customer', 'blue-sheep-business-manager'); ?></th>
                    <th><?php esc_html_e('Enquiry Type', 'blue-sheep-business-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'blue-sheep-business-manager'); ?></th>
                    <th><?php esc_html_e('Date Received', 'blue-sheep-business-manager'); ?></th>
                    <th><?php esc_html_e('Action', 'blue-sheep-business-manager'); ?></th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($enquiries as $enquiry) : ?>

                    <tr>

                        <td>
                            <strong>
                                <?php echo esc_html($enquiry['full_name'] ?? 'Unknown Customer'); ?>
                            </strong>

                            <br>

                            <small>
                                <?php echo esc_html($enquiry['email'] ?? ''); ?>
                            </small>
                        </td>

                        <td>
                            <?php echo esc_html($enquiry['enquiry_type']); ?>
                        </td>

                        <td>
                            <?php $this->renderStatusBadge($enquiry['status']); ?>
                        </td>

                        <td>
                            <?php echo esc_html($this->formatDate($enquiry['created_at'])); ?>
                        </td>

                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=bsbm-enquiries&view=' . absint($enquiry['id']))); ?>">
                                <?php esc_html_e('View Details', 'blue-sheep-business-manager'); ?>
                            </a>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <?php
    }

    /**
     * Render recent quotations table.
     */
    private function renderRecentQuotations(array $quotations): void
    {
        if (empty($quotations)) {
            $this->renderEmptyState(
                __('No quotations have been created yet.', 'blue-sheep-business-manager')
            );
            return;
        }
        ?>

        <table class="widefat striped bsbm-dashboard-table">

            <thead>
                <tr>
                    <th><?php esc_html_e('Reference', 'blue-sheep-business-manager'); ?></th>
                    <th><?php esc_html_e('Customer', 'blue-sheep-business-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'blue-sheep-business-manager'); ?></th>
                    <th><?php esc_html_e('Last Updated', 'blue-sheep-business-manager'); ?></th>
                    <th><?php esc_html_e('Action', 'blue-sheep-business-manager'); ?></th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($quotations as $quotation) : ?>

                    <tr>

                        <td>
                            <strong>
                                <?php echo esc_html($quotation['reference_number']); ?>
                            </strong>
                        </td>

                        <td>
                            <?php echo esc_html($quotation['full_name'] ?? 'Unknown Customer'); ?>
                        </td>

                        <td>
                            <?php $this->renderStatusBadge($quotation['status']); ?>
                        </td>

                        <td>
                            <?php echo esc_html($this->formatDate($quotation['updated_at'])); ?>
                        </td>

                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=bsbm-quotations&view=' . absint($quotation['id']))); ?>">
                                <?php esc_html_e('View Details', 'blue-sheep-business-manager'); ?>
                            </a>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <?php
    }

    /**
     * Render recent bookings table.
     */
    private function renderRecentBookings(array $bookings): void
    {
        if (empty($bookings)) {
            $this->renderEmptyState(
                __('No bookings have been created yet.', 'blue-sheep-business-manager')
            );
            return;
        }
        ?>

        <table class="widefat striped bsbm-dashboard-table">

            <thead>
                <tr>
                    <th><?php esc_html_e('Booking Reference', 'blue-sheep-business-manager'); ?></th>
                    <th><?php esc_html_e('Customer', 'blue-sheep-business-manager'); ?></th>
                    <th><?php esc_html_e('Travel Dates', 'blue-sheep-business-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'blue-sheep-business-manager'); ?></th>
                    <th><?php esc_html_e('Action', 'blue-sheep-business-manager'); ?></th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($bookings as $booking) : ?>

                    <tr>

                        <td>
                            <strong>
                                <?php echo esc_html($booking['booking_reference']); ?>
                            </strong>
                        </td>

                        <td>
                            <?php echo esc_html($booking['full_name'] ?? 'Unknown Customer'); ?>
                        </td>

                        <td>
                            <?php
                            echo esc_html(
                                $this->formatDateOnly($booking['travel_start_date'])
                                . ' - '
                                . $this->formatDateOnly($booking['travel_end_date'])
                            );
                            ?>
                        </td>

                        <td>
                            <?php $this->renderStatusBadge($booking['status']); ?>
                        </td>

                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=bsbm-bookings&view=' . absint($booking['id']))); ?>">
                                <?php esc_html_e('View Details', 'blue-sheep-business-manager'); ?>
                            </a>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <?php
    }

    /**
     * Render an empty-state message.
     */
    private function renderEmptyState(string $message): void
    {
        ?>

        <div class="bsbm-dashboard-empty">
            <?php echo esc_html($message); ?>
        </div>

        <?php
    }

    /**
     * Render a status badge.
     */
    private function renderStatusBadge(string $status): void
    {
        $class = sanitize_html_class('bsbm-status-' . $status);
        ?>

        <span class="bsbm-status-badge <?php echo esc_attr($class); ?>">
            <?php echo esc_html($this->formatStatus($status)); ?>
        </span>

        <?php
    }

    /**
     * Format a status identifier for display.
     */
    private function formatStatus(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    /**
     * Format a database datetime using the WordPress timezone.
     */
    private function formatDate(string $date): string
    {
        if ($date === '') {
            return '—';
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

    /**
     * Format a date-only database value.
     */
    private function formatDateOnly(string $date): string
    {
        if ($date === '') {
            return '—';
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return $date;
        }

        return wp_date(get_option('date_format'), $timestamp);
    }

    /**
     * Render dashboard styling.
     */
    private function renderStyles(): void
    {
        ?>

        <style>
            .bsbm-dashboard {
                max-width: 1500px;
            }

            .bsbm-dashboard-intro {
                color: #646970;
                margin-bottom: 25px;
            }

            .bsbm-dashboard-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 18px;
                margin: 25px 0 35px;
            }

            .bsbm-dashboard-metric {
                display: flex;
                align-items: center;
                gap: 17px;
                padding: 22px;
                min-height: 115px;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                text-decoration: none;
                color: #1d2327;
                transition: box-shadow .2s ease, transform .2s ease;
            }

            .bsbm-dashboard-metric:hover {
                color: #1d2327;
                box-shadow: 0 4px 15px rgba(0, 0, 0, .08);
                transform: translateY(-2px);
            }

            .bsbm-metric-icon {
                width: 52px;
                height: 52px;
                min-width: 52px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 12px;
            }

            .bsbm-metric-icon .dashicons {
                font-size: 27px;
                width: 27px;
                height: 27px;
            }

            .bsbm-card-blue .bsbm-metric-icon {
                background: #e7f0ff;
                color: #2271b1;
            }

            .bsbm-card-purple .bsbm-metric-icon {
                background: #f0eaff;
                color: #7652b5;
            }

            .bsbm-card-orange .bsbm-metric-icon {
                background: #fff0df;
                color: #c56a13;
            }

            .bsbm-card-green .bsbm-metric-icon {
                background: #e5f5e9;
                color: #218739;
            }

            .bsbm-metric-content {
                display: flex;
                flex-direction: column;
                gap: 5px;
                min-width: 0;
            }

            .bsbm-metric-title {
                color: #646970;
                font-size: 13px;
            }

            .bsbm-metric-content strong {
                font-size: 27px;
                line-height: 1.2;
            }

            .bsbm-metric-content small {
                color: #646970;
                font-size: 12px;
            }

            .bsbm-dashboard-section {
                margin: 30px 0;
            }

            .bsbm-dashboard-section > h2,
            .bsbm-dashboard-section-heading h2 {
                font-size: 18px;
                margin: 0;
            }

            .bsbm-dashboard-overview-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 18px;
                margin-top: 17px;
            }

            .bsbm-dashboard-panel {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                padding: 20px;
            }

            .bsbm-dashboard-panel h3 {
                font-size: 15px;
                margin: 0 0 15px;
            }

            .bsbm-status-list {
                margin: 0 0 15px;
            }

            .bsbm-status-list li {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 9px 0;
                border-bottom: 1px solid #f0f0f1;
                gap: 12px;
                font-size: 13px;
            }

            .bsbm-status-list li:last-child {
                border-bottom: 0;
            }

            .bsbm-status-list strong {
                font-size: 13px;
            }

            .bsbm-dashboard-panel > a {
                display: inline-block;
                margin-top: 8px;
            }

            .bsbm-dashboard-pending-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 15px;
                margin-top: 17px;
            }

            .bsbm-pending-card {
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 18px;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                text-decoration: none;
                color: #1d2327;
            }

            .bsbm-pending-card:hover {
                border-color: #2271b1;
                color: #2271b1;
            }

            .bsbm-pending-card > .dashicons {
                font-size: 25px;
                width: 25px;
                height: 25px;
                color: #2271b1;
            }

            .bsbm-pending-card > span:last-child {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }

            .bsbm-pending-card strong {
                font-size: 21px;
            }

            .bsbm-pending-card small {
                color: #646970;
            }

            .bsbm-dashboard-section-heading {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 15px;
            }

            .bsbm-dashboard-table {
                border: 1px solid #dcdcde;
                border-radius: 5px;
                overflow: hidden;
            }

            .bsbm-dashboard-table th {
                font-weight: 600;
            }

            .bsbm-dashboard-table td,
            .bsbm-dashboard-table th {
                padding: 12px;
                vertical-align: middle;
            }

            .bsbm-dashboard-table small {
                color: #646970;
            }

            .bsbm-status-badge {
                display: inline-block;
                padding: 4px 9px;
                border-radius: 20px;
                background: #f0f0f1;
                color: #50575e;
                font-size: 11px;
                font-weight: 600;
                white-space: nowrap;
            }

            .bsbm-status-new,
            .bsbm-status-draft,
            .bsbm-status-pending_confirmation {
                background: #fff0d6;
                color: #875900;
            }

            .bsbm-status-quoted,
            .bsbm-status-sent,
            .bsbm-status-confirmed,
            .bsbm-status-in_quotation {
                background: #e5f0ff;
                color: #135e96;
            }

            .bsbm-status-accepted,
            .bsbm-status-completed,
            .bsbm-status-converted,
            .bsbm-status-closed {
                background: #e5f5e9;
                color: #176b2c;
            }

            .bsbm-status-declined,
            .bsbm-status-cancelled,
            .bsbm-status-expired {
                background: #fbeaea;
                color: #b32d2e;
            }

            .bsbm-dashboard-empty {
                padding: 25px;
                background: #fff;
                border: 1px solid #dcdcde;
                color: #646970;
                text-align: center;
                border-radius: 5px;
            }

            @media screen and (max-width: 1200px) {
                .bsbm-dashboard-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .bsbm-dashboard-overview-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media screen and (max-width: 782px) {
                .bsbm-dashboard-grid,
                .bsbm-dashboard-overview-grid,
                .bsbm-dashboard-pending-grid {
                    grid-template-columns: 1fr;
                }

                .bsbm-dashboard-table {
                    display: block;
                    overflow-x: auto;
                }

                .bsbm-dashboard-section-heading {
                    align-items: flex-start;
                    gap: 10px;
                }
            }
        </style>

        <?php
    }
}
