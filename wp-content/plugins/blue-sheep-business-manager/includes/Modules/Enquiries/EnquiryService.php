<?php

namespace BlueSheep\BusinessManager\Modules\Enquiries;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles enquiry-related business logic.
 */
final class EnquiryService
{
    /**
     * Enquiry repository instance.
     */
    private EnquiryRepository $repository;

    /**
     * Initialize the enquiry service.
     */
    public function __construct(?EnquiryRepository $repository = null)
    {
        $this->repository = $repository ?? new EnquiryRepository();
    }

    /**
     * Retrieve enquiries with pagination, search, and optional status filtering.
     *
     * Supported status filters:
     * - closed: Only closed enquiries.
     * - not_closed: All enquiries except closed enquiries.
     * - all or empty: No status filtering.
     *
     * @param int    $page          Current page number.
     * @param int    $per_page      Records per page.
     * @param string $search        Search term.
     * @param string $status_filter Optional status filter.
     *
     * @return array
     */
    public function getEnquiries(
        int $page = 1,
        int $per_page = 20,
        string $search = '',
        string $status_filter = ''
    ): array {
        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page));
        $search = sanitize_text_field($search);

        $status_filter = sanitize_key($status_filter);

        if (!in_array($status_filter, ['closed', 'not_closed'], true)) {
            $status_filter = '';
        }

        $total = $this->repository->count(
            $search,
            $status_filter
        );

        $total_pages = (int) ceil($total / $per_page);

        $offset = ($page - 1) * $per_page;

        $enquiries = $this->repository->getAll(
            $per_page,
            $offset,
            $search,
            $status_filter
        );

        return [
            'enquiries' => $enquiries,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
        ];
    }

    /**
     * Retrieve an enquiry by ID.
     *
     * @param int $enquiry_id Enquiry ID.
     *
     * @return array|null
     */
    public function getEnquiry(int $enquiry_id): ?array
    {
        if ($enquiry_id <= 0) {
            return null;
        }

        return $this->repository->getById($enquiry_id);
    }
}
