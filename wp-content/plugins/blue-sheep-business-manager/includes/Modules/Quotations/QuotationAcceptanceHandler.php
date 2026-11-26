<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles administrator-recorded quotation acceptance requests.
 */
class QuotationAcceptanceHandler
{
    /**
     * Nonce action used by the acceptance form.
     */
    public const NONCE_ACTION = 'bsbm_record_quotation_acceptance';

    /**
     * Acceptance service.
     *
     * @var QuotationAcceptanceService
     */
    private $acceptance_service;

    /**
     * Constructor.
     *
     * @param QuotationAcceptanceService|null $acceptance_service Acceptance service.
     */
    public function __construct(
        ?QuotationAcceptanceService $acceptance_service = null
    ) {
        $this->acceptance_service = $acceptance_service
            ?: new QuotationAcceptanceService();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    public function register(): void
    {
        add_action(
            'admin_post_bsbm_record_quotation_acceptance',
            [$this, 'handleAcceptance']
        );
    }

    /**
     * Process an administrator-recorded acceptance request.
     *
     * @return void
     */
    public function handleAcceptance(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have permission to accept quotations.', 'blue-sheep-business-manager'),
                '',
                ['response' => 403]
            );
        }

        if (
            !isset($_SERVER['REQUEST_METHOD']) ||
            strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) !== 'POST'
        ) {
            $this->redirect(0, 'invalid_request');
        }

        check_admin_referer(self::NONCE_ACTION);

        $quotation_id = isset($_POST['quotation_id'])
            ? absint(wp_unslash($_POST['quotation_id']))
            : 0;

        $version_id = isset($_POST['version_id'])
            ? absint(wp_unslash($_POST['version_id']))
            : 0;

        $reason = isset($_POST['acceptance_reason'])
            ? sanitize_textarea_field(wp_unslash($_POST['acceptance_reason']))
            : '';

        if (!$quotation_id || !$version_id || $reason === '') {
            $this->redirect($quotation_id, 'invalid_request');
        }

        $result = $this->acceptance_service->recordAdministratorAcceptance(
            $quotation_id,
            $version_id,
            $reason
        );

        if (is_wp_error($result)) {
            $this->redirect($quotation_id, 'failed');
        }

        $this->redirect($quotation_id, 'accepted');
    }

    /**
     * Redirect to the quotation details page.
     *
     * @param int    $quotation_id Quotation ID.
     * @param string $result       Operation result.
     *
     * @return void
     */
    private function redirect(int $quotation_id, string $result): void
    {
        $url = add_query_arg(
            [
                'page'              => 'bsbm-quotations',
                'view'              => $quotation_id,
                'acceptance_result' => sanitize_key($result),
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
