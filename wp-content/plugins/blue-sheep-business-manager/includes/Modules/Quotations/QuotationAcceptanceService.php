<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles quotation acceptance operations.
 *
 * The initial implementation supports administrator-recorded acceptance.
 * Customer-facing acceptance methods must validate their evidence before
 * being added to this service.
 */
class QuotationAcceptanceService
{
    /**
     * Record acceptance manually as an administrator.
     *
     * Only the current quotation version can be accepted.
     * Acceptance is processed inside a database transaction.
     *
     * @param int    $quotation_id Quotation ID.
     * @param int    $version_id   Quotation version ID.
     * @param string $reason       Reason and acceptance evidence.
     *
     * @return bool|\WP_Error
     */
    public function recordAdministratorAcceptance(
        int $quotation_id,
        int $version_id,
        string $reason
    ) {
        global $wpdb;

        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'permission_denied',
                'Only an administrator can record quotation acceptance.'
            );
        }

        $quotation_id = absint($quotation_id);
        $version_id   = absint($version_id);
        $reason       = sanitize_textarea_field($reason);

        if (!$quotation_id || !$version_id) {
            return new \WP_Error(
                'invalid_quotation',
                'A valid quotation and quotation version are required.'
            );
        }

        if ($reason === '') {
            return new \WP_Error(
                'acceptance_reason_required',
                'A reason or acceptance evidence is required.'
            );
        }

        $quotations_table = $wpdb->prefix . 'bs_quotations';
        $versions_table   = $wpdb->prefix . 'bs_quotation_versions';
        $activity_table   = $wpdb->prefix . 'bs_activity_log';

        $admin_id = get_current_user_id();
        $now      = current_time('mysql', true);

        /*
         * Start transaction.
         */
        $started = $wpdb->query('START TRANSACTION');

        if ($started === false) {
            return new \WP_Error(
                'transaction_failed',
                'Unable to start the acceptance transaction.'
            );
        }

        try {
            /*
             * Lock the parent quotation first.
             * This serializes acceptance attempts for the same quotation.
             */
            $quotation = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, current_version_id, status
                    FROM {$quotations_table}
                    WHERE id = %d
                    FOR UPDATE",
                    $quotation_id
                )
            );

            if (!$quotation) {
                throw new \RuntimeException(
                    'The requested quotation could not be found.'
                );
            }

            /*
             * Ensure the requested version belongs to this quotation.
             */
            $version = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, quotation_id, status, valid_until,
                            accepted_at, acceptance_method
                    FROM {$versions_table}
                    WHERE id = %d
                    AND quotation_id = %d
                    FOR UPDATE",
                    $version_id,
                    $quotation_id
                )
            );

            if (!$version) {
                throw new \RuntimeException(
                    'The requested quotation version does not belong to this quotation.'
                );
            }

            /*
             * Only the current version may be accepted.
             */
            if ((int) $quotation->current_version_id !== $version_id) {
                throw new \RuntimeException(
                    'Only the current quotation version can be accepted.'
                );
            }

            /*
             * Acceptance is permitted only from the sent state.
             */
            if ($version->status !== 'sent') {
                throw new \RuntimeException(
                    'Only a sent quotation version can be accepted.'
                );
            }

            /*
             * Check whether the quotation already has an accepted version.
             */
            $existing_accepted = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                    FROM {$versions_table}
                    WHERE quotation_id = %d
                    AND status = %s
                    LIMIT 1",
                    $quotation_id,
                    'accepted'
                )
            );

            if ($existing_accepted) {
                throw new \RuntimeException(
                    'This quotation already has an accepted version.'
                );
            }

            /*
             * Reject acceptance after the validity period.
             */
            if (!empty($version->valid_until)) {
                $expiry_timestamp = strtotime(
                    $version->valid_until . ' UTC'
                );

                if (
                    $expiry_timestamp === false ||
                    $expiry_timestamp < time()
                ) {
                    throw new \RuntimeException(
                        'This quotation version has expired and cannot be accepted.'
                    );
                }
            }

            /*
             * Update the exact quotation version.
             * The status condition protects against unexpected changes.
             */
            $version_updated = $wpdb->update(
                $versions_table,
                [
                    'status'              => 'accepted',
                    'accepted_at'         => $now,
                    'acceptance_method'   => 'administrator_recorded',
                    'acceptance_reference' => null,
                ],
                [
                    'id'           => $version_id,
                    'quotation_id' => $quotation_id,
                    'status'       => 'sent',
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                    '%d',
                    '%s',
                ]
            );

            if ($version_updated !== 1) {
                throw new \RuntimeException(
                    'The quotation version could not be updated. It may have changed during processing.'
                );
            }

            /*
             * Update the quotation master status.
             * The current-version condition prevents updating a different version.
             */
            $quotation_updated = $wpdb->update(
                $quotations_table,
                [
                    'status'     => 'accepted',
                    'updated_at' => $now,
                ],
                [
                    'id'                => $quotation_id,
                    'current_version_id' => $version_id,
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                    '%d',
                ]
            );

            if ($quotation_updated !== 1) {
                throw new \RuntimeException(
                    'The quotation master status could not be updated.'
                );
            }

            /*
             * Record the acceptance in the activity log.
             */
            $activity_inserted = $wpdb->insert(
                $activity_table,
                [
                    'user_id'        => $admin_id,
                    'entity_type'    => 'quotation',
                    'entity_id'      => $quotation_id,
                    'action'         => 'quotation_accepted',
                    'description'    => sprintf(
                        'Quotation version %d was accepted and recorded manually by an administrator.',
                        $version_id
                    ),
                    'created_at'     => $now,
                    'previous_status' => $quotation->status,
                    'new_status'     => 'accepted',
                    'reason'         => $reason,
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

            if ($activity_inserted !== 1) {
                throw new \RuntimeException(
                    'The acceptance activity could not be recorded.'
                );
            }

            /*
             * Commit only after all operations succeed.
             */
            $committed = $wpdb->query('COMMIT');

            if ($committed === false) {
                throw new \RuntimeException(
                    'The acceptance transaction could not be committed.'
                );
            }

            return true;
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');

            return new \WP_Error(
                'quotation_acceptance_failed',
                $exception->getMessage()
            );
        }
    }
}
