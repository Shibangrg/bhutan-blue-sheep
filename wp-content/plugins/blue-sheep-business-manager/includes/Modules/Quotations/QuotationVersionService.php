<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

use RuntimeException;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles quotation version creation and revision management.
 */
final class QuotationVersionService
{
    /**
     * WordPress database instance.
     *
     * @var wpdb
     */
    private $wpdb;

    private string $quotation_table;
    private string $version_table;
    private string $item_table;
    private string $activity_table;

    /**
     * Initialize the version service.
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->quotation_table = $wpdb->prefix . 'bs_quotations';
        $this->version_table = $wpdb->prefix . 'bs_quotation_versions';
        $this->item_table = $wpdb->prefix . 'bs_quotation_items';
        $this->activity_table = $wpdb->prefix . 'bs_activity_log';
    }

    /**
     * Create a new draft version after a customer requests changes.
     *
     * @param int $quotation_id Quotation ID.
     *
     * @return int|\WP_Error New version ID or an error.
     */
    public function createRevision(int $quotation_id)
    {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'quotation_permission_denied',
                __('You do not have permission to revise quotations.', 'blue-sheep-business-manager')
            );
        }

        if ($quotation_id <= 0) {
            return new \WP_Error(
                'invalid_quotation',
                __('A valid quotation is required.', 'blue-sheep-business-manager')
            );
        }

        $transaction_started = false;

        try {
            $this->wpdb->query('START TRANSACTION');
            $transaction_started = true;

            // Lock the quotation to serialize revision creation.
            $quotation = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT id, current_version_id, status
                     FROM {$this->quotation_table}
                     WHERE id = %d
                     FOR UPDATE",
                    $quotation_id
                ),
                ARRAY_A
            );

            if (!is_array($quotation)) {
                throw new RuntimeException('The requested quotation does not exist.');
            }

            $current_version_id = (int) $quotation['current_version_id'];

            if ($current_version_id <= 0) {
                throw new RuntimeException('The quotation does not have a current version.');
            }

            // Lock and retrieve the current version.
            $current_version = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT *
                     FROM {$this->version_table}
                     WHERE id = %d
                       AND quotation_id = %d
                     FOR UPDATE",
                    $current_version_id,
                    $quotation_id
                ),
                ARRAY_A
            );

            if (!is_array($current_version)) {
                throw new RuntimeException('The current quotation version could not be found.');
            }

            if ($current_version['status'] !== 'changes_requested') {
                throw new RuntimeException(
                    'A revision can only be created after the customer requests changes to the current sent version.'
                );
            }

            // Prevent revisions after another version has been accepted.
            $accepted_version = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id
                     FROM {$this->version_table}
                     WHERE quotation_id = %d
                       AND status = %s
                     LIMIT 1
                     FOR UPDATE",
                    $quotation_id,
                    'accepted'
                )
            );

            if ($accepted_version) {
                throw new RuntimeException('An accepted quotation cannot be revised.');
            }

            $latest_version_number = (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT MAX(version_number)
                     FROM {$this->version_table}
                     WHERE quotation_id = %d",
                    $quotation_id
                )
            );

            $new_version_number = $latest_version_number + 1;
            $now = current_time('mysql');

            // Preserve the previous version's financial and contractual data.
            $version_data = [
                'quotation_id' => $quotation_id,
                'version_number' => $new_version_number,
                'currency' => $current_version['currency'],
                'base_currency' => $current_version['base_currency'],
                'exchange_rate' => $current_version['exchange_rate'],
                'exchange_rate_source' => $current_version['exchange_rate_source'],
                'exchange_rate_date' => $current_version['exchange_rate_date'],
                'subtotal' => $current_version['subtotal'],
                'discount' => $current_version['discount'],
                'total_amount' => $current_version['total_amount'],
                'deposit_type' => $current_version['deposit_type'],
                'deposit_value' => $current_version['deposit_value'],
                'balance_due_date' => $current_version['balance_due_date'],
                'cancellation_terms' => $current_version['cancellation_terms'],
                'terms_and_conditions' => $current_version['terms_and_conditions'],
                'valid_until' => null,
                'accepted_at' => null,
                'acceptance_method' => null,
                'acceptance_reference' => null,
                'created_at' => $now,
                'status' => 'draft',
            ];

            if ($this->wpdb->insert($this->version_table, $version_data) !== 1) {
                throw new RuntimeException('The new quotation version could not be created.');
            }

            $new_version_id = (int) $this->wpdb->insert_id;

            // Copy all items without modifying the previous version.
            $items = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT
                        item_type,
                        day_number,
                        title,
                        description,
                        quantity,
                        unit_amount,
                        total_amount,
                        sort_order
                     FROM {$this->item_table}
                     WHERE quotation_version_id = %d
                     ORDER BY sort_order ASC, id ASC",
                    $current_version_id
                ),
                ARRAY_A
            );

            if ($this->wpdb->last_error !== '') {
                throw new RuntimeException('The existing quotation items could not be retrieved.');
            }

            foreach ($items as $item) {
                $item_data = [
                    'quotation_version_id' => $new_version_id,
                    'item_type' => $item['item_type'],
                    'day_number' => $item['day_number'],
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_amount' => $item['unit_amount'],
                    'total_amount' => $item['total_amount'],
                    'sort_order' => $item['sort_order'],
                ];

                if ($this->wpdb->insert($this->item_table, $item_data) !== 1) {
                    throw new RuntimeException('A quotation item could not be copied to the new version.');
                }
            }

            // Make the new draft version current.
            $updated = $this->wpdb->update(
                $this->quotation_table,
                [
                    'current_version_id' => $new_version_id,
                    'status' => 'draft',
                    'updated_at' => $now,
                ],
                [
                    'id' => $quotation_id,
                ],
                [
                    '%d',
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            if ($updated !== 1) {
                throw new RuntimeException('The quotation current-version reference could not be updated.');
            }

            // Record the revision in the activity log.
            $activity_inserted = $this->wpdb->insert(
                $this->activity_table,
                [
                    'user_id' => get_current_user_id(),
                    'entity_type' => 'quotation',
                    'entity_id' => $quotation_id,
                    'action' => 'quotation_revision_created',
                    'description' => sprintf(
                        'Quotation version %d created from version %d.',
                        $new_version_number,
                        (int) $current_version['version_number']
                    ),
                    'created_at' => $now,
                    'previous_status' => 'changes_requested',
                    'new_status' => 'draft',
                    'reason' => 'Customer-requested quotation revision.',
                ]
            );

            if ($activity_inserted !== 1) {
                throw new RuntimeException('The quotation revision activity could not be recorded.');
            }

            if ($this->wpdb->query('COMMIT') === false) {
                throw new RuntimeException('The quotation revision transaction could not be committed.');
            }

            $transaction_started = false;

            return $new_version_id;

        } catch (Throwable $exception) {
            if ($transaction_started) {
                $this->wpdb->query('ROLLBACK');
            }

            error_log(
                'Blue Sheep quotation revision failed: ' . $exception->getMessage()
            );

            return new \WP_Error(
                'quotation_revision_failed',
                __('The quotation revision could not be created. Please review the quotation and try again.', 'blue-sheep-business-manager')
            );
        }
    }
}
