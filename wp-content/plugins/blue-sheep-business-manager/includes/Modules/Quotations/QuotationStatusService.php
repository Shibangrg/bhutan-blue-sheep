<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

use RuntimeException;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages quotation version status transitions.
 */
final class QuotationStatusService
{
    private $wpdb;

    private string $quotation_table;
    private string $version_table;
    private string $activity_table;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->quotation_table = $wpdb->prefix . 'bs_quotations';
        $this->version_table = $wpdb->prefix . 'bs_quotation_versions';
        $this->activity_table = $wpdb->prefix . 'bs_activity_log';
    }

    /**
     * Record successful quotation delivery.
     *
     * Call only after the delivery provider confirms submission.
     */
    public function markSent(int $version_id, array $delivery)
    {
        if (!current_user_can('manage_options')) {
            return $this->error(
                'permission_denied',
                'You do not have permission to update quotations.'
            );
        }

        $provider = isset($delivery['provider'])
            ? sanitize_text_field($delivery['provider'])
            : '';

        $message_id = isset($delivery['message_id'])
            ? sanitize_text_field($delivery['message_id'])
            : '';

        if ($provider === '' || $message_id === '') {
            return $this->error(
                'delivery_confirmation_required',
                'A delivery provider and message reference are required.'
            );
        }

        return $this->transition(
            $version_id,
            'sent',
            'quotation_sent',
            'Quotation delivery submitted successfully.',
            [
                'provider' => $provider,
                'message_id' => $message_id,
            ],
            ['draft']
        );
    }

    /**
     * Record a customer change request.
     *
     * Customer-facing access must be implemented through a separate
     * authenticated or secure-token workflow.
     */
    public function requestChanges(int $version_id, string $reason)
    {
        if (!current_user_can('manage_options')) {
            return $this->error(
                'permission_denied',
                'You do not have permission to update quotations.'
            );
        }

        $reason = sanitize_textarea_field($reason);

        if ($reason === '') {
            return $this->error(
                'change_reason_required',
                'A reason for the requested changes is required.'
            );
        }

        return $this->transition(
            $version_id,
            'changes_requested',
            'quotation_changes_requested',
            $reason,
            [],
            ['sent']
        );
    }

    /**
     * Record quotation acceptance.
     */
    public function acceptQuotation(
        int $version_id,
        string $method,
        string $reference = ''
    ) {
        if (!current_user_can('manage_options')) {
            return $this->error(
                'permission_denied',
                'You do not have permission to update quotations.'
            );
        }

        $allowed_methods = [
            'secure_link',
            'email',
            'administrator_recorded',
        ];

        $method = sanitize_key($method);
        $reference = sanitize_text_field($reference);

        if (!in_array($method, $allowed_methods, true)) {
            return $this->error(
                'invalid_acceptance_method',
                'The acceptance method is invalid.'
            );
        }

        if ($reference === '') {
            return $this->error(
                'acceptance_reference_required',
                'An acceptance reference or reason is required.'
            );
        }

        return $this->transition(
            $version_id,
            'accepted',
            'quotation_accepted',
            'Quotation acceptance recorded.',
            [
                'acceptance_method' => $method,
                'acceptance_reference' => $reference,
            ],
            ['sent']
        );
    }

    /**
     * Record quotation decline.
     */
    public function declineQuotation(int $version_id, string $reason = '')
    {
        if (!current_user_can('manage_options')) {
            return $this->error(
                'permission_denied',
                'You do not have permission to update quotations.'
            );
        }

        $reason = sanitize_textarea_field($reason);

        return $this->transition(
            $version_id,
            'declined',
            'quotation_declined',
            $reason !== '' ? $reason : 'Quotation declined.',
            [],
            ['sent']
        );
    }

    /**
     * Expire a quotation after its validity date.
     */
    public function expireQuotation(int $version_id)
    {
        if (!current_user_can('manage_options')) {
            return $this->error(
                'permission_denied',
                'You do not have permission to expire quotations.'
            );
        }

        return $this->transition(
            $version_id,
            'expired',
            'quotation_expired',
            'Quotation validity period has ended.',
            [],
            ['sent'],
            true
        );
    }

    /**
     * Execute a validated status transition.
     */
    private function transition(
        int $version_id,
        string $new_status,
        string $action,
        string $reason,
        array $metadata,
        array $allowed_from,
        bool $check_expiry = false
    ) {
        if ($version_id <= 0) {
            return $this->error(
                'invalid_version',
                'A valid quotation version is required.'
            );
        }

        $transaction_started = false;

        try {
            if ($this->wpdb->query('START TRANSACTION') === false) {
                throw new RuntimeException(
                    'The quotation transaction could not be started.'
                );
            }

            $transaction_started = true;

            /*
             * Lock both the quotation and version records.
             * Locking the quotation serializes transitions across
             * different versions of the same quotation.
             */
            $version = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT
                        v.*,
                        q.status AS quotation_status,
                        q.current_version_id
                     FROM {$this->version_table} AS v
                     INNER JOIN {$this->quotation_table} AS q
                        ON q.id = v.quotation_id
                     WHERE v.id = %d
                     FOR UPDATE",
                    $version_id
                ),
                ARRAY_A
            );

            if ($this->wpdb->last_error !== '') {
                throw new RuntimeException(
                    'The quotation version could not be retrieved.'
                );
            }

            if (!is_array($version)) {
                throw new RuntimeException(
                    'The quotation version could not be found.'
                );
            }

            /*
             * Only the current version can receive status changes.
             */
            if ((int) $version['current_version_id'] !== $version_id) {
                throw new RuntimeException(
                    'Only the current quotation version can be updated.'
                );
            }

            $previous_status = $version['status'];

            if (!in_array($previous_status, $allowed_from, true)) {
                throw new RuntimeException(
                    'The requested status transition is not permitted.'
                );
            }

            /*
             * Prevent another version from being accepted after
             * an accepted version already exists.
             */
            if ($new_status === 'accepted') {
                $accepted_count = (int) $this->wpdb->get_var(
                    $this->wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$this->version_table}
                         WHERE quotation_id = %d
                         AND status = 'accepted'",
                        (int) $version['quotation_id']
                    )
                );

                if ($this->wpdb->last_error !== '') {
                    throw new RuntimeException(
                        'Existing quotation acceptance could not be verified.'
                    );
                }

                if ($accepted_count > 0) {
                    throw new RuntimeException(
                        'This quotation already has an accepted version.'
                    );
                }
            }

            /*
             * Compare date-only validity values using the WordPress
             * site timezone.
             */
            if ($check_expiry) {
                $valid_until = $version['valid_until'] ?? '';

                if (
                    $valid_until === ''
                    || $valid_until >= current_time('Y-m-d')
                ) {
                    throw new RuntimeException(
                        'The quotation has not reached its expiry date.'
                    );
                }
            }

            $update_data = [
                'status' => $new_status,
            ];

            if ($new_status === 'accepted') {
                $update_data['accepted_at'] = current_time('mysql');
                $update_data['acceptance_method'] =
                    $metadata['acceptance_method'];

                $update_data['acceptance_reference'] =
                    $metadata['acceptance_reference'];
            }

            $updated = $this->wpdb->update(
                $this->version_table,
                $update_data,
                ['id' => $version_id]
            );

            if ($updated === false) {
                throw new RuntimeException(
                    'The quotation version status could not be updated.'
                );
            }

            $quotation_update = $this->wpdb->update(
                $this->quotation_table,
                [
                    'status' => $new_status,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => (int) $version['quotation_id']]
            );

            if ($quotation_update === false) {
                throw new RuntimeException(
                    'The quotation record could not be updated.'
                );
            }

            /*
             * Use only columns defined in the approved activity log schema.
             */
            $activity_details = array_merge(
                $metadata,
                ['version_id' => $version_id]
            );

            $encoded_details = wp_json_encode($activity_details);

            if ($encoded_details === false) {
                throw new RuntimeException(
                    'Quotation activity details could not be encoded.'
                );
            }

            $description = $reason;

            if (!empty($activity_details)) {
                $description .= ' | Details: ' . $encoded_details;
            }

            $activity_inserted = $this->wpdb->insert(
                $this->activity_table,
                [
                    'user_id' => get_current_user_id(),
                    'entity_type' => 'quotation',
                    'entity_id' => (int) $version['quotation_id'],
                    'action' => $action,
                    'description' => $description,
                    'previous_status' => $previous_status,
                    'new_status' => $new_status,
                    'reason' => $reason,
                    'created_at' => current_time('mysql'),
                ]
            );

            if ($activity_inserted !== 1) {
                throw new RuntimeException(
                    'Quotation activity could not be recorded: '
                    . $this->wpdb->last_error
                );
            }

            if ($this->wpdb->query('COMMIT') === false) {
                throw new RuntimeException(
                    'The quotation transaction could not be committed.'
                );
            }

            $transaction_started = false;

            return true;

        } catch (Throwable $exception) {
            if ($transaction_started) {
                $this->wpdb->query('ROLLBACK');
            }

            error_log(
                'Blue Sheep quotation status transition failed: '
                . $exception->getMessage()
            );

            return $this->error(
                'quotation_status_update_failed',
                $exception->getMessage()
            );
        }
    }

    /**
     * Create a WordPress error.
     */
    private function error(string $code, string $message): \WP_Error
    {
        return new \WP_Error(
            $code,
            __($message, 'blue-sheep-business-manager')
        );
    }
}
