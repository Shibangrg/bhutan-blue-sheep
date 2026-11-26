<?php

namespace BlueSheep\BusinessManager\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the Business Manager administration menu.
 */
final class AdminMenu
{
    /**
     * Reuse one quotation page instance for request handling and rendering.
     */
    private ?QuotationsPage $quotations_page = null;

    /**
     * Register administration hooks.
     */
    public function register(): void
    {
        // Instantiate before the admin page begins rendering.
        $this->quotations_page = new QuotationsPage();

        // Register POST handlers before any page output.
        $this->quotations_page->registerSubmissionHandlers();

        // Register the administration menus.
        add_action('admin_menu', [$this, 'registerMenus']);
    }

    /**
     * Register the top-level menu and submenu pages.
     */
    public function registerMenus(): void
    {
        $dashboard_page = new DashboardPage();
        $customers_page = new CustomersPage();
        $enquiries_page = new EnquiriesPage();
        $bookings_page = new BookingsPage();
        $notifications_page = new NotificationsPage();

        // Fallback for direct invocation outside the normal registration flow.
        if (!$this->quotations_page instanceof QuotationsPage) {
            $this->quotations_page = new QuotationsPage();
            $this->quotations_page->registerSubmissionHandlers();
        }

        add_menu_page(
            __('Business Manager', 'blue-sheep-business-manager'),
            __('Business Manager', 'blue-sheep-business-manager'),
            'manage_options',
            'bsbm-dashboard',
            [$dashboard_page, 'render'],
            'dashicons-businessman',
            25
        );

        add_submenu_page(
            'bsbm-dashboard',
            __('Dashboard', 'blue-sheep-business-manager'),
            __('Dashboard', 'blue-sheep-business-manager'),
            'manage_options',
            'bsbm-dashboard',
            [$dashboard_page, 'render']
        );

        add_submenu_page(
            'bsbm-dashboard',
            __('Customers', 'blue-sheep-business-manager'),
            __('Customers', 'blue-sheep-business-manager'),
            'manage_options',
            'bsbm-customers',
            [$customers_page, 'render']
        );

        add_submenu_page(
            'bsbm-dashboard',
            __('Enquiries', 'blue-sheep-business-manager'),
            __('Enquiries', 'blue-sheep-business-manager'),
            'manage_options',
            'bsbm-enquiries',
            [$enquiries_page, 'render']
        );

        add_submenu_page(
            'bsbm-dashboard',
            __('Quotations', 'blue-sheep-business-manager'),
            __('Quotations', 'blue-sheep-business-manager'),
            'manage_options',
            'bsbm-quotations',
            [$this->quotations_page, 'render']
        );

        add_submenu_page(
            'bsbm-dashboard',
            __('Bookings', 'blue-sheep-business-manager'),
            __('Bookings', 'blue-sheep-business-manager'),
            'manage_options',
            'bsbm-bookings',
            [$bookings_page, 'render']
        );

        add_submenu_page(
            'bsbm-dashboard',
            __('Notifications', 'blue-sheep-business-manager'),
            __('Notifications', 'blue-sheep-business-manager'),
            'manage_options',
            'bsbm-notifications',
            [$notifications_page, 'render']
        );
    }
}
