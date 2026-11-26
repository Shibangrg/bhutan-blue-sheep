<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prepares quotation data for PDF generation.
 *
 * This class retrieves existing quotation information without
 * modifying quotation, customer, financial, or itinerary records.
 */
final class QuotationPdfDataProvider
{
    private QuotationRepository $quotation_repository;

    private QuotationVersionRepository $version_repository;

    private QuotationItineraryService $itinerary_service;

    /**
     * Initialize the data provider.
     */
    public function __construct(
        ?QuotationRepository $quotation_repository = null,
        ?QuotationVersionRepository $version_repository = null,
        ?QuotationItineraryService $itinerary_service = null
    ) {
        $this->quotation_repository = $quotation_repository
            ?? new QuotationRepository();

        $this->version_repository = $version_repository
            ?? new QuotationVersionRepository();

        $this->itinerary_service = $itinerary_service
            ?? new QuotationItineraryService();
    }

    /**
     * Prepare all data required for a quotation PDF.
     *
     * Only the quotation's current version is included.
     *
     * @param int $quotation_id Quotation ID.
     *
     * @return array|WP_Error
     */
    public function getQuotationPdfData(int $quotation_id): array|\WP_Error
    {
        if ($quotation_id <= 0) {
            return $this->error(
                'invalid_quotation',
                'A valid quotation ID is required.'
            );
        }

        /*
         * Retrieve quotation and customer information.
         */
        $quotation = $this->quotation_repository->getById(
            $quotation_id
        );

        if (!is_array($quotation)) {
            return $this->error(
                'quotation_not_found',
                'The requested quotation could not be found.'
            );
        }

        $version_id = (int) ($quotation['current_version_id'] ?? 0);

        if ($version_id <= 0) {
            return $this->error(
                'quotation_version_missing',
                'This quotation does not have a current version.'
            );
        }

        /*
         * Retrieve the current quotation version and its items.
         */
        $version = $this->version_repository->getVersionWithItems(
            $version_id
        );

        if (!is_array($version)) {
            return $this->error(
                'quotation_version_not_found',
                'The current quotation version could not be retrieved.'
            );
        }

        /*
         * Verify that the retrieved version belongs to this quotation
         * and is still its current version.
         */
        if (
            (int) ($version['quotation_id'] ?? 0) !== $quotation_id
            || (int) ($version['current_version_id'] ?? 0) !== $version_id
        ) {
            return $this->error(
                'quotation_version_mismatch',
                'The requested quotation version is not the current version.'
            );
        }

        /*
         * Retrieve the itinerary associated with the current version.
         */
        $itinerary = $this->itinerary_service->getDays($version_id);

        if (is_wp_error($itinerary)) {
            return $itinerary;
        }

        if (!is_array($itinerary)) {
            return $this->error(
                'itinerary_retrieval_failed',
                'The quotation itinerary could not be prepared.'
            );
        }

        /*
         * Decode stored itinerary activities for PDF rendering.
         */
        foreach ($itinerary as &$day) {
            $activities = $day['activities'] ?? '';

            $decoded_activities = is_string($activities)
                ? json_decode($activities, true)
                : null;

            $day['activities_list'] = is_array($decoded_activities)
                ? $decoded_activities
                : [];
        }

        unset($day);

        /*
         * Retrieve financial items from the quotation version.
         */
        $items = $version['items'] ?? [];

        if (!is_array($items)) {
            return $this->error(
                'quotation_items_invalid',
                'The quotation cost items could not be prepared.'
            );
        }

        /*
         * Return a structured data package for the PDF template.
         */
        return [
            'quotation' => [
                'id' => (int) $quotation['id'],
                'reference_number' => $quotation['reference_number'],
                'status' => $quotation['status'],
                'created_at' => $quotation['created_at'],
                'updated_at' => $quotation['updated_at'],
            ],

            'customer' => [
                'id' => (int) $quotation['customer_id'],
                'full_name' => $quotation['customer_name'],
                'email' => $quotation['customer_email'],
                'phone' => $quotation['customer_phone'] ?? '',
                'country' => $quotation['customer_country'] ?? '',
            ],

            'version' => $version,

            'items' => $items,

            'itinerary' => $itinerary,

            'summary' => [
                'currency' => $version['currency'] ?? '',
                'subtotal' => $version['subtotal'] ?? 0,
                'discount' => $version['discount'] ?? 0,
                'total_amount' => $version['total_amount'] ?? 0,
                'deposit_type' => $version['deposit_type'] ?? '',
                'deposit_value' => $version['deposit_value'] ?? 0,
                'balance_due_date' => $version['balance_due_date'] ?? null,
                'valid_until' => $version['valid_until'] ?? null,
            ],

            'generated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    /**
     * Create a WordPress error.
     */
    private function error(string $code, string $message): \WP_Error
    {
        return new \WP_Error($code, $message);
    }
}
