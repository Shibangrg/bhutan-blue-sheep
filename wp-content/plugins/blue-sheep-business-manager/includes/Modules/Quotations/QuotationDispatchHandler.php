<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles administrator confirmation of manually dispatched quotations.
 *
 * This handler records a dispatch only. It never sends email.
 */
final class QuotationDispatchHandler
{
    public function register(): void
    {
        add_action(
            'admin_post_bsbm_record_quotation_dispatch',
            [$this, 'handle']
        );
    }

    public function handle(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'You do not have permission to record quotation dispatches.',
                    'blue-sheep-business-manager'
                ),
                '',
                ['response' => 403]
            );
        }

        $quotation_id = isset($_POST['quotation_id'])
            ? absint(wp_unslash($_POST['quotation_id']))
            : 0;

        $nonce = isset($_POST['bsbm_dispatch_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['bsbm_dispatch_nonce']))
            : '';

        if (
            $quotation_id <= 0
            || !wp_verify_nonce($nonce, 'bsbm_record_quotation_dispatch_' . $quotation_id)
        ) {
            $this->redirectWithResult($quotation_id, 'invalid_request');
        }

        // Enforce the administrator's confirmation on the server side.
        // The HTML required attribute is only a client-side convenience.
        $confirm_sent = isset($_POST['confirm_sent'])
            ? sanitize_text_field(wp_unslash($_POST['confirm_sent']))
            : '';

        if ($confirm_sent !== '1') {
            $this->redirectWithResult($quotation_id, 'invalid_request');
        }

        $recipient = isset($_POST['recipient'])
            ? sanitize_email(wp_unslash($_POST['recipient']))
            : '';

        $notes = isset($_POST['dispatch_notes'])
            ? sanitize_textarea_field(wp_unslash($_POST['dispatch_notes']))
            : '';

        $service = new QuotationDispatchService();

        $result = $service->recordManualDispatch(
            $quotation_id,
            $recipient,
            $notes
        );

        if (is_wp_error($result)) {
            error_log(
                'Blue Sheep quotation dispatch handler: '
                . $result->get_error_code()
            );

            $this->redirectWithResult($quotation_id, 'failed');
        }

        $this->redirectWithResult($quotation_id, 'recorded');
    }

    private function redirectWithResult(
        int $quotation_id,
        string $result
    ): void {
        $url = add_query_arg(
            [
                'page' => 'bsbm-quotations',
                'view' => $quotation_id,
                'dispatch_result' => $result,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
