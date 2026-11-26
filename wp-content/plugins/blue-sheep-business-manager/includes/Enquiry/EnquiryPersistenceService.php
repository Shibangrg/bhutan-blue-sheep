<?php

namespace BlueSheep\BusinessManager\Enquiry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Persists validated customer enquiries.
 *
 * Creates a new customer record for every enquiry and inserts
 * the enquiry within the same database transaction.
 */
final class EnquiryPersistenceService
{
    /**
     * Save a validated enquiry and its customer record.
     *
     * @param array<string, mixed> $data Validated enquiry data.
     *
     * @return array{
     *     success: bool,
     *     customer_id: int|null,
     *     enquiry_id: int|null,
     *     error: string|null
     * }
     */
    public function save(array $data): array
    {
        global $wpdb;

        $required_fields = [
            'full_name',
            'email',
            'phone',
            'country',
            'enquiry_type',
            'tour_id',
            'preferred_start_date',
            'preferred_end_date',
            'dates_flexible',
            'traveller_count',
            'adults_count',
            'children_count',
            'destinations',
            'interests',
            'budget_range',
            'additional_requirements',
        ];

        foreach ($required_fields as $field) {
            if (!array_key_exists($field, $data)) {
                return $this->failure(
                    'The validated enquiry data is incomplete.'
                );
            }
        }

        if (
            !is_array($data['destinations']) ||
            !is_array($data['interests'])
        ) {
            return $this->failure(
                'The enquiry contains invalid selection data.'
            );
        }

        $destinations_json = wp_json_encode($data['destinations']);
        $interests_json = wp_json_encode($data['interests']);

        if (
            !is_string($destinations_json) ||
            !is_string($interests_json)
        ) {
            return $this->failure(
                'Unable to encode enquiry selection data.'
            );
        }

        $customers_table = $wpdb->prefix . 'bs_customers';
        $enquiries_table = $wpdb->prefix . 'bs_enquiries';

        $timestamp = current_time('mysql', true);

        // Start a transaction before inserting either record.
        if ($wpdb->query('START TRANSACTION') === false) {
            $this->logDatabaseError('Starting enquiry transaction');

            return $this->failure(
                'Unable to start the enquiry transaction.'
            );
        }

        try {
            // Create a separate customer record for this enquiry.
            $customer_inserted = $wpdb->insert(
                $customers_table,
                [
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'country' => $data['country'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($customer_inserted === false) {
                $this->logDatabaseError('Inserting customer');
                $this->rollback();

                return $this->failure(
                    'Unable to save customer information.'
                );
            }

            $customer_id = (int) $wpdb->insert_id;

            if ($customer_id < 1) {
                $this->rollback();

                return $this->failure(
                    'Unable to retrieve the customer record.'
                );
            }

            // Insert the enquiry using the newly created customer ID.
            $enquiry_inserted = $wpdb->insert(
                $enquiries_table,
                [
                    'customer_id' => $customer_id,
                    'enquiry_type' => $data['enquiry_type'],
                    'tour_id' => $data['tour_id'],
                    'preferred_start_date' =>
                        $data['preferred_start_date'],
                    'preferred_end_date' =>
                        $data['preferred_end_date'],
                    'dates_flexible' => $data['dates_flexible'],
                    'traveller_count' => $data['traveller_count'],
                    'adults_count' => $data['adults_count'],
                    'children_count' => $data['children_count'],
                    'destinations' => $destinations_json,
                    'interests' => $interests_json,
                    'budget_range' => $data['budget_range'],
                    'additional_requirements' =>
                        $data['additional_requirements'],
                    'status' => 'new',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                [
                    '%d',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%d',
                    '%d',
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($enquiry_inserted === false) {
                $this->logDatabaseError('Inserting enquiry');
                $this->rollback();

                return $this->failure(
                    'Unable to save the enquiry.'
                );
            }

            $enquiry_id = (int) $wpdb->insert_id;

            if ($enquiry_id < 1) {
                $this->rollback();

                return $this->failure(
                    'Unable to retrieve the enquiry record.'
                );
            }

            // Commit both records together.
            if ($wpdb->query('COMMIT') === false) {
                $this->logDatabaseError('Committing enquiry transaction');
                $this->rollback();

                return $this->failure(
                    'Unable to complete the enquiry transaction.'
                );
            }

            return [
                'success' => true,
                'customer_id' => $customer_id,
                'enquiry_id' => $enquiry_id,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            $this->logDatabaseError(
                'Unexpected enquiry persistence error: '
                . $exception->getMessage()
            );

            $this->rollback();

            return $this->failure(
                'An unexpected error occurred while saving the enquiry.'
            );
        }
    }

    /**
     * Roll back the current database transaction.
     */
    private function rollback(): void
    {
        global $wpdb;

        if ($wpdb->query('ROLLBACK') === false) {
            $this->logDatabaseError('Rolling back enquiry transaction');
        }
    }

    /**
     * Log database errors without exposing them to website visitors.
     */
    private function logDatabaseError(string $operation): void
    {
        global $wpdb;

        error_log(
            '[BSBM] ' . $operation . ': ' . $wpdb->last_error
        );
    }

    /**
     * Return a standard failure response.
     *
     * @return array{
     *     success: bool,
     *     customer_id: int|null,
     *     enquiry_id: int|null,
     *     error: string|null
     * }
     */
    private function failure(string $message): array
    {
        return [
            'success' => false,
            'customer_id' => null,
            'enquiry_id' => null,
            'error' => $message,
        ];
    }
}
