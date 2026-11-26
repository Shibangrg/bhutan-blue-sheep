<?php

namespace BlueSheep\BusinessManager\Admin;

use BlueSheep\BusinessManager\Modules\Customers\CustomerService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the Business Manager customers administration page.
 */
final class CustomersPage
{
    /**
     * Customer service instance.
     */
    private CustomerService $customer_service;

    /**
     * Initialize the customers page.
     */
    public function __construct(?CustomerService $customer_service = null)
    {
        $this->customer_service = $customer_service ?? new CustomerService();
    }

    /**
     * Render the customers administration page.
     */
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'You do not have permission to access this page.',
                    'blue-sheep-business-manager'
                )
            );
        }

        $search = isset($_GET['customer_search'])
            ? sanitize_text_field(wp_unslash($_GET['customer_search']))
            : '';

        $current_page = isset($_GET['paged'])
            ? max(1, absint($_GET['paged']))
            : 1;

        $per_page = 20;

        $result = $this->customer_service->getCustomers(
            $current_page,
            $per_page,
            $search
        );

        $customers = $result['customers'];
        $total = $result['total'];
        $total_pages = $result['total_pages'];

        ?>

        <div class="wrap bsbm-customers-page">

            <h1>
                <?php echo esc_html__('Customers', 'blue-sheep-business-manager'); ?>
            </h1>

            <p>
                <?php
                echo esc_html__(
                    'View and search customer records received through your tour enquiries.',
                    'blue-sheep-business-manager'
                );
                ?>
            </p>

            <hr class="wp-header-end">

            <div class="bsbm-customers-toolbar">

                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">

                    <input
                        type="hidden"
                        name="page"
                        value="bsbm-customers"
                    >

                    <p class="search-box">

                        <label
                            class="screen-reader-text"
                            for="bsbm-customer-search"
                        >
                            <?php
                            echo esc_html__(
                                'Search customers',
                                'blue-sheep-business-manager'
                            );
                            ?>
                        </label>

                        <input
                            type="search"
                            id="bsbm-customer-search"
                            name="customer_search"
                            value="<?php echo esc_attr($search); ?>"
                            placeholder="<?php echo esc_attr__('Search customers...', 'blue-sheep-business-manager'); ?>"
                        >

                        <input
                            type="submit"
                            class="button"
                            value="<?php echo esc_attr__('Search Customers', 'blue-sheep-business-manager'); ?>"
                        >

                    </p>

                </form>

            </div>

            <p class="bsbm-customer-count">
                <?php
                printf(
                    esc_html__(
                        'Total customers: %d',
                        'blue-sheep-business-manager'
                    ),
                    (int) $total
                );
                ?>
            </p>

            <table class="wp-list-table widefat fixed striped">

                <thead>
                    <tr>
                        <th scope="col">
                            <?php echo esc_html__('Name', 'blue-sheep-business-manager'); ?>
                        </th>

                        <th scope="col">
                            <?php echo esc_html__('Email', 'blue-sheep-business-manager'); ?>
                        </th>

                        <th scope="col">
                            <?php echo esc_html__('Phone', 'blue-sheep-business-manager'); ?>
                        </th>

                        <th scope="col">
                            <?php echo esc_html__('Country', 'blue-sheep-business-manager'); ?>
                        </th>

                        <th scope="col">
                            <?php echo esc_html__('Date Added', 'blue-sheep-business-manager'); ?>
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!empty($customers)) : ?>

                        <?php foreach ($customers as $customer) : ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $customer['full_name'] ?? ''
                                        );
                                        ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php if (!empty($customer['email'])) : ?>
                                        <a href="<?php echo esc_url('mailto:' . sanitize_email($customer['email'])); ?>">
                                            <?php echo esc_html($customer['email']); ?>
                                        </a>
                                    <?php else : ?>
                                        <span aria-label="No email">
                                            <?php echo esc_html__('Not provided', 'blue-sheep-business-manager'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($customer['phone'])
                                        ? esc_html($customer['phone'])
                                        : esc_html__('Not provided', 'blue-sheep-business-manager');
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($customer['country'])
                                        ? esc_html($customer['country'])
                                        : esc_html__('Not provided', 'blue-sheep-business-manager');
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    if (!empty($customer['created_at'])) {
                                        $timestamp = strtotime($customer['created_at']);

                                        echo $timestamp
                                            ? esc_html(
                                                wp_date(
                                                    get_option('date_format'),
                                                    $timestamp
                                                )
                                            )
                                            : esc_html__('Unavailable', 'blue-sheep-business-manager');
                                    } else {
                                        echo esc_html__('Unavailable', 'blue-sheep-business-manager');
                                    }
                                    ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else : ?>

                        <tr>
                            <td colspan="5">
                                <?php
                                echo $search !== ''
                                    ? esc_html__(
                                        'No customers found matching your search.',
                                        'blue-sheep-business-manager'
                                    )
                                    : esc_html__(
                                        'No customer records are available yet.',
                                        'blue-sheep-business-manager'
                                    );
                                ?>
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

            <?php if ($total_pages > 1) : ?>

                <div class="tablenav">
                    <div class="tablenav-pages">

                        <?php
                        echo wp_kses_post(
                            paginate_links([
                                'base' => add_query_arg(
                                    'paged',
                                    '%#%',
                                    admin_url('admin.php?page=bsbm-customers')
                                ),
                                'format' => '',
                                'current' => $current_page,
                                'total' => $total_pages,
                                'prev_text' => __('&laquo; Previous', 'blue-sheep-business-manager'),
                                'next_text' => __('Next &raquo;', 'blue-sheep-business-manager'),
                                'add_args' => $search !== ''
                                    ? ['customer_search' => $search]
                                    : false,
                            ])
                        );
                        ?>

                    </div>
                </div>

            <?php endif; ?>

        </div>

        <?php
    }
}
