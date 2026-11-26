<?php

namespace BlueSheep\BusinessManager\Modules\Customers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles customer-related business logic.
 */
final class CustomerService
{
    /**
     * Customer repository instance.
     */
    private CustomerRepository $repository;

    /**
     * Initialize the customer service.
     */
    public function __construct(?CustomerRepository $repository = null)
    {
        $this->repository = $repository ?? new CustomerRepository();
    }

    /**
     * Retrieve customers with pagination and search.
     *
     * @param int    $page     Current page number.
     * @param int    $per_page Records per page.
     * @param string $search   Search term.
     *
     * @return array
     */
    public function getCustomers(
        int $page = 1,
        int $per_page = 20,
        string $search = ''
    ): array {
        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page));
        $search = sanitize_text_field($search);

        $total = $this->repository->count($search);

        $total_pages = (int) ceil($total / $per_page);

        $offset = ($page - 1) * $per_page;

        $customers = $this->repository->getAll(
            $per_page,
            $offset,
            $search
        );

        return [
            'customers' => $customers,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
        ];
    }

    /**
     * Retrieve a customer by ID.
     *
     * @param int $customer_id Customer ID.
     *
     * @return array|null
     */
    public function getCustomer(int $customer_id): ?array
    {
        if ($customer_id <= 0) {
            return null;
        }

        return $this->repository->getById($customer_id);
    }
}
