<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

use RuntimeException;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Records administrator-confirmed manual quotation dispatches.
 * This service does not send email.
 */
final class QuotationDispatchService
{
    private $wpdb;

    private string $quotation_table;
    private string $version_table;
    private string $enquiry_table;
    private string $activity_table;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->quotation_table = $wpdb->prefix . 'bs_quotations';
        $this->version_table = $wpdb->prefix . 'bs_quotation_versions';
        $this->enquiry_table = $wpdb->prefix . 'bs_enquiries';
        $this->activity_table = $wpdb->prefix . 'bs_activity_log';
    }

    /**
     * Record that an administrator has manually sent the current draft PDF.
     *
     * Updates the quotation and its associated enquiry atomically.
     *
     * @return true|\WP_Error
     */
    public function recordManualDispatch(
        int $quotation_id,
        string $recipient,
        string $notes = ''
    ) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'quotation_dispatch_permission_denied',
                __('You do not have permission to record quotation dispatches.', 'blue-sheep-business-manager')
            );
        }

        if ($quotation_id <= 0) {
            return new \WP_Error(
                'invalid_quotation',
                __('A valid quotation is required.', 'blue-sheep-business-manager')
            );
        }

        $recipient = sanitize_email($recipient);

        if (!is_email($recipient)) {
            return new \WP_Error(
                'invalid_dispatch_recipient',
                __('Enter a valid recipient email address.', 'blue-sheep-business-manager')
            );
        }

        $notes = sanitize_textarea_field($notes);

        if (strlen($notes) > 5000) {
            return new \WP_Error(
                'dispatch_notes_too_long',
                __('Dispatch notes must not exceed 5,000 characters.', 'blue-sheep-business-manager')
            );
        }

        $transaction_started = false;

        try {
            if ($this->wpdb->query('START TRANSACTION') === false) {
                throw new RuntimeException(
                    'The dispatch transaction could not be started.'
                );
            }

            $transaction_started = true;

            /*
             * Lock the quotation.
             */
            $quotation = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT id, enquiry_id, current_version_id, status
                     FROM {$this->quotation_table}
                     WHERE id = %d
                     FOR UPDATE",
                    $quotation_id
                ),
                ARRAY_A
            );

            if (!is_array($quotation)) {
                throw new RuntimeException(
                    'The requested quotation does not exist.'
                );
            }

            $version_id = (int) ($quotation['current_version_id'] ?? 0);
            $enquiry_id = (int) ($quotation['enquiry_id'] ?? 0);

            if ($version_id <= 0) {
                throw new RuntimeException(
                    'The quotation does not have a current version.'
                );
            }

            if ($enquiry_id <= 0) {
                throw new RuntimeException(
                    'The quotation does not have an associated enquiry.'
                );
            }

            /*
             * Lock and verify the current quotation version.
             */
            $version = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT id, quotation_id, version_number, status
                     FROM {$this->version_table}
                     WHERE id = %d
                       AND quotation_id = %d
                     FOR UPDATE",
                    $version_id,
                    $quotation_id
                ),
                ARRAY_A
            );

            if (!is_array($version)) {
                throw new RuntimeException(
                    'The current quotation version could not be found.'
                );
            }

            if ($version['status'] !== 'draft') {
                throw new RuntimeException(
                    'Only the current draft version can be recorded as manually sent.'
                );
            }

            /*
             * Lock and verify the associated enquiry.
             *
             * The enquiry must be in_quotation before dispatch.
             */
            $enquiry = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT id, status
                     FROM {$this->enquiry_table}
                     WHERE id = %d
                     FOR UPDATE",
                    $enquiry_id
                ),
                ARRAY_A
            );

            if (!is_array($enquiry)) {
                throw new RuntimeException(
                    'The enquiry associated with this quotation could not be found.'
                );
            }

            if ($enquiry['status'] !== 'in_quotation') {
                throw new RuntimeException(
                    'The associated enquiry must have In Quotation status before dispatch can be recorded.'
                );
            }

            $now = current_time('mysql');
            $user_id = get_current_user_id();

            $previous_quotation_status = (string) $quotation['status'];
            $previous_enquiry_status = (string) $enquiry['status'];

            /*
             * Change the current quotation version to sent.
             */
            $version_updated = $this->wpdb->update(
                $this->version_table,
                ['status' => 'sent'],
                [
                    'id' => $version_id,
                    'quotation_id' => $quotation_id,
                    'status' => 'draft',
                ],
                ['%s'],
                ['%d', '%d', '%s']
            );

            if ($version_updated !== 1) {
                throw new RuntimeException(
                    'The quotation version status could not be updated.'
                );
            }

            /*
             * Supersede previous changes-requested versions.
             */
            $superseded = $this->wpdb->update(
                $this->version_table,
                ['status' => 'superseded'],
                [
                    'quotation_id' => $quotation_id,
                    'status' => 'changes_requested',
                ],
                ['%s'],
                ['%d', '%s']
            );

            if ($superseded === false) {
                throw new RuntimeException(
                    'Previous quotation versions could not be marked as superseded.'
                );
            }

            /*
             * Update quotation master status.
             */
            $quotation_updated = $this->wpdb->update(
                $this->quotation_table,
                [
                    'status' => 'sent',
                    'updated_at' => $now,
                ],
                ['id' => $quotation_id],
                ['%s', '%s'],
                ['%d']
            );

            if ($quotation_updated !== 1) {
                throw new RuntimeException(
                    'The quotation status could not be updated.'
                );
            }

            /*
             * Update associated enquiry status.
             *
             * Only an enquiry currently in_quotation can transition
             * to quoted through this dispatch workflow.
             */
            $enquiry_updated = $this->wpdb->update(
                $this->enquiry_table,
                [
                    'status' => 'quoted',
                    'updated_at' => $now,
                ],
                [
                    'id' => $enquiry_id,
                    'status' => 'in_quotation',
                ],
                ['%s', '%s'],
                ['%d', '%s']
            );

            if ($enquiry_updated !== 1) {
                throw new RuntimeException(
                    'The enquiry status could not be updated to quoted.'
                );
            }

            /*
             * Record quotation dispatch activity.
             */
            $quotation_description = sprintf(
                'Quotation version %d (version ID %d) was manually sent to %s at %s.',
                (int) $version['version_number'],
                $version_id,
                $recipient,
                $now
            );

            if ($notes !== '') {
                $quotation_description .= ' Notes: ' . $notes;
            }

            $quotation_log = $this->wpdb->insert(
                $this->activity_table,
                [
                    'user_id' => $user_id,
                    'entity_type' => 'quotation',
                    'entity_id' => $quotation_id,
                    'action' => 'quotation_manually_dispatched',
                    'description' => $quotation_description,
                    'created_at' => $now,
                    'previous_status' => $previous_quotation_status,
                    'new_status' => 'sent',
                    'reason' => $notes !== ''
                        ? $notes
                        : 'Administrator confirmed manual quotation dispatch.',
                ],
                [
                    '%d',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($quotation_log !== 1) {
                throw new RuntimeException(
                    'The quotation dispatch activity could not be recorded.'
                );
            }

            /*
             * Record enquiry status transition activity.
             */
            $enquiry_description = sprintf(
                'Enquiry status changed from %s to quoted after quotation %d, version %d, was manually dispatched to %s.',
                $previous_enquiry_status,
                $quotation_id,
                (int) $version['version_number'],
                $recipient
            );

            $enquiry_log = $this->wpdb->insert(
                $this->activity_table,
                [
                    'user_id' => $user_id,
                    'entity_type' => 'enquiry',
                    'entity_id' => $enquiry_id,
                    'action' => 'enquiry_status_changed',
                    'description' => $enquiry_description,
                    'created_at' => $now,
                    'previous_status' => $previous_enquiry_status,
                    'new_status' => 'quoted',
                    'reason' => 'Quotation manually dispatched.',
                ],
                [
                    '%d',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($enquiry_log !== 1) {
                throw new RuntimeException(
                    'The enquiry status activity could not be recorded.'
                );
            }

            /*
             * Commit all changes together.
             */
            if ($this->wpdb->query('COMMIT') === false) {
                throw new RuntimeException(
                    'The quotation dispatch transaction could not be committed.'
                );
            }

            $transaction_started = false;

            return true;

        } catch (Throwable $exception) {
            if ($transaction_started) {
                $this->wpdb->query('ROLLBACK');
            }

            error_log(
                'Blue Sheep manual quotation dispatch failed: '
                . $exception->getMessage()
            );

            return new \WP_Error(
                'quotation_dispatch_failed',
                __(
                    'The quotation dispatch could not be recorded. Verify that the PDF was sent and try again.',
                    'blue-sheep-business-manager'
                )
            );
        }
    }
}
