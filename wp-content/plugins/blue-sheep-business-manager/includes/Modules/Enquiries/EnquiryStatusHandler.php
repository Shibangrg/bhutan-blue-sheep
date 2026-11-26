<?php

namespace BlueSheep\BusinessManager\Modules\Enquiries;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles secure enquiry status update requests.
 */
final class EnquiryStatusHandler
{
    /**
     * Status service instance.
     */
    private EnquiryStatusService $status_service;

    /**
     * Initialize the handler.
     */
    public function __construct(?EnquiryStatusService $status_service = null)
    {
        $this->status_service = $status_service ?? new EnquiryStatusService();
    }

    /**
     * Register the status update action.
     */
    public function register(): void
    {
        add_action(
            'admin_post_bsbm_update_enquiry_status',
            [$this, 'handle']
        );
    }

    /**
     * Process a status update request.
     */
    public function handle(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have permission to update enquiries.', 'blue-sheep-business-manager'),
                '',
                ['response' => 403]
            );
        }

        check_admin_referer(
            'bsbm_update_enquiry_status',
            'bsbm_status_nonce'
        );

        $enquiry_id = isset($_POST['enquiry_id'])
            ? absint($_POST['enquiry_id'])
            : 0;

        $new_status = isset($_POST['new_status'])
            ? sanitize_key(wp_unslash($_POST['new_status']))
            : '';

        $reason = isset($_POST['reason'])
            ? sanitize_textarea_field(wp_unslash($_POST['reason']))
            : '';

        if ($enquiry_id <= 0 || $new_status === '') {
            $this->redirect($enquiry_id, 'invalid_request');
        }

        $result = $this->status_service->updateStatus(
            $enquiry_id,
            $new_status,
            $reason
        );

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();

            $this->redirect(
                $enquiry_id,
                $error_code === 'transition_not_allowed'
                    ? 'transition_not_allowed'
                    : 'update_failed'
            );
        }

        $this->redirect($enquiry_id, 'status_updated');
    }

    /**
     * Redirect to the enquiry detail page.
     *
     * @param int    $enquiry_id Enquiry ID.
     * @param string $result     Result identifier.
     */
    private function redirect(int $enquiry_id, string $result): void
    {
        $url = add_query_arg(
            [
                'page' => 'bsbm-enquiries',
                'view' => $enquiry_id,
                'status_result' => $result,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
