<?php

namespace BlueSheep\BusinessManager\Enquiry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles public enquiry form submissions.
 *
 * Validates submissions and persists customer and enquiry
 * records through EnquiryPersistenceService.
 *
 * Email notifications remain disabled.
 */
final class EnquirySubmissionHandler
{
    /**
     * Register public and authenticated submission endpoints.
     */
    public function register(): void
    {
        add_action(
            'admin_post_nopriv_bsbm_submit_enquiry',
            [$this, 'handle']
        );

        add_action(
            'admin_post_bsbm_submit_enquiry',
            [$this, 'handle']
        );
    }

    /**
     * Process an enquiry form submission.
     */
    public function handle(): void
    {
        // Accept POST requests only.
        if (
            !isset($_SERVER['REQUEST_METHOD']) ||
            strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST'
        ) {
            $this->redirect('invalid');
        }

        // Verify the WordPress security nonce.
        $nonce = isset($_POST['bsbm_enquiry_nonce'])
            && is_string($_POST['bsbm_enquiry_nonce'])
            ? sanitize_text_field(
                wp_unslash($_POST['bsbm_enquiry_nonce'])
            )
            : '';

        if (
            $nonce === '' ||
            !wp_verify_nonce($nonce, 'bsbm_submit_enquiry')
        ) {
            $this->redirect('expired');
        }

        // Apply rate limiting after nonce verification.
        $rate_limiter = new EnquiryRateLimiter();

        if ($rate_limiter->isLimited()) {
            $this->redirect('rate_limited');
        }

        // Count this nonce-valid submission attempt.
        $rate_limiter->recordAttempt();

        // Check the form submission timestamp.
        $started_at = $_POST['form_started_at'] ?? '';

        if (
            !is_scalar($started_at) ||
            !ctype_digit((string) $started_at)
        ) {
            $this->redirect('invalid');
        }

        $started_at = (int) $started_at;

        if (
            $started_at <= 0 ||
            $started_at > time() ||
            $started_at < (time() - DAY_IN_SECONDS)
        ) {
            $this->redirect('expired');
        }

        // Reject honeypot submissions.
        $honeypot = $_POST['website'] ?? '';

        if (
            is_scalar($honeypot) &&
            trim((string) $honeypot) !== ''
        ) {
            $this->redirect('spam');
        }

        // Validate all submitted customer and enquiry fields.
        $validator = new EnquiryValidator();
        $result = $validator->validate($_POST);

        if (!$result['valid']) {
            $this->redirect('invalid');
        }

        // Persist only validated and sanitized data.
        $persistence_service = new EnquiryPersistenceService();
        $save_result = $persistence_service->save($result['data']);

        if (!$save_result['success']) {
            $this->redirect('save_failed');
        }

        /*
         * Both customer and enquiry records have been committed.
         *
         * Email notifications are intentionally disabled.
         */
        $this->redirect('success');
    }

    /**
     * Redirect to the Contact Us page with a controlled status.
     */
    private function redirect(string $status): void
    {
        $allowed_statuses = [
            'invalid',
            'expired',
            'spam',
            'rate_limited',
            'save_failed',
            'success',
        ];

        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'invalid';
        }

        $contact_page = get_page_by_path(
            'contact-us',
            OBJECT,
            'page'
        );

        $destination = $contact_page instanceof \WP_Post
            ? get_permalink($contact_page)
            : home_url('/contact-us/');

        $redirect_url = add_query_arg(
            'bsbm_enquiry',
            $status,
            $destination
        );

        wp_safe_redirect($redirect_url);
        exit;
    }
}
