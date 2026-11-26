<?php

namespace BlueSheep\BusinessManager\Admin;

use BlueSheep\BusinessManager\Modules\Enquiries\EnquiryService;
use BlueSheep\BusinessManager\Modules\Enquiries\EnquiryStatusService;
use BlueSheep\BusinessManager\Modules\Enquiries\EnquiryActivityRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the Business Manager enquiries administration page.
 */
final class EnquiriesPage
{
    /**
     * Enquiry service instance.
     */
    private EnquiryService $enquiry_service;

    /**
     * Enquiry status service instance.
     */
    private EnquiryStatusService $status_service;

    /**
     * Enquiry activity repository instance.
     */
    private EnquiryActivityRepository $activity_repository;

    /**
     * Initialize the enquiries page.
     */
    public function __construct(
        ?EnquiryService $enquiry_service = null,
        ?EnquiryStatusService $status_service = null,
        ?EnquiryActivityRepository $activity_repository = null
    ) {
        $this->enquiry_service = $enquiry_service ?? new EnquiryService();
        $this->status_service = $status_service ?? new EnquiryStatusService();
        $this->activity_repository = $activity_repository ?? new EnquiryActivityRepository();
    }

    
    /**
     * Render the enquiries administration page.
     */
    public function render(): void{
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'You do not have permission to access this page.',
                    'blue-sheep-business-manager'
                )
            );
        }

        if (isset($_GET['view'])) {
            $this->renderDetails(absint($_GET['view']));
            return;
        }

        $search = isset($_GET['enquiry_search'])
            ? sanitize_text_field(wp_unslash($_GET['enquiry_search']))
            : '';

        $current_page = isset($_GET['paged'])
            ? max(1, absint($_GET['paged']))
            : 1;

        $closed_page = isset($_GET['closed_paged'])
            ? max(1, absint($_GET['closed_paged']))
            : 1;

        $per_page = 20;

        $active_result = $this->enquiry_service->getEnquiries(
            $current_page,
            $per_page,
            $search,
            'not_closed'
        );

        $closed_result = $this->enquiry_service->getEnquiries(
            $closed_page,
            $per_page,
            $search,
            'closed'
        );
        ?>

        <div class="wrap bsbm-enquiries-page">

            <h1>
                <?php echo esc_html__('Enquiries', 'blue-sheep-business-manager'); ?>
            </h1>

            <p>
                <?php
                echo esc_html__(
                    'Review and manage customer enquiries submitted through your website.',
                    'blue-sheep-business-manager'
                );
                ?>
            </p>

            <hr class="wp-header-end">

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">

                <input type="hidden" name="page" value="bsbm-enquiries">

                <p class="search-box">

                    <label
                        class="screen-reader-text"
                        for="bsbm-enquiry-search"
                    >
                        <?php echo esc_html__('Search enquiries', 'blue-sheep-business-manager'); ?>
                    </label>

                    <input
                        type="search"
                        id="bsbm-enquiry-search"
                        name="enquiry_search"
                        value="<?php echo esc_attr($search); ?>"
                        placeholder="<?php echo esc_attr__('Search enquiries...', 'blue-sheep-business-manager'); ?>"
                    >

                    <input
                        type="submit"
                        class="button"
                        value="<?php echo esc_attr__('Search Enquiries', 'blue-sheep-business-manager'); ?>"
                    >

                </p>

            </form>

            <h2>
                <?php echo esc_html__('Active Enquiries', 'blue-sheep-business-manager'); ?>
            </h2>

            <p>
                <?php
                printf(
                    esc_html__('Total active enquiries: %d', 'blue-sheep-business-manager'),
                    (int) $active_result['total']
                );
                ?>
            </p>

            <?php
            $this->renderEnquiryTable($active_result['enquiries']);

            $this->renderEnquiryPagination(
                $current_page,
                $active_result['total_pages'],
                $search,
                'paged'
            );
            ?>

            <hr>

            <h2>
                <?php echo esc_html__('Closed Enquiries', 'blue-sheep-business-manager'); ?>
            </h2>

            <p>
                <?php
                printf(
                    esc_html__('Total closed enquiries: %d', 'blue-sheep-business-manager'),
                    (int) $closed_result['total']
                );
                ?>
            </p>

            <?php
            $this->renderEnquiryTable($closed_result['enquiries']);

            $this->renderEnquiryPagination(
                $closed_page,
                $closed_result['total_pages'],
                $search,
                'closed_paged'
            );
            ?>

        </div>

        <?php
    }


    /**
     * Render an enquiry table.
     *
     * @param array $enquiries Enquiry records.
     */
    private function renderEnquiryTable(array $enquiries): void
    {
        ?>

        <table class="wp-list-table widefat fixed striped">

            <thead>
                <tr>
                    <th><?php echo esc_html__('Customer', 'blue-sheep-business-manager'); ?></th>
                    <th><?php echo esc_html__('Enquiry Type', 'blue-sheep-business-manager'); ?></th>
                    <th><?php echo esc_html__('Travel Dates', 'blue-sheep-business-manager'); ?></th>
                    <th><?php echo esc_html__('Travellers', 'blue-sheep-business-manager'); ?></th>
                    <th><?php echo esc_html__('Status', 'blue-sheep-business-manager'); ?></th>
                    <th><?php echo esc_html__('Date Received', 'blue-sheep-business-manager'); ?></th>
                    <th><?php echo esc_html__('Action', 'blue-sheep-business-manager'); ?></th>
                </tr>
            </thead>

            <tbody>

                <?php if (!empty($enquiries)) : ?>

                    <?php foreach ($enquiries as $enquiry) : ?>

                        <tr>
                            <td>
                                <strong>
                                    <?php echo esc_html($enquiry['customer_name'] ?? ''); ?>
                                </strong>
                                <br>
                                <?php echo esc_html($enquiry['customer_email'] ?? ''); ?>
                            </td>

                            <td>
                                <?php
                                $enquiry_types = [
                                    'custom_trip' => __('Custom Trip', 'blue-sheep-business-manager'),
                                    'package_reservation' => __('Package Reservation', 'blue-sheep-business-manager'),
                                    'general' => __('General Enquiry', 'blue-sheep-business-manager'),
                                ];

                                $type = $enquiry['enquiry_type'] ?? '';

                                echo esc_html($enquiry_types[$type] ?? $type);
                                ?>
                            </td>

                            <td>
                                <?php
                                $start_date = $enquiry['preferred_start_date'] ?? '';
                                $end_date = $enquiry['preferred_end_date'] ?? '';

                                if ($start_date) {
                                    $timestamp = strtotime($start_date);

                                    echo $timestamp
                                        ? esc_html(wp_date(get_option('date_format'), $timestamp))
                                        : esc_html__('Invalid date', 'blue-sheep-business-manager');
                                }

                                if ($start_date && $end_date) {
                                    echo ' - ';
                                }

                                if ($end_date) {
                                    $timestamp = strtotime($end_date);

                                    echo $timestamp
                                        ? esc_html(wp_date(get_option('date_format'), $timestamp))
                                        : esc_html__('Invalid date', 'blue-sheep-business-manager');
                                }

                                if (!$start_date && !$end_date) {
                                    echo esc_html__('Not specified', 'blue-sheep-business-manager');
                                }

                                if (!empty($enquiry['dates_flexible'])) {
                                    echo '<br><small>';
                                    echo esc_html__('Flexible dates', 'blue-sheep-business-manager');
                                    echo '</small>';
                                }
                                ?>
                            </td>

                            <td>
                                <?php
                                echo isset($enquiry['traveller_count'])
                                    && $enquiry['traveller_count'] !== null
                                    ? esc_html((string) absint($enquiry['traveller_count']))
                                    : esc_html__('Not specified', 'blue-sheep-business-manager');
                                ?>
                            </td>

                            <td>
                                <?php echo esc_html($this->formatStatus($enquiry['status'] ?? '')); ?>
                            </td>

                            <td>
                                <?php
                                if (!empty($enquiry['created_at'])) {
                                    $timestamp = strtotime($enquiry['created_at']);

                                    echo $timestamp
                                        ? esc_html(wp_date(get_option('date_format'), $timestamp))
                                        : esc_html__('Unavailable', 'blue-sheep-business-manager');
                                } else {
                                    echo esc_html__('Unavailable', 'blue-sheep-business-manager');
                                }
                                ?>
                            </td>

                            <td>
                                <a
                                    class="button button-small"
                                    href="<?php
                                    echo esc_url(
                                        add_query_arg(
                                            [
                                                'page' => 'bsbm-enquiries',
                                                'view' => absint($enquiry['id']),
                                            ],
                                            admin_url('admin.php')
                                        )
                                    );
                                    ?>"
                                >
                                    <?php echo esc_html__('View Details', 'blue-sheep-business-manager'); ?>
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else : ?>

                    <tr>
                        <td colspan="7">
                            <?php
                            echo esc_html__(
                                'No enquiries found in this section.',
                                'blue-sheep-business-manager'
                            );
                            ?>
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

        <?php
    }

    /**
     * Render pagination for an enquiry table.
     *
     * @param int    $current_page Current page.
     * @param int    $total_pages  Total pages.
     * @param string $search      Search term.
     * @param string $page_key    Pagination query parameter.
     */
    private function renderEnquiryPagination(
        int $current_page,
        int $total_pages,
        string $search,
        string $page_key
    ): void {
        if ($total_pages <= 1) {
            return;
        }

        $base_url = add_query_arg(
            [
                'page' => 'bsbm-enquiries',
                $page_key => '%#%',
                'enquiry_search' => $search,
            ],
            admin_url('admin.php')
        );

        ?>

        <div class="tablenav">
            <div class="tablenav-pages">

                <?php
                echo wp_kses_post(
                    paginate_links([
                        'base' => $base_url,
                        'format' => '',
                        'current' => $current_page,
                        'total' => $total_pages,
                        'prev_text' => __('&laquo; Previous', 'blue-sheep-business-manager'),
                        'next_text' => __('Next &raquo;', 'blue-sheep-business-manager'),
                    ])
                );
                ?>

            </div>
        </div>

        <?php
    }


    /**
     * Render the details of an individual enquiry.
     *
     * @param int $enquiry_id Enquiry ID.
     */
    private function renderDetails(int $enquiry_id): void
    {
        $enquiry = $this->enquiry_service->getEnquiry($enquiry_id);

        if (!$enquiry) {
            wp_die(
                esc_html__('The requested enquiry could not be found.', 'blue-sheep-business-manager')
            );
        }

        $enquiry_types = [
            'custom_trip' => __('Custom Trip', 'blue-sheep-business-manager'),
            'package_reservation' => __('Package Reservation', 'blue-sheep-business-manager'),
            'general' => __('General Enquiry', 'blue-sheep-business-manager'),
        ];

        $type = $enquiry['enquiry_type'] ?? '';
        $current_status = $enquiry['status'] ?? '';

        $available_transitions = $this->status_service->getAvailableTransitions(
            $current_status
        );

        $activities = $this->activity_repository->getByEnquiryId($enquiry_id);

        $status_messages = [
            'status_updated' => [
                'type' => 'success',
                'message' => __('Enquiry status updated successfully.', 'blue-sheep-business-manager'),
            ],
            'invalid_request' => [
                'type' => 'error',
                'message' => __('The status update request was invalid.', 'blue-sheep-business-manager'),
            ],
            'transition_not_allowed' => [
                'type' => 'error',
                'message' => __('This status transition is not permitted.', 'blue-sheep-business-manager'),
            ],
            'update_failed' => [
                'type' => 'error',
                'message' => __('The enquiry status could not be updated. Please try again.', 'blue-sheep-business-manager'),
            ],
        ];

        $status_result = isset($_GET['status_result'])
            ? sanitize_key(wp_unslash($_GET['status_result']))
            : '';

        ?>

        <div class="wrap bsbm-enquiry-details">

            <h1>
                <?php
                printf(
                    esc_html__('Enquiry #%d', 'blue-sheep-business-manager'),
                    (int) $enquiry['id']
                );
                ?>
            </h1>

            <p>
                <a
                    href="<?php echo esc_url(admin_url('admin.php?page=bsbm-enquiries')); ?>"
                    class="button"
                >
                    <?php echo esc_html__('Back to Enquiries', 'blue-sheep-business-manager'); ?>
                </a>
            </p>

            <hr class="wp-header-end">

            <?php if (isset($status_messages[$status_result])) : ?>

                <div class="notice notice-<?php echo esc_attr($status_messages[$status_result]['type']); ?> is-dismissible">
                    <p>
                        <?php echo esc_html($status_messages[$status_result]['message']); ?>
                    </p>
                </div>

            <?php endif; ?>

            <h2><?php echo esc_html__('Customer Information', 'blue-sheep-business-manager'); ?></h2>

            <table class="form-table">
                <tbody>
                    <tr>
                        <th><?php echo esc_html__('Full Name', 'blue-sheep-business-manager'); ?></th>
                        <td><?php echo esc_html($enquiry['customer_name'] ?? 'Not provided'); ?></td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Email', 'blue-sheep-business-manager'); ?></th>
                        <td>
                            <?php if (!empty($enquiry['customer_email'])) : ?>
                                <a href="<?php echo esc_url('mailto:' . sanitize_email($enquiry['customer_email'])); ?>">
                                    <?php echo esc_html($enquiry['customer_email']); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html__('Not provided', 'blue-sheep-business-manager'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Phone', 'blue-sheep-business-manager'); ?></th>
                        <td><?php echo esc_html($enquiry['customer_phone'] ?? 'Not provided'); ?></td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Country', 'blue-sheep-business-manager'); ?></th>
                        <td><?php echo esc_html($enquiry['customer_country'] ?? 'Not provided'); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Enquiry Information', 'blue-sheep-business-manager'); ?></h2>

            <table class="form-table">
                <tbody>
                    <tr>
                        <th><?php echo esc_html__('Enquiry Type', 'blue-sheep-business-manager'); ?></th>
                        <td><?php echo esc_html($enquiry_types[$type] ?? $type); ?></td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Status', 'blue-sheep-business-manager'); ?></th>
                        <td><?php echo esc_html($this->formatStatus($current_status)); ?></td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Tour ID', 'blue-sheep-business-manager'); ?></th>
                        <td>
                            <?php
                            echo !empty($enquiry['tour_id'])
                                ? esc_html((string) absint($enquiry['tour_id']))
                                : esc_html__('Not specified', 'blue-sheep-business-manager');
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Preferred Start Date', 'blue-sheep-business-manager'); ?></th>
                        <td><?php echo esc_html($enquiry['preferred_start_date'] ?: 'Not specified'); ?></td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Preferred End Date', 'blue-sheep-business-manager'); ?></th>
                        <td><?php echo esc_html($enquiry['preferred_end_date'] ?: 'Not specified'); ?></td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Flexible Dates', 'blue-sheep-business-manager'); ?></th>
                        <td>
                            <?php
                            echo !empty($enquiry['dates_flexible'])
                                ? esc_html__('Yes', 'blue-sheep-business-manager')
                                : esc_html__('No', 'blue-sheep-business-manager');
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Total Travellers', 'blue-sheep-business-manager'); ?></th>
                        <td>
                            <?php
                            echo isset($enquiry['traveller_count']) && $enquiry['traveller_count'] !== null
                                ? esc_html((string) absint($enquiry['traveller_count']))
                                : esc_html__('Not specified', 'blue-sheep-business-manager');
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Adults', 'blue-sheep-business-manager'); ?></th>
                        <td>
                            <?php
                            echo isset($enquiry['adults_count']) && $enquiry['adults_count'] !== null
                                ? esc_html((string) absint($enquiry['adults_count']))
                                : esc_html__('Not specified', 'blue-sheep-business-manager');
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Children', 'blue-sheep-business-manager'); ?></th>
                        <td>
                            <?php
                            echo isset($enquiry['children_count']) && $enquiry['children_count'] !== null
                                ? esc_html((string) absint($enquiry['children_count']))
                                : esc_html__('Not specified', 'blue-sheep-business-manager');
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Destinations', 'blue-sheep-business-manager'); ?></th>
                        <td><?php echo $this->formatStructuredValue($enquiry['destinations'] ?? ''); ?></td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Interests', 'blue-sheep-business-manager'); ?></th>
                        <td><?php echo $this->formatStructuredValue($enquiry['interests'] ?? ''); ?></td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Budget Range', 'blue-sheep-business-manager'); ?></th>
                        <td><?php echo esc_html($enquiry['budget_range'] ?: 'Not specified'); ?></td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Additional Requirements', 'blue-sheep-business-manager'); ?></th>
                        <td>
                            <?php
                            echo !empty($enquiry['additional_requirements'])
                                ? nl2br(esc_html($enquiry['additional_requirements']))
                                : esc_html__('None provided', 'blue-sheep-business-manager');
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Date Received', 'blue-sheep-business-manager'); ?></th>
                        <td><?php echo esc_html($enquiry['created_at'] ?? 'Unavailable'); ?></td>
                    </tr>

                    <tr>
                        <th><?php echo esc_html__('Last Updated', 'blue-sheep-business-manager'); ?></th>
                        <td><?php echo esc_html($enquiry['updated_at'] ?? 'Unavailable'); ?></td>
                    </tr>
                </tbody>
            </table>

            <hr>

            <h2><?php echo esc_html__('Manage Enquiry Status', 'blue-sheep-business-manager'); ?></h2>

            <?php if (!empty($available_transitions)) : ?>

                <form
                    method="post"
                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    class="bsbm-status-form"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="bsbm_update_enquiry_status"
                    >

                    <input
                        type="hidden"
                        name="enquiry_id"
                        value="<?php echo esc_attr((string) $enquiry_id); ?>"
                    >

                    <?php
                    wp_nonce_field(
                        'bsbm_update_enquiry_status',
                        'bsbm_status_nonce'
                    );
                    ?>

                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th>
                                    <label for="bsbm-new-status">
                                        <?php echo esc_html__('New Status', 'blue-sheep-business-manager'); ?>
                                    </label>
                                </th>
                                <td>
                                    <select
                                        name="new_status"
                                        id="bsbm-new-status"
                                        required
                                    >
                                        <option value="">
                                            <?php echo esc_html__('Select a status', 'blue-sheep-business-manager'); ?>
                                        </option>

                                        <?php foreach ($available_transitions as $transition) : ?>
                                            <option value="<?php echo esc_attr($transition); ?>">
                                                <?php echo esc_html($this->formatStatus($transition)); ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    <label for="bsbm-status-reason">
                                        <?php echo esc_html__('Reason or Notes', 'blue-sheep-business-manager'); ?>
                                    </label>
                                </th>
                                <td>
                                    <textarea
                                        name="reason"
                                        id="bsbm-status-reason"
                                        rows="4"
                                        class="large-text"
                                        placeholder="<?php echo esc_attr__('Add an explanation for this status change (optional).', 'blue-sheep-business-manager'); ?>"
                                    ></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php
                    submit_button(
                        __('Update Enquiry Status', 'blue-sheep-business-manager')
                    );
                    ?>

                </form>

            <?php else : ?>

                <p>
                    <?php
                    echo esc_html__(
                        'No further status transitions are currently available for this enquiry.',
                        'blue-sheep-business-manager'
                    );
                    ?>
                </p>

            <?php endif; ?>

            <hr>

            <h2><?php echo esc_html__('Activity History', 'blue-sheep-business-manager'); ?></h2>

            <?php if (!empty($activities)) : ?>

                <table class="wp-list-table widefat fixed striped">

                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Activity', 'blue-sheep-business-manager'); ?></th>
                            <th><?php echo esc_html__('Previous Status', 'blue-sheep-business-manager'); ?></th>
                            <th><?php echo esc_html__('New Status', 'blue-sheep-business-manager'); ?></th>
                            <th><?php echo esc_html__('Reason', 'blue-sheep-business-manager'); ?></th>
                            <th><?php echo esc_html__('Performed By', 'blue-sheep-business-manager'); ?></th>
                            <th><?php echo esc_html__('Date', 'blue-sheep-business-manager'); ?></th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($activities as $activity) : ?>

                            <?php
                            $actor = !empty($activity['user_id'])
                                ? get_userdata(absint($activity['user_id']))
                                : false;

                            $actor_name = $actor
                                ? $actor->display_name
                                : __('System', 'blue-sheep-business-manager');

                            $activity_date = !empty($activity['created_at'])
                                ? strtotime($activity['created_at'])
                                : false;
                            ?>

                            <tr>
                                <td>
                                    <?php
                                    echo esc_html(
                                        $activity['description']
                                            ?: ucwords(str_replace('_', ' ', $activity['action'] ?? 'Activity'))
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($activity['previous_status'])
                                        ? esc_html($this->formatStatus($activity['previous_status']))
                                        : esc_html__('Not available', 'blue-sheep-business-manager');
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($activity['new_status'])
                                        ? esc_html($this->formatStatus($activity['new_status']))
                                        : esc_html__('Not available', 'blue-sheep-business-manager');
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($activity['reason'])
                                        ? nl2br(esc_html($activity['reason']))
                                        : esc_html__('No reason provided', 'blue-sheep-business-manager');
                                    ?>
                                </td>

                                <td><?php echo esc_html($actor_name); ?></td>

                                <td>
                                    <?php
                                    echo $activity_date
                                        ? esc_html(
                                            wp_date(
                                                get_option('date_format') . ' ' . get_option('time_format'),
                                                $activity_date
                                            )
                                        )
                                        : esc_html__('Unavailable', 'blue-sheep-business-manager');
                                    ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else : ?>

                <p>
                    <?php
                    echo esc_html__(
                        'No activity has been recorded for this enquiry yet.',
                        'blue-sheep-business-manager'
                    );
                    ?>
                </p>

            <?php endif; ?>

        </div>

        <?php
    }

    /**
     * Format a status value for display.
     *
     * @param string $status Status identifier.
     *
     * @return string
     */
    private function formatStatus(string $status): string
    {
        if ($status === '') {
            return __('Unknown', 'blue-sheep-business-manager');
        }

        return ucwords(
            str_replace(
                ['_', '-'],
                ' ',
                $status
            )
        );
    }

    /**
     * Format structured JSON values for safe display.
     *
     * @param mixed $value Stored value.
     *
     * @return string
     */
    private function formatStructuredValue($value): string
    {
        if ($value === null || $value === '') {
            return esc_html__('Not specified', 'blue-sheep-business-manager');
        }

        $decoded = json_decode((string) $value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $items = [];

            array_walk_recursive(
                $decoded,
                static function ($item) use (&$items): void {
                    if (is_scalar($item)) {
                        $items[] = (string) $item;
                    }
                }
            );

            if (empty($items)) {
                return esc_html__('No values provided', 'blue-sheep-business-manager');
            }

            return esc_html(implode(', ', $items));
        }

        return esc_html((string) $value);
    }
}
