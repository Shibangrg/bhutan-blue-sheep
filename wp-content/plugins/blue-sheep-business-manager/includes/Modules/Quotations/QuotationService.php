<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

use RuntimeException;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles quotation creation and retrieval.
 */
final class QuotationService
{
    /**
     * WordPress database instance.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Quotation repository.
     */
    private QuotationRepository $quotation_repository;

    /**
     * Quotation version repository.
     */
    private QuotationVersionRepository $version_repository;

    /**
     * Database table names.
     */
    private string $quotation_table;
    private string $version_table;
    private string $enquiry_table;
    private string $activity_table;

    /**
     * Initialize the quotation service.
     */
    public function __construct(
        ?QuotationRepository $quotation_repository = null,
        ?QuotationVersionRepository $version_repository = null
    ) {
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->quotation_repository = $quotation_repository ?? new QuotationRepository();
        $this->version_repository = $version_repository ?? new QuotationVersionRepository();

        $this->quotation_table = $wpdb->prefix . 'bs_quotations';
        $this->version_table = $wpdb->prefix . 'bs_quotation_versions';
        $this->enquiry_table = $wpdb->prefix . 'bs_enquiries';
        $this->activity_table = $wpdb->prefix . 'bs_activity_log';
    }

    /**
     * Create a quotation and its initial draft version from an enquiry.
     *
     * @param int    $enquiry_id Enquiry ID.
     * @param string $currency   Three-character quotation currency.
     *
     * @return int|\WP_Error Created quotation ID or an error.
     */
    public function createDraftFromEnquiry(
        int $enquiry_id,
        string $currency
    ) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'quotation_permission_denied',
                __('You do not have permission to create quotations.', 'blue-sheep-business-manager')
            );
        }

        if ($enquiry_id <= 0) {
            return new \WP_Error(
                'invalid_enquiry',
                __('A valid enquiry is required.', 'blue-sheep-business-manager')
            );
        }

        $currency = strtoupper(sanitize_text_field($currency));

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            return new \WP_Error(
                'invalid_currency',
                __('Enter a valid three-character currency code.', 'blue-sheep-business-manager')
            );
        }

        $quotation_id = 0;
        $transaction_started = false;

        try {
            $this->wpdb->query('START TRANSACTION');
            $transaction_started = true;

            // Lock the enquiry while creating its quotation.
            $enquiry = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT id, customer_id
                     FROM {$this->enquiry_table}
                     WHERE id = %d
                     FOR UPDATE",
                    $enquiry_id
                ),
                ARRAY_A
            );

            if (!is_array($enquiry)) {
                throw new RuntimeException('The requested enquiry does not exist.');
            }

            $customer_id = (int) $enquiry['customer_id'];

            if ($customer_id <= 0) {
                throw new RuntimeException('The enquiry does not have a valid customer.');
            }

            $reference_number = $this->generateReferenceNumber();

            $now = current_time('mysql');
            $user_id = get_current_user_id();

            // Create the quotation master record.
            $inserted = $this->wpdb->insert(
                $this->quotation_table,
                [
                    'enquiry_id' => $enquiry_id,
                    'customer_id' => $customer_id,
                    'reference_number' => $reference_number,
                    'current_version_id' => null,
                    'status' => 'draft',
                    'created_by' => $user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    '%d',
                    '%d',
                    '%s',
                    null,
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                ]
            );

            if ($inserted !== 1) {
                throw new RuntimeException('The quotation record could not be created.');
            }

            $quotation_id = (int) $this->wpdb->insert_id;

            // Create the initial draft version.
            $version_inserted = $this->wpdb->insert(
                $this->version_table,
                [
                    'quotation_id' => $quotation_id,
                    'version_number' => 1,
                    'currency' => $currency,
                    'base_currency' => null,
                    'exchange_rate' => null,
                    'exchange_rate_source' => null,
                    'exchange_rate_date' => null,
                    'subtotal' => '0.0000',
                    'discount' => '0.0000',
                    'total_amount' => '0.0000',
                    'deposit_type' => null,
                    'deposit_value' => null,
                    'balance_due_date' => null,
                    'cancellation_terms' => null,
                    'terms_and_conditions' => null,
                    'valid_until' => null,
                    'accepted_at' => null,
                    'acceptance_method' => null,
                    'acceptance_reference' => null,
                    'created_at' => $now,
                    'status' => 'draft',
                ],
                [
                    '%d',
                    '%d',
                    '%s',
                    null,
                    null,
                    null,
                    null,
                    '%s',
                    '%s',
                    '%s',
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    '%s',
                    '%s',
                ]
            );

            if ($version_inserted !== 1) {
                throw new RuntimeException('The initial quotation version could not be created.');
            }

            $version_id = (int) $this->wpdb->insert_id;

            // Link the initial version to the quotation.
            $updated = $this->wpdb->update(
                $this->quotation_table,
                [
                    'current_version_id' => $version_id,
                ],
                [
                    'id' => $quotation_id,
                ],
                [
                    '%d',
                ],
                [
                    '%d',
                ]
            );

            if ($updated !== 1) {
                throw new RuntimeException('The quotation current version could not be assigned.');
            }

            // Record quotation creation in the activity log.
            $activity_inserted = $this->wpdb->insert(
                $this->activity_table,
                [
                    'user_id' => $user_id,
                    'entity_type' => 'quotation',
                    'entity_id' => $quotation_id,
                    'action' => 'quotation_created',
                    'description' => sprintf(
                        'Quotation %s created from enquiry #%d.',
                        $reference_number,
                        $enquiry_id
                    ),
                    'created_at' => $now,
                ],
                [
                    '%d',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($activity_inserted !== 1) {
                throw new RuntimeException('The quotation activity record could not be created.');
            }

            if ($this->wpdb->query('COMMIT') === false) {
                throw new RuntimeException('The quotation transaction could not be committed.');
            }

            $transaction_started = false;

            return $quotation_id;

        } catch (Throwable $exception) {
            if ($transaction_started) {
                $this->wpdb->query('ROLLBACK');
            }

            error_log(
                'Blue Sheep quotation creation failed: ' . $exception->getMessage()
            );

            return new \WP_Error(
                'quotation_creation_failed',
                __('The quotation could not be created. Please review the enquiry and try again.', 'blue-sheep-business-manager')
            );
        }
    }

    /**
     * Retrieve quotations with pagination, search, and optional status filtering.
     *
     * Supported status filters:
     * - sent: Only sent quotations.
     * - not_sent: Quotations that have not been sent.
     * - empty: No status filtering.
     *
     * @param int    $page          Current page.
     * @param int    $per_page      Records per page.
     * @param string $search        Search term.
     * @param string $status_filter Optional status filter.
     *
     * @return array
     */
    public function getQuotations(
        int $page = 1,
        int $per_page = 20,
        string $search = '',
        string $status_filter = ''
    ): array {
        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page));
        $search = sanitize_text_field($search);
        $status_filter = sanitize_key($status_filter);

        if (!in_array($status_filter, ['sent', 'not_sent'], true)) {
            $status_filter = '';
        }

        $total = $this->quotation_repository->count(
            $search,
            $status_filter
        );

        $total_pages = (int) ceil($total / $per_page);

        return [
            'quotations' => $this->quotation_repository->getAll(
                $per_page,
                ($page - 1) * $per_page,
                $search,
                $status_filter
            ),
            'total' => $total,
            'current_page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
        ];
    }

    /**
     * Retrieve a quotation by ID.
     *
     * @param int $quotation_id Quotation ID.
     *
     * @return array|null
     */
    public function getQuotation(int $quotation_id): ?array
    {
        if ($quotation_id <= 0) {
            return null;
        }

        return $this->quotation_repository->getById($quotation_id);
    }

    /**
     * Generate a unique, non-sequential quotation reference.
     *
     * @return string
     */
    private function generateReferenceNumber(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = 'BSQ-' . strtoupper(bin2hex(random_bytes(6)));

            $exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id
                     FROM {$this->quotation_table}
                     WHERE reference_number = %s
                     LIMIT 1",
                    $reference
                )
            );

            if (!$exists) {
                return $reference;
            }
        }

        throw new RuntimeException('A unique quotation reference could not be generated.');
    }
}
