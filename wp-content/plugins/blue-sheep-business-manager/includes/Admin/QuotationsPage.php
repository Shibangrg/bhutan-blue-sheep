<?php

namespace BlueSheep\BusinessManager\Admin;

use BlueSheep\BusinessManager\Modules\Enquiries\EnquiryService;
use BlueSheep\BusinessManager\Modules\Quotations\QuotationService;
use BlueSheep\BusinessManager\Modules\Quotations\QuotationVersionRepository;
use BlueSheep\BusinessManager\Modules\Quotations\QuotationItemService;
use BlueSheep\BusinessManager\Modules\Quotations\QuotationItineraryService;

if (!defined('ABSPATH')) {
    exit;
}

final class QuotationsPage
{
    private QuotationService $quotation_service;
    private QuotationVersionRepository $version_repository;
    private QuotationItemService $item_service;
    private QuotationItineraryService $itinerary_service;
    private EnquiryService $enquiry_service;
    private $wpdb;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->quotation_service = new QuotationService();
        $this->version_repository = new QuotationVersionRepository();
        $this->item_service = new QuotationItemService();
        $this->itinerary_service = new QuotationItineraryService();
        $this->enquiry_service = new EnquiryService();
    }

    /**
     * Register request handlers before admin page output.
     */
    public function registerSubmissionHandlers(): void
    {
        add_action('admin_post_bsbm_create_quotation', [$this, 'handleCreateSubmission']);
        add_action('admin_post_bsbm_manage_quotation_item', [$this, 'handleItemSubmission']);
        add_action('admin_post_bsbm_manage_quotation_itinerary', [$this, 'handleItinerarySubmission']);
    }

    /**
     * Render the quotation administration page.
     */
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'blue-sheep-business-manager'));
        }

        $quotation_id = isset($_GET['view']) ? absint($_GET['view']) : 0;

        if ($quotation_id > 0) {
            $this->renderDetails($quotation_id);
            return;
        }

        if (isset($_GET['create'])) {
            $this->renderCreateForm();
            return;
        }

        $this->renderListing();
    }

    /**
     * Handle quotation creation through admin-post.php.
     */
    public function handleCreateSubmission(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to create quotations.', 'blue-sheep-business-manager'));
        }

        check_admin_referer('bsbm_create_quotation', 'bsbm_quotation_nonce');

        if (!$this->requiredTablesExist()) {
            wp_die(esc_html__('The required quotation database tables are unavailable.', 'blue-sheep-business-manager'));
        }

        $enquiry_id = isset($_POST['enquiry_id'])
            ? absint($_POST['enquiry_id'])
            : 0;

        $currency = isset($_POST['currency'])
            ? sanitize_text_field(wp_unslash($_POST['currency']))
            : '';

        if ($enquiry_id <= 0) {
            wp_die(esc_html__('Please select a valid enquiry.', 'blue-sheep-business-manager'));
        }

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT id FROM ' . $this->wpdb->prefix . 'bs_quotations
                 WHERE enquiry_id = %d LIMIT 1',
                $enquiry_id
            )
        );

        if ($this->wpdb->last_error !== '') {
            wp_die(esc_html__('The existing quotation could not be checked.', 'blue-sheep-business-manager'));
        }

        if ($existing) {
            wp_die(esc_html__('A quotation already exists for this enquiry. Open the existing quotation instead.', 'blue-sheep-business-manager'));
        }

        $result = $this->quotation_service->createDraftFromEnquiry($enquiry_id, $currency);

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        $this->redirectToQuotation(absint($result), ['created' => 1]);
    }

    /**
     * Handle quotation item operations through admin-post.php.
     */
    public function handleItemSubmission(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage quotation items.', 'blue-sheep-business-manager'));
        }

        $version_id = isset($_POST['version_id']) ? absint($_POST['version_id']) : 0;
        $quotation_id = isset($_POST['quotation_id']) ? absint($_POST['quotation_id']) : 0;
        $action = isset($_POST['bsbm_item_action'])
            ? sanitize_key(wp_unslash($_POST['bsbm_item_action']))
            : '';

        if ($version_id <= 0 || $quotation_id <= 0) {
            wp_die(esc_html__('Invalid quotation or version.', 'blue-sheep-business-manager'));
        }

        check_admin_referer(
            'bsbm_manage_quotation_items_' . $version_id,
            'bsbm_item_nonce'
        );

        $quotation = $this->quotation_service->getQuotation($quotation_id);

        if (!$quotation) {
            wp_die(esc_html__('The quotation could not be found.', 'blue-sheep-business-manager'));
        }

        if (absint($quotation['current_version_id'] ?? 0) !== $version_id) {
            wp_die(esc_html__('Only the current quotation version can be modified.', 'blue-sheep-business-manager'));
        }

        $version = $this->version_repository->getVersionWithItems($version_id);

        if (!is_array($version) || ($version['status'] ?? '') !== 'draft') {
            wp_die(esc_html__('Only draft quotation versions can be modified.', 'blue-sheep-business-manager'));
        }

        switch ($action) {
            case 'add':
                $input = isset($_POST['item']) && is_array($_POST['item'])
                    ? wp_unslash($_POST['item'])
                    : [];

                $result = $this->item_service->addItem($version_id, $input);
                $success_key = 'item_added';
                break;

            case 'update':
                $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
                $input = isset($_POST['item']) && is_array($_POST['item'])
                    ? wp_unslash($_POST['item'])
                    : [];

                $result = $this->item_service->updateItem($version_id, $item_id, $input);
                $success_key = 'item_updated';
                break;

            case 'delete':
                $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
                $result = $this->item_service->deleteItem($version_id, $item_id);
                $success_key = 'item_deleted';
                break;

            default:
                wp_die(esc_html__('Unsupported quotation item action.', 'blue-sheep-business-manager'));
        }

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        if ($result === false || $result === null) {
            wp_die(esc_html__('The quotation item operation could not be completed.', 'blue-sheep-business-manager'));
        }

        $this->redirectToQuotation($quotation_id, [$success_key => 1]);
    }

    /**
     * Handle itinerary day operations through admin-post.php.
     */
    public function handleItinerarySubmission(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage quotation itineraries.', 'blue-sheep-business-manager'));
        }

        $version_id = isset($_POST['version_id']) ? absint($_POST['version_id']) : 0;
        $quotation_id = isset($_POST['quotation_id']) ? absint($_POST['quotation_id']) : 0;
        $action = isset($_POST['bsbm_itinerary_action'])
            ? sanitize_key(wp_unslash($_POST['bsbm_itinerary_action']))
            : '';

        if ($version_id <= 0 || $quotation_id <= 0) {
            wp_die(esc_html__('Invalid quotation or version.', 'blue-sheep-business-manager'));
        }

        check_admin_referer(
            'bsbm_manage_quotation_itinerary_' . $version_id,
            'bsbm_itinerary_nonce'
        );

        if (!$this->tableExists('bs_quotation_itinerary_days')) {
            wp_die(esc_html__('The itinerary database table is unavailable. Apply the approved local itinerary migration before using this feature.', 'blue-sheep-business-manager'));
        }

        $quotation = $this->quotation_service->getQuotation($quotation_id);

        if (!$quotation) {
            wp_die(esc_html__('The quotation could not be found.', 'blue-sheep-business-manager'));
        }

        if (absint($quotation['current_version_id'] ?? 0) !== $version_id) {
            wp_die(esc_html__('Only the current quotation version can be modified.', 'blue-sheep-business-manager'));
        }

        $version = $this->version_repository->getVersionWithItems($version_id);

        if (!is_array($version) || ($version['status'] ?? '') !== 'draft') {
            wp_die(esc_html__('Only draft quotation versions can be modified.', 'blue-sheep-business-manager'));
        }

        switch ($action) {
            case 'add':
                $input = isset($_POST['day']) && is_array($_POST['day'])
                    ? wp_unslash($_POST['day'])
                    : [];

                $result = $this->itinerary_service->addDay($version_id, $input);
                $success_key = 'itinerary_day_added';
                break;

            case 'update':
                $day_id = isset($_POST['day_id']) ? absint($_POST['day_id']) : 0;
                $input = isset($_POST['day']) && is_array($_POST['day'])
                    ? wp_unslash($_POST['day'])
                    : [];

                $result = $this->itinerary_service->updateDay($version_id, $day_id, $input);
                $success_key = 'itinerary_day_updated';
                break;

            case 'delete':
                $day_id = isset($_POST['day_id']) ? absint($_POST['day_id']) : 0;
                $result = $this->itinerary_service->deleteDay($version_id, $day_id);
                $success_key = 'itinerary_day_deleted';
                break;

            default:
                wp_die(esc_html__('Unsupported itinerary operation.', 'blue-sheep-business-manager'));
        }

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        if ($result === false || $result === null) {
            wp_die(esc_html__('The itinerary operation could not be completed.', 'blue-sheep-business-manager'));
        }

        $this->redirectToQuotation($quotation_id, [$success_key => 1]);
    }

    private function redirectToQuotation(int $quotation_id, array $args = []): void
    {
        $url = add_query_arg(
            array_merge([
                'page' => 'bsbm-quotations',
                'view' => $quotation_id,
            ], $args),
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    private function tableExists(string $table_name): bool
    {
        $table = $this->wpdb->prefix . $table_name;
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $this->wpdb->esc_like($table))
        );

        return $result === $table;
    }

    private function requiredTablesExist(): bool
    {
        return $this->tableExists('bs_customers')
            && $this->tableExists('bs_enquiries')
            && $this->tableExists('bs_quotations')
            && $this->tableExists('bs_quotation_versions')
            && $this->tableExists('bs_activity_log');
    }

    private function renderCreateForm(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Create Quotation', 'blue-sheep-business-manager') . '</h1>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=bsbm-quotations')) . '">';
        echo esc_html__('Back to Quotations', 'blue-sheep-business-manager');
        echo '</a></p>';

        if (!$this->requiredTablesExist()) {
            $this->renderError(__('Quotation creation is unavailable because required database tables are missing.', 'blue-sheep-business-manager'));
            echo '</div>';
            return;
        }

        $result = $this->enquiry_service->getEnquiries(1, 100, '');

        if (is_wp_error($result)) {
            $this->renderError($result->get_error_message());
            echo '</div>';
            return;
        }

        $enquiries = $result['enquiries'] ?? [];

        if (empty($enquiries)) {
            $this->renderInfo(__('There are no enquiries available for quotation creation.', 'blue-sheep-business-manager'));
            echo '</div>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bsbm_create_quotation">';
        wp_nonce_field('bsbm_create_quotation', 'bsbm_quotation_nonce');

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="bsbm-enquiry-id">' . esc_html__('Select Enquiry', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<select name="enquiry_id" id="bsbm-enquiry-id" required>';
        echo '<option value="">' . esc_html__('Choose an enquiry', 'blue-sheep-business-manager') . '</option>';

        foreach ($enquiries as $enquiry) {
            $id = absint($enquiry['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $label = sprintf(
                'Enquiry #%d - %s (%s)',
                $id,
                $enquiry['customer_name'] ?? 'Unknown customer',
                $enquiry['customer_email'] ?? 'No email'
            );

            echo '<option value="' . esc_attr((string) $id) . '">' . esc_html($label) . '</option>';
        }

        echo '</select>';
        echo '<p class="description">' . esc_html__('Select the existing customer enquiry for this quotation.', 'blue-sheep-business-manager') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bsbm-currency">' . esc_html__('Quotation Currency', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<input type="text" name="currency" id="bsbm-currency" value="BTN" maxlength="3" minlength="3" pattern="[A-Za-z]{3}" required class="regular-text">';
        echo '<p class="description">' . esc_html__('Enter a three-character currency code, for example BTN, USD or INR.', 'blue-sheep-business-manager') . '</p>';
        echo '</td></tr>';
        echo '</tbody></table>';

        submit_button(__('Create Draft Quotation', 'blue-sheep-business-manager'));
        echo '</form></div>';
    }

    /**
     * Render separate draft and sent quotation listings.
     */
    private function renderListing(): void
    {
        $draft_page = isset($_GET['paged'])
            ? max(1, absint($_GET['paged']))
            : 1;

        $sent_page = isset($_GET['sent_paged'])
            ? max(1, absint($_GET['sent_paged']))
            : 1;

        $search = isset($_GET['s'])
            ? sanitize_text_field(wp_unslash($_GET['s']))
            : '';

        echo '<div class="wrap">';

        echo '<h1>' . esc_html__('Quotations', 'blue-sheep-business-manager') . '</h1>';

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=bsbm-quotations&create=1')) . '" class="page-title-action">';
        echo esc_html__('Create Quotation', 'blue-sheep-business-manager');
        echo '</a></p>';

        if (!$this->tableExists('bs_quotations') || !$this->tableExists('bs_quotation_versions')) {
            $this->renderError(
                __('Quotation database tables are unavailable.', 'blue-sheep-business-manager')
            );

            echo '</div>';
            return;
        }

        // Retrieve quotations that have not been dispatched.
        $draft_result = $this->quotation_service->getQuotations(
            $draft_page,
            20,
            $search,
            'not_sent'
        );

        // Retrieve quotations whose dispatch has been recorded.
        $sent_result = $this->quotation_service->getQuotations(
            $sent_page,
            20,
            $search,
            'sent'
        );

        if (is_wp_error($draft_result) || is_wp_error($sent_result)) {
            $this->renderError(
                __('The quotation lists could not be loaded.', 'blue-sheep-business-manager')
            );

            echo '</div>';
            return;
        }

        $this->renderSearchForm($search);

        $draft_quotations = $draft_result['quotations'] ?? [];
        $sent_quotations = $sent_result['quotations'] ?? [];

        $draft_total = (int) ($draft_result['total'] ?? 0);
        $sent_total = (int) ($sent_result['total'] ?? 0);

        // Draft quotations.
        echo '<h2 style="margin-top:25px;">';
        echo esc_html__('Draft Quotations', 'blue-sheep-business-manager');
        echo '</h2>';

        echo '<p>' . esc_html(
            sprintf(
                __('Total draft quotations: %d', 'blue-sheep-business-manager'),
                $draft_total
            )
        ) . '</p>';

        $this->renderTable(
            $draft_quotations,
            __('No draft quotations found.', 'blue-sheep-business-manager')
        );

        $this->renderPagination(
            $draft_page,
            (int) ($draft_result['total_pages'] ?? 1),
            $search,
            'paged',
            'sent_paged',
            $sent_page
        );

        echo '<hr style="margin:35px 0;">';

        // Sent quotations.
        echo '<h2>';
        echo esc_html__('Sent Quotations', 'blue-sheep-business-manager');
        echo '</h2>';

        echo '<p>' . esc_html(
            sprintf(
                __('Total sent quotations: %d', 'blue-sheep-business-manager'),
                $sent_total
            )
        ) . '</p>';

        $this->renderTable(
            $sent_quotations,
            __('No sent quotations found.', 'blue-sheep-business-manager')
        );

        $this->renderPagination(
            $sent_page,
            (int) ($sent_result['total_pages'] ?? 1),
            $search,
            'sent_paged',
            'paged',
            $draft_page
        );

        echo '</div>';
    }
    private function renderDetails(int $quotation_id): void{
        $quotation = $this->quotation_service->getQuotation($quotation_id);
        echo '<div class="wrap">';

        if (!$quotation) {
            $this->renderError(__('Quotation not found.', 'blue-sheep-business-manager'));
            echo '</div>';
            return;
        }

        if (!$this->tableExists('bs_quotation_versions')) {
            $this->renderError(__('Quotation version data is unavailable.', 'blue-sheep-business-manager'));
            echo '</div>';
            return;
        }

        echo '<h1>' . esc_html__('Quotation Details', 'blue-sheep-business-manager') . '</h1>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=bsbm-quotations')) . '">';
        echo esc_html__('Back to Quotations', 'blue-sheep-business-manager');
        echo '</a></p>';

        $this->renderActionNotices();

        if (isset($_GET['created']) && absint($_GET['created']) === 1) {
            $this->renderSuccess(__('Draft quotation created successfully.', 'blue-sheep-business-manager'));
        }

        $version_id = absint($quotation['current_version_id'] ?? 0);
        $version = $version_id > 0
            ? $this->version_repository->getVersionWithItems($version_id)
            : null;

        if (is_array($version)) {
            $pdf_handler = new \BlueSheep\BusinessManager\Modules\Quotations\QuotationPdfDownloadHandler();
            $pdf_url = $pdf_handler->getDownloadUrl($quotation_id);

            echo '<p>';
            echo '<a class="button button-primary" href="' . esc_url($pdf_url) . '">';
            echo esc_html__('Generate Quotation PDF', 'blue-sheep-business-manager');
            echo '</a>';
            echo '</p>';
        }

        $this->renderQuotationSummary($quotation);

        if (is_array($version)) {
            $this->renderVersionDetails($version);
            $this->renderItems($quotation, $version);
            $this->renderItinerary($quotation, $version);
            $this->renderManualDispatchForm($quotation, $version);
            $this->renderAcceptanceForm($quotation, $version);
            $this->renderBookingConversionForm($quotation, $version);
            $this->renderDispatchHistory($quotation_id);
        } else {
            $this->renderInfo(__('No current quotation version is available.', 'blue-sheep-business-manager'));
        }

        echo '</div>';
    }

    private function renderActionNotices(): void{
        $messages = [
            'item_added' => __('Quotation item added successfully.', 'blue-sheep-business-manager'),
            'item_updated' => __('Quotation item updated successfully.', 'blue-sheep-business-manager'),
            'item_deleted' => __('Quotation item removed successfully.', 'blue-sheep-business-manager'),
            'itinerary_day_added' => __('Itinerary day added successfully.', 'blue-sheep-business-manager'),
            'itinerary_day_updated' => __('Itinerary day updated successfully.', 'blue-sheep-business-manager'),
            'itinerary_day_deleted' => __('Itinerary day removed successfully.', 'blue-sheep-business-manager'),
        ];

        foreach ($messages as $key => $message) {
            if (isset($_GET[$key]) && absint($_GET[$key]) === 1) {
                $this->renderSuccess($message);
            }
        }

        $dispatch_result = isset($_GET['dispatch_result'])
            ? sanitize_key(wp_unslash($_GET['dispatch_result']))
            : '';

        if ($dispatch_result === 'recorded') {
            $this->renderSuccess(__('Manual quotation dispatch recorded successfully.', 'blue-sheep-business-manager'));
        } elseif ($dispatch_result === 'failed') {
            $this->renderError(__('The dispatch could not be recorded. Review the quotation status and try again.', 'blue-sheep-business-manager'));
        } elseif ($dispatch_result === 'invalid_request') {
            $this->renderError(__('The dispatch request was invalid. Please try again.', 'blue-sheep-business-manager'));
        }
        $acceptance_result = isset($_GET['acceptance_result'])
            ? sanitize_key(wp_unslash($_GET['acceptance_result']))
            : '';

        if ($acceptance_result === 'accepted') {
            $this->renderSuccess(
                __('Quotation acceptance recorded successfully.', 'blue-sheep-business-manager')
            );
        } elseif ($acceptance_result === 'failed') {
            $this->renderError(
                __('Quotation acceptance could not be recorded. Verify that the quotation is still current, sent and valid.', 'blue-sheep-business-manager')
            );
        } elseif ($acceptance_result === 'invalid_request') {
            $this->renderError(
                __('The quotation acceptance request was invalid. Please try again.', 'blue-sheep-business-manager')
            );
        }
                $booking_result = isset($_GET['booking_result'])
            ? sanitize_key(wp_unslash($_GET['booking_result']))
            : '';

        if ($booking_result === 'created') {
            $booking_id = isset($_GET['booking_id'])
                ? absint($_GET['booking_id'])
                : 0;

            $message = __('Booking created successfully.', 'blue-sheep-business-manager');

            if ($booking_id > 0) {
                $message .= ' ' . sprintf(
                    __('Booking ID: %d', 'blue-sheep-business-manager'),
                    $booking_id
                );
            }

            $this->renderSuccess($message);
        } elseif ($booking_result === 'failed') {
            $this->renderError(
                __('Booking conversion failed. Verify the quotation acceptance, enquiry status, and booking details.', 'blue-sheep-business-manager')
            );
        } elseif ($booking_result === 'confirmation_required') {
            $this->renderError(
                __('Please confirm the booking details before continuing.', 'blue-sheep-business-manager')
            );
        } elseif ($booking_result === 'invalid_booking_details') {
            $this->renderError(
                __('Provide valid travel dates and a traveller count of at least one.', 'blue-sheep-business-manager')
            );
        } elseif ($booking_result === 'invalid_request') {
            $this->renderError(
                __('The booking conversion request was invalid.', 'blue-sheep-business-manager')
            );
        }
    }

    /**
     * Render the manual dispatch confirmation form for the current draft version.
     *
     * This form records a dispatch after the administrator has sent the PDF
     * using the business email account. It does not send an email.
     */
    private function renderManualDispatchForm(array $quotation, array $version): void
    {
        $version_id = absint($version['id'] ?? 0);
        $quotation_id = absint($quotation['id'] ?? 0);
        $is_current_draft = ($version['status'] ?? '') === 'draft'
            && absint($quotation['current_version_id'] ?? 0) === $version_id;

        echo '<h2 style="margin-top:30px;">' . esc_html__('Manual Quotation Dispatch', 'blue-sheep-business-manager') . '</h2>';

        if (!$is_current_draft) {
            $this->renderInfo(__('Dispatch can only be recorded for the current draft quotation version. Once recorded, this section becomes read-only.', 'blue-sheep-business-manager'));
            return;
        }

        $recipient = sanitize_email($quotation['customer_email'] ?? '');

        echo '<div style="max-width:900px; padding:18px; margin-top:12px; border:1px solid #c3c4c7; background:#fff;">';
        echo '<p>' . esc_html__('Generate and download the quotation PDF, review it, and send it manually using your business email account. After sending it, return here and confirm the dispatch. This form only records the dispatch; it does not send an email.', 'blue-sheep-business-manager') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bsbm_record_quotation_dispatch">';
        echo '<input type="hidden" name="quotation_id" value="' . esc_attr((string) $quotation_id) . '">';

        wp_nonce_field(
            'bsbm_record_quotation_dispatch_' . $quotation_id,
            'bsbm_dispatch_nonce'
        );

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="bsbm-dispatch-recipient">' . esc_html__('Recipient Email', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<input type="email" name="recipient" id="bsbm-dispatch-recipient" value="' . esc_attr($recipient) . '" class="regular-text" maxlength="190" required>';
        echo '<p class="description">' . esc_html__('Pre-filled from the customer record. Verify or edit this address before recording the dispatch.', 'blue-sheep-business-manager') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bsbm-dispatch-notes">' . esc_html__('Dispatch Notes', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<textarea name="dispatch_notes" id="bsbm-dispatch-notes" rows="3" class="large-text" maxlength="5000"></textarea>';
        echo '<p class="description">' . esc_html__('Optional notes, such as the email subject or any relevant follow-up details.', 'blue-sheep-business-manager') . '</p>';
        echo '</td></tr>';
        echo '</tbody></table>';

        echo '<p><label><input type="checkbox" name="confirm_sent" value="1" required> ';
        echo esc_html__('I confirm that I have already sent the quotation PDF to the recipient above.', 'blue-sheep-business-manager');
        echo '</label></p>';

        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Confirm Manual Dispatch', 'blue-sheep-business-manager') . '</button></p>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Render administrator-recorded quotation acceptance.
     *
     * Acceptance can only be recorded for the current sent version.
     * The administrator must provide a reason or evidence reference.
     */
    private function renderAcceptanceForm(
        array $quotation,
        array $version
    ): void {
        $quotation_id = absint($quotation['id'] ?? 0);
        $version_id = absint($version['id'] ?? 0);
        $version_status = $version['status'] ?? '';

        $is_current_version =
            $quotation_id > 0
            && $version_id > 0
            && absint($quotation['current_version_id'] ?? 0) === $version_id;

        echo '<h2 style="margin-top:30px;">';
        echo esc_html__('Quotation Acceptance', 'blue-sheep-business-manager');
        echo '</h2>';

        if (!$is_current_version) {
            $this->renderInfo(
                __('Acceptance can only be recorded for the current quotation version.', 'blue-sheep-business-manager')
            );
            return;
        }

        if ($version_status === 'accepted') {
            $this->renderSuccess(
                __('This quotation version has already been accepted. Its acceptance status is locked.', 'blue-sheep-business-manager')
            );

            if (!empty($version['accepted_at'])) {
                echo '<p>';
                echo '<strong>' . esc_html__('Accepted at (UTC):', 'blue-sheep-business-manager') . '</strong> ';
                echo esc_html((string) $version['accepted_at']);
                echo '</p>';
            }

            return;
        }

        if ($version_status !== 'sent') {
            $this->renderInfo(
                __('Acceptance can only be recorded after the current quotation version has been sent to the customer.', 'blue-sheep-business-manager')
            );
            return;
        }

        echo '<div style="max-width:900px; padding:18px; margin-top:12px; border:1px solid #c3c4c7; background:#fff;">';

        echo '<p>';
        echo esc_html__(
            'Use this form only after receiving confirmation from the customer through an agreed communication channel. This action records the customer acceptance; it does not confirm a booking or payment.',
            'blue-sheep-business-manager'
        );
        echo '</p>';

        echo '<h3>';
        echo esc_html__('Version being accepted', 'blue-sheep-business-manager');
        echo ': ' . esc_html((string) ($version['version_number'] ?? ''));
        echo '</h3>';

        if (isset($version['currency'], $version['total_amount'])) {
            echo '<p>';
            echo '<strong>' . esc_html__('Quotation Total:', 'blue-sheep-business-manager') . '</strong> ';
            echo esc_html((string) $version['currency'] . ' ' . (string) $version['total_amount']);
            echo '</p>';
        }

        if (!empty($version['valid_until'])) {
            echo '<p>';
            echo '<strong>' . esc_html__('Valid Until (UTC):', 'blue-sheep-business-manager') . '</strong> ';
            echo esc_html((string) $version['valid_until']);
            echo '</p>';
        }

        if (!empty($version['terms_and_conditions'])) {
            echo '<h3>';
            echo esc_html__('Terms and Conditions for This Version', 'blue-sheep-business-manager');
            echo '</h3>';

            echo '<div style="max-height:250px; overflow-y:auto; padding:12px; border:1px solid #dcdcde; background:#f6f7f7; white-space:pre-wrap;">';
            echo esc_html((string) $version['terms_and_conditions']);
            echo '</div>';
        }

        if (!empty($version['cancellation_terms'])) {
            echo '<h3>';
            echo esc_html__('Cancellation Terms', 'blue-sheep-business-manager');
            echo '</h3>';

            echo '<div style="max-height:180px; overflow-y:auto; padding:12px; border:1px solid #dcdcde; background:#f6f7f7; white-space:pre-wrap;">';
            echo esc_html((string) $version['cancellation_terms']);
            echo '</div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';

        echo '<input type="hidden" name="action" value="bsbm_record_quotation_acceptance">';
        echo '<input type="hidden" name="quotation_id" value="' . esc_attr((string) $quotation_id) . '">';
        echo '<input type="hidden" name="version_id" value="' . esc_attr((string) $version_id) . '">';

        wp_nonce_field(
            \BlueSheep\BusinessManager\Modules\Quotations\QuotationAcceptanceHandler::NONCE_ACTION
        );

        echo '<table class="form-table"><tbody>';

        echo '<tr>';
        echo '<th scope="row">';
        echo '<label for="bsbm-acceptance-reason">';
        echo esc_html__('Acceptance Evidence / Reason', 'blue-sheep-business-manager');
        echo '</label>';
        echo '</th>';
        echo '<td>';

        echo '<textarea name="acceptance_reason" id="bsbm-acceptance-reason" rows="4" class="large-text" maxlength="5000" required></textarea>';

        echo '<p class="description">';
        echo esc_html__(
            'Record how the customer confirmed acceptance, such as the date of their email confirmation or a reference to their written confirmation. Do not enter passwords or access tokens.',
            'blue-sheep-business-manager'
        );
        echo '</p>';

        echo '</td>';
        echo '</tr>';

        echo '</tbody></table>';

        echo '<p>';
        echo '<label>';
        echo '<input type="checkbox" name="confirm_acceptance" value="1" required> ';
        echo esc_html__(
            'I confirm that the customer has accepted this exact quotation version and its terms.',
            'blue-sheep-business-manager'
        );
        echo '</label>';
        echo '</p>';

        echo '<p>';
        echo '<button type="submit" class="button button-primary">';
        echo esc_html__('Record Customer Acceptance', 'blue-sheep-business-manager');
        echo '</button>';
        echo '</p>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Render the booking conversion form for an accepted quotation.
     *
     * Only the current accepted quotation version can be converted.
     * Existing bookings are displayed instead of showing another form.
     */
    private function renderBookingConversionForm(
        array $quotation,
        array $version
    ): void {
        $quotation_id = absint($quotation['id'] ?? 0);
        $version_id = absint($version['id'] ?? 0);

        echo '<h2 style="margin-top:30px;">';
        echo esc_html__('Booking Conversion', 'blue-sheep-business-manager');
        echo '</h2>';

        if (
            $quotation_id <= 0
            || $version_id <= 0
            || absint($quotation['current_version_id'] ?? 0) !== $version_id
        ) {
            $this->renderInfo(
                __('Booking conversion is only available for the current quotation version.', 'blue-sheep-business-manager')
            );
            return;
        }

        if (($version['status'] ?? '') !== 'accepted') {
            $this->renderInfo(
                __('The quotation must be accepted before it can be converted into a booking.', 'blue-sheep-business-manager')
            );
            return;
        }

        if (!$this->tableExists('bs_bookings')) {
            $this->renderError(
                __('Booking data is unavailable because the bookings table does not exist.', 'blue-sheep-business-manager')
            );
            return;
        }

        if (!class_exists(
            \BlueSheep\BusinessManager\Modules\Bookings\BookingRepository::class
        )) {
            $this->renderError(
                __('The booking repository is unavailable.', 'blue-sheep-business-manager')
            );
            return;
        }

        $booking_repository =
            new \BlueSheep\BusinessManager\Modules\Bookings\BookingRepository();

        $existing_booking = $booking_repository->getByQuotationId(
            $quotation_id
        );

        if ($existing_booking) {
            echo '<div style="max-width:900px; padding:18px; margin-top:12px; border:1px solid #c3c4c7; background:#fff;">';

            echo '<p><strong>';
            echo esc_html__('A booking already exists for this quotation.', 'blue-sheep-business-manager');
            echo '</strong></p>';

            echo '<table class="widefat striped"><tbody>';

            echo '<tr><th>';
            echo esc_html__('Booking Reference', 'blue-sheep-business-manager');
            echo '</th><td>';
            echo esc_html((string) ($existing_booking['booking_reference'] ?? ''));
            echo '</td></tr>';

            echo '<tr><th>';
            echo esc_html__('Booking Status', 'blue-sheep-business-manager');
            echo '</th><td>';
            echo esc_html((string) ($existing_booking['status'] ?? ''));
            echo '</td></tr>';

            echo '<tr><th>';
            echo esc_html__('Travel Dates', 'blue-sheep-business-manager');
            echo '</th><td>';
            echo esc_html((string) ($existing_booking['travel_start_date'] ?? ''));
            echo ' - ';
            echo esc_html((string) ($existing_booking['travel_end_date'] ?? ''));
            echo '</td></tr>';

            echo '<tr><th>';
            echo esc_html__('Travellers', 'blue-sheep-business-manager');
            echo '</th><td>';
            echo esc_html((string) ($existing_booking['traveller_count'] ?? ''));
            echo '</td></tr>';

            echo '</tbody></table>';

            echo '</div>';

            return;
        }

        echo '<div style="max-width:900px; padding:18px; margin-top:12px; border:1px solid #c3c4c7; background:#fff;">';

        echo '<p>';
        echo esc_html__(
            'This quotation has been accepted. Verify the actual travel dates and traveller count before creating the booking. Creating a booking does not automatically confirm it.',
            'blue-sheep-business-manager'
        );
        echo '</p>';

        echo '<p><strong>';
        echo esc_html__('Accepted quotation version:', 'blue-sheep-business-manager');
        echo ' ';
        echo esc_html((string) ($version['version_number'] ?? ''));
        echo '</strong></p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';

        echo '<input type="hidden" name="action" value="bsbm_convert_accepted_quotation">';
        echo '<input type="hidden" name="quotation_id" value="' . esc_attr((string) $quotation_id) . '">';

        wp_nonce_field(
            \BlueSheep\BusinessManager\Modules\Bookings\BookingConversionHandler::NONCE_ACTION
        );

        echo '<table class="form-table"><tbody>';

        echo '<tr>';
        echo '<th scope="row"><label for="bsbm-booking-start-date">';
        echo esc_html__('Travel Start Date', 'blue-sheep-business-manager');
        echo '</label></th>';
        echo '<td><input type="date" name="travel_start_date" id="bsbm-booking-start-date" class="regular-text" required></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="bsbm-booking-end-date">';
        echo esc_html__('Travel End Date', 'blue-sheep-business-manager');
        echo '</label></th>';
        echo '<td><input type="date" name="travel_end_date" id="bsbm-booking-end-date" class="regular-text" required></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="bsbm-booking-travellers">';
        echo esc_html__('Confirmed Traveller Count', 'blue-sheep-business-manager');
        echo '</label></th>';
        echo '<td><input type="number" name="traveller_count" id="bsbm-booking-travellers" class="small-text" min="1" step="1" required></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="bsbm-booking-reason">';
        echo esc_html__('Administrative Notes', 'blue-sheep-business-manager');
        echo '</label></th>';
        echo '<td>';
        echo '<textarea name="reason" id="bsbm-booking-reason" rows="3" class="large-text" maxlength="5000"></textarea>';
        echo '<p class="description">';
        echo esc_html__('Optional internal notes about the booking conversion.', 'blue-sheep-business-manager');
        echo '</p>';
        echo '</td>';
        echo '</tr>';

        echo '</tbody></table>';

        echo '<p><label>';
        echo '<input type="checkbox" name="confirm_booking_details" value="1" required> ';
        echo esc_html__(
            'I confirm that the travel dates and traveller count above have been verified.',
            'blue-sheep-business-manager'
        );
        echo '</label></p>';

        echo '<p>';
        echo '<button type="submit" class="button button-primary">';
        echo esc_html__('Convert Accepted Quotation to Booking', 'blue-sheep-business-manager');
        echo '</button>';
        echo '</p>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Display the audit history for manually dispatched quotation versions.
     */
    private function renderDispatchHistory(int $quotation_id): void
    {
        echo '<h2 style="margin-top:30px;">' . esc_html__('Manual Dispatch History', 'blue-sheep-business-manager') . '</h2>';

        if (!$this->tableExists('bs_activity_log')) {
            $this->renderInfo(__('Dispatch history is unavailable because the activity log table does not exist.', 'blue-sheep-business-manager'));
            return;
        }

        $table = $this->wpdb->prefix . 'bs_activity_log';
        $users_table = $this->wpdb->users;

        $history = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT log.description, log.created_at, log.user_id, users.display_name
                 FROM {$table} AS log
                 LEFT JOIN {$users_table} AS users ON users.ID = log.user_id
                 WHERE log.entity_type = %s
                   AND log.entity_id = %d
                   AND log.action = %s
                 ORDER BY log.created_at DESC",
                'quotation',
                $quotation_id,
                'quotation_manually_dispatched'
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            $this->renderError(__('The quotation dispatch history could not be loaded.', 'blue-sheep-business-manager'));
            return;
        }

        if (empty($history)) {
            $this->renderInfo(__('No manual dispatches have been recorded for this quotation.', 'blue-sheep-business-manager'));
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped" style="max-width:1100px;">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Date and Time', 'blue-sheep-business-manager') . '</th>';
        echo '<th scope="col">' . esc_html__('Administrator', 'blue-sheep-business-manager') . '</th>';
        echo '<th scope="col">' . esc_html__('Dispatch Record', 'blue-sheep-business-manager') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($history as $record) {
            $timestamp = $record['created_at'] ?? '';
            $administrator = $record['display_name'] ?? '';

            if ($administrator === '') {
                $administrator = sprintf(
                    __('User #%d', 'blue-sheep-business-manager'),
                    absint($record['user_id'] ?? 0)
                );
            }

            echo '<tr>';
            echo '<td>' . esc_html($timestamp ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : __('Not available', 'blue-sheep-business-manager')) . '</td>';
            echo '<td>' . esc_html($administrator) . '</td>';
            echo '<td>' . esc_html($record['description'] ?? '') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderQuotationSummary(array $quotation): void
    {
        echo '<h2>' . esc_html__('Quotation Information', 'blue-sheep-business-manager') . '</h2>';
        echo '<table class="widefat striped" style="max-width:900px;"><tbody>';

        $fields = [
            __('Reference Number', 'blue-sheep-business-manager') => $quotation['reference_number'] ?? '',
            __('Customer', 'blue-sheep-business-manager') => $quotation['customer_name'] ?? '',
            __('Customer Email', 'blue-sheep-business-manager') => $quotation['customer_email'] ?? '',
            __('Status', 'blue-sheep-business-manager') => $quotation['status'] ?? '',
            __('Created', 'blue-sheep-business-manager') => $quotation['created_at'] ?? '',
            __('Last Updated', 'blue-sheep-business-manager') => $quotation['updated_at'] ?? '',
        ];

        foreach ($fields as $label => $value) {
            echo '<tr><th style="width:220px;">' . esc_html($label) . '</th>';
            echo '<td>' . esc_html($value !== '' ? (string) $value : 'Not available') . '</td></tr>';
        }

        echo '</tbody></table>';
    }

    private function renderVersionDetails(array $version): void
    {
        echo '<h2 style="margin-top:25px;">' . esc_html__('Current Version', 'blue-sheep-business-manager') . '</h2>';
        echo '<table class="widefat striped" style="max-width:900px;"><tbody>';

        $fields = [
            __('Version Number', 'blue-sheep-business-manager') => $version['version_number'] ?? '',
            __('Version Status', 'blue-sheep-business-manager') => $version['status'] ?? '',
            __('Currency', 'blue-sheep-business-manager') => $version['currency'] ?? '',
            __('Subtotal', 'blue-sheep-business-manager') => $version['subtotal'] ?? '0.00',
            __('Discount', 'blue-sheep-business-manager') => $version['discount'] ?? '0.00',
            __('Total Amount', 'blue-sheep-business-manager') => $version['total_amount'] ?? '0.00',
            __('Valid Until', 'blue-sheep-business-manager') => $version['valid_until'] ?? '',
        ];

        foreach ($fields as $label => $value) {
            echo '<tr><th style="width:220px;">' . esc_html($label) . '</th>';
            echo '<td>' . esc_html($value !== '' ? (string) $value : 'Not available') . '</td></tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Render item table, separate edit panel and add form.
     */
    private function renderItems(array $quotation, array $version): void
    {
        $items = $version['items'] ?? [];
        $version_id = absint($version['id'] ?? 0);
        $quotation_id = absint($quotation['id'] ?? 0);
        $is_draft = ($version['status'] ?? '') === 'draft'
            && absint($quotation['current_version_id'] ?? 0) === $version_id;

        echo '<h2 style="margin-top:25px;">' . esc_html__('Quotation Cost Items', 'blue-sheep-business-manager') . '</h2>';
        echo '<p>' . esc_html__('Add the services and costs included in this quotation. Itinerary descriptions and day-by-day travel details will be managed separately.', 'blue-sheep-business-manager') . '</p>';

        if (empty($items)) {
            $this->renderInfo(__('No cost items have been added to this quotation version.', 'blue-sheep-business-manager'));
        } else {
            $this->renderItemsTable($items, $quotation_id, $version_id, $is_draft);
        }

        $this->renderTotals($version);

        if (!$is_draft) {
            $this->renderInfo(__('Only the current draft quotation version can be edited.', 'blue-sheep-business-manager'));
            return;
        }

        $edit_item_id = isset($_GET['edit_item']) ? absint($_GET['edit_item']) : 0;

        if ($edit_item_id > 0) {
            $selected_item = null;

            foreach ($items as $item) {
                if (absint($item['id'] ?? 0) === $edit_item_id) {
                    $selected_item = $item;
                    break;
                }
            }

            if ($selected_item) {
                $this->renderEditItemForm($quotation_id, $version_id, $selected_item);
            } else {
                $this->renderError(__('The selected quotation item could not be found.', 'blue-sheep-business-manager'));
            }
        } else {
            $this->renderAddItemForm($quotation_id, $version_id);
        }
    }

    private function renderItemsTable(
        array $items,
        int $quotation_id,
        int $version_id,
        bool $is_draft
    ): void {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';

        $headers = [
            __('Day', 'blue-sheep-business-manager'),
            __('Type', 'blue-sheep-business-manager'),
            __('Item', 'blue-sheep-business-manager'),
            __('Description', 'blue-sheep-business-manager'),
            __('Quantity', 'blue-sheep-business-manager'),
            __('Unit Amount', 'blue-sheep-business-manager'),
            __('Total', 'blue-sheep-business-manager'),
        ];

        if ($is_draft) {
            $headers[] = __('Actions', 'blue-sheep-business-manager');
        }

        foreach ($headers as $header) {
            echo '<th scope="col">' . esc_html($header) . '</th>';
        }

        echo '</tr></thead><tbody>';

        foreach ($items as $item) {
            $item_id = absint($item['id'] ?? 0);

            echo '<tr>';
            echo '<td>' . esc_html(!empty($item['day_number']) ? (string) $item['day_number'] : '-') . '</td>';
            echo '<td>' . esc_html(ucwords(str_replace('_', ' ', $item['item_type'] ?? ''))) . '</td>';
            echo '<td><strong>' . esc_html($item['title'] ?? '') . '</strong></td>';
            echo '<td>' . esc_html($item['description'] ?? '') . '</td>';
            echo '<td>' . esc_html($item['quantity'] ?? '0') . '</td>';
            echo '<td>' . esc_html($item['unit_amount'] ?? '0.00') . '</td>';
            echo '<td>' . esc_html($item['total_amount'] ?? '0.00') . '</td>';

            if ($is_draft) {
                $edit_url = add_query_arg([
                    'page' => 'bsbm-quotations',
                    'view' => $quotation_id,
                    'edit_item' => $item_id,
                ], admin_url('admin.php'));

                echo '<td><a class="button button-small" href="' . esc_url($edit_url) . '">';
                echo esc_html__('Edit', 'blue-sheep-business-manager');
                echo '</a></td>';
            }

            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderAddItemForm(int $quotation_id, int $version_id): void
    {
        echo '<h3 style="margin-top:25px;">' . esc_html__('Add Cost Item', 'blue-sheep-business-manager') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $this->renderItemFormSecurity($quotation_id, $version_id, 'add');
        $this->renderItemFields();

        echo '<p><button type="submit" class="button button-primary">';
        echo esc_html__('Add Item', 'blue-sheep-business-manager');
        echo '</button></p></form>';
    }

    private function renderEditItemForm(
        int $quotation_id,
        int $version_id,
        array $item
    ): void {
        echo '<div class="bsbm-edit-item-panel" style="max-width:900px; margin-top:25px; padding:20px; border:1px solid #c3c4c7; background:#fff;">';
        echo '<h3>' . esc_html__('Edit Quotation Item', 'blue-sheep-business-manager') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $this->renderItemFormSecurity($quotation_id, $version_id, 'update');
        echo '<input type="hidden" name="item_id" value="' . esc_attr((string) absint($item['id'] ?? 0)) . '">';
        $this->renderItemFields($item);

        echo '<p><button type="submit" class="button button-primary">';
        echo esc_html__('Save Changes', 'blue-sheep-business-manager');
        echo '</button> ';

        $cancel_url = $this->quotationUrl($quotation_id);
        echo '<a class="button" href="' . esc_url($cancel_url) . '">';
        echo esc_html__('Cancel', 'blue-sheep-business-manager');
        echo '</a></p></form>';

        $this->renderDeleteItemForm($quotation_id, $version_id, absint($item['id'] ?? 0));
        echo '</div>';
    }

    private function renderDeleteItemForm(
        int $quotation_id,
        int $version_id,
        int $item_id
    ): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px;">';
        $this->renderItemFormSecurity($quotation_id, $version_id, 'delete');
        echo '<input type="hidden" name="item_id" value="' . esc_attr((string) $item_id) . '">';
        echo '<button type="submit" class="button button-link-delete" onclick="return confirm(\'Remove this quotation item?\');">';
        echo esc_html__('Remove Item', 'blue-sheep-business-manager');
        echo '</button></form>';
    }

    private function renderItemFormSecurity(
        int $quotation_id,
        int $version_id,
        string $action
    ): void {
        echo '<input type="hidden" name="action" value="bsbm_manage_quotation_item">';
        wp_nonce_field(
            'bsbm_manage_quotation_items_' . $version_id,
            'bsbm_item_nonce'
        );

        echo '<input type="hidden" name="quotation_id" value="' . esc_attr((string) $quotation_id) . '">';
        echo '<input type="hidden" name="version_id" value="' . esc_attr((string) $version_id) . '">';
        echo '<input type="hidden" name="bsbm_item_action" value="' . esc_attr($action) . '">';
    }

    private function renderItemFields(array $item = []): void
    {
        $item_type = $item['item_type'] ?? 'accommodation';
        $day_number = $item['day_number'] ?? '';
        $title = $item['title'] ?? '';
        $description = $item['description'] ?? '';
        $quantity = $item['quantity'] ?? '1.00';
        $unit_amount = $item['unit_amount'] ?? '0.0000';
        $sort_order = $item['sort_order'] ?? '0';

        $types = [
            'accommodation' => __('Accommodation', 'blue-sheep-business-manager'),
            'transport' => __('Transport', 'blue-sheep-business-manager'),
            'activity' => __('Activity', 'blue-sheep-business-manager'),
            'guide' => __('Guide', 'blue-sheep-business-manager'),
            'meal' => __('Meal', 'blue-sheep-business-manager'),
            'other' => __('Other', 'blue-sheep-business-manager'),
        ];

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="bsbm-item-type">' . esc_html__('Item Type', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<select name="item[item_type]" id="bsbm-item-type" required>';

        foreach ($types as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($item_type, $value, false) . '>';
            echo esc_html($label) . '</option>';
        }

        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="bsbm-item-title">' . esc_html__('Title', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<input type="text" name="item[title]" id="bsbm-item-title" value="' . esc_attr($title) . '" maxlength="190" class="regular-text" required>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bsbm-item-description">' . esc_html__('Description', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<textarea name="item[description]" id="bsbm-item-description" rows="3" class="large-text">' . esc_textarea($description) . '</textarea>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bsbm-item-day">' . esc_html__('Itinerary Day', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<input type="number" name="item[day_number]" id="bsbm-item-day" min="1" step="1" value="' . esc_attr((string) $day_number) . '" class="small-text">';
        echo '<p class="description">' . esc_html__('Optional. Associate this cost with an itinerary day.', 'blue-sheep-business-manager') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bsbm-item-quantity">' . esc_html__('Quantity', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<input type="number" name="item[quantity]" id="bsbm-item-quantity" min="0.01" step="0.01" value="' . esc_attr((string) $quantity) . '" required>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bsbm-item-unit-amount">' . esc_html__('Unit Amount', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<input type="number" name="item[unit_amount]" id="bsbm-item-unit-amount" min="0" step="0.0001" value="' . esc_attr((string) $unit_amount) . '" required>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bsbm-item-sort-order">' . esc_html__('Display Order', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<input type="number" name="item[sort_order]" id="bsbm-item-sort-order" min="0" step="1" value="' . esc_attr((string) $sort_order) . '" class="small-text">';
        echo '</td></tr>';
        echo '</tbody></table>';
    }

    /**
     * Render the day-by-day itinerary for the current quotation version.
     */
    private function renderItinerary(array $quotation, array $version): void
    {
        $quotation_id = absint($quotation['id'] ?? 0);
        $version_id = absint($version['id'] ?? 0);
        $is_draft = ($version['status'] ?? '') === 'draft'
            && absint($quotation['current_version_id'] ?? 0) === $version_id;

        echo '<h2 style="margin-top:30px;">' . esc_html__('Tour Itinerary', 'blue-sheep-business-manager') . '</h2>';
        echo '<p>' . esc_html__('Build the customer-facing travel schedule one day at a time. Add financial charges separately under Quotation Cost Items.', 'blue-sheep-business-manager') . '</p>';

        if (!$this->tableExists('bs_quotation_itinerary_days')) {
            $this->renderInfo(__('The itinerary table has not been created yet. Apply the approved local itinerary migration before adding itinerary days. No migration is run automatically from this page.', 'blue-sheep-business-manager'));
            return;
        }

        $days = $this->itinerary_service->getDays($version_id);

        if (is_wp_error($days)) {
            $this->renderError($days->get_error_message());
            return;
        }

        if (empty($days)) {
            $this->renderInfo(__('No itinerary days have been added to this quotation version.', 'blue-sheep-business-manager'));
        } else {
            $this->renderItineraryTable($days, $quotation_id, $version_id, $is_draft);
        }

        if (!$is_draft) {
            $this->renderInfo(__('This itinerary is read-only because only the current draft quotation version can be edited.', 'blue-sheep-business-manager'));
            return;
        }

        $edit_day_id = isset($_GET['edit_day']) ? absint($_GET['edit_day']) : 0;

        if ($edit_day_id > 0) {
            $selected_day = $this->itinerary_service->getDay($version_id, $edit_day_id);

            if (is_wp_error($selected_day)) {
                $this->renderError($selected_day->get_error_message());
            } elseif (!is_array($selected_day)) {
                $this->renderError(__('The selected itinerary day could not be found.', 'blue-sheep-business-manager'));
            } else {
                $this->renderEditItineraryDayForm($quotation_id, $version_id, $selected_day);
            }
        } else {
            $this->renderAddItineraryDayForm($quotation_id, $version_id, $days);
        }
    }

    private function renderItineraryTable(
        array $days,
        int $quotation_id,
        int $version_id,
        bool $is_draft
    ): void {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';

        $headers = [
            __('Day', 'blue-sheep-business-manager'),
            __('Date', 'blue-sheep-business-manager'),
            __('Title and Route', 'blue-sheep-business-manager'),
            __('Activities', 'blue-sheep-business-manager'),
            __('Travel Time', 'blue-sheep-business-manager'),
            __('Overnight', 'blue-sheep-business-manager'),
            __('Meals', 'blue-sheep-business-manager'),
        ];

        if ($is_draft) {
            $headers[] = __('Actions', 'blue-sheep-business-manager');
        }

        foreach ($headers as $header) {
            echo '<th scope="col">' . esc_html($header) . '</th>';
        }

        echo '</tr></thead><tbody>';

        foreach ($days as $day) {
            $day_id = absint($day['id'] ?? 0);
            $activities = $this->decodeActivities($day['activities'] ?? '');
            $travel_minutes = $day['estimated_travel_minutes'] ?? null;

            $travel_label = ($travel_minutes === null || $travel_minutes === '')
                ? '-'
                : $this->formatTravelDuration((int) $travel_minutes);

            echo '<tr>';
            echo '<td>' . esc_html((string) ($day['day_number'] ?? '')) . '</td>';
            echo '<td>' . esc_html(!empty($day['itinerary_date']) ? (string) $day['itinerary_date'] : '-') . '</td>';
            echo '<td><strong>' . esc_html($day['title'] ?? '') . '</strong>';

            $start = trim((string) ($day['start_location'] ?? ''));
            $end = trim((string) ($day['end_location'] ?? ''));

            if ($start !== '' || $end !== '') {
                echo '<br><span>' . esc_html($start !== '' ? $start : '?') . ' &rarr; ' . esc_html($end !== '' ? $end : '?') . '</span>';
            }

            if (!empty($day['route_description'])) {
                echo '<br><span>' . esc_html($day['route_description']) . '</span>';
            }

            if (!empty($day['description'])) {
                echo '<br><small>' . esc_html($day['description']) . '</small>';
            }

            echo '</td>';
            echo '<td>';

            if (empty($activities)) {
                echo '-';
            } else {
                echo '<ul style="margin:0 0 0 18px;">';

                foreach ($activities as $activity) {
                    echo '<li>' . esc_html($activity) . '</li>';
                }

                echo '</ul>';
            }

            echo '</td>';
            echo '<td>' . esc_html($travel_label) . '</td>';
            echo '<td>' . esc_html(!empty($day['overnight_location']) ? (string) $day['overnight_location'] : '-') . '</td>';
            echo '<td>' . esc_html(!empty($day['meal_arrangements']) ? (string) $day['meal_arrangements'] : '-') . '</td>';

            if ($is_draft) {
                $edit_url = add_query_arg([
                    'page' => 'bsbm-quotations',
                    'view' => $quotation_id,
                    'edit_day' => $day_id,
                ], admin_url('admin.php'));

                echo '<td><a class="button button-small" href="' . esc_url($edit_url) . '">';
                echo esc_html__('Edit', 'blue-sheep-business-manager') . '</a></td>';
            }

            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderAddItineraryDayForm(
        int $quotation_id,
        int $version_id,
        array $existing_days
    ): void {
        $next_day = 1;

        foreach ($existing_days as $existing_day) {
            $next_day = max($next_day, absint($existing_day['day_number'] ?? 0) + 1);
        }

        echo '<h3 style="margin-top:25px;">' . esc_html__('Add Itinerary Day', 'blue-sheep-business-manager') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $this->renderItineraryFormSecurity($quotation_id, $version_id, 'add');

        $this->renderItineraryFields([
            'day_number' => $next_day,
            'sort_order' => count($existing_days),
        ]);

        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Add Itinerary Day', 'blue-sheep-business-manager') . '</button></p>';
        echo '</form>';
    }

    private function renderEditItineraryDayForm(
        int $quotation_id,
        int $version_id,
        array $day
    ): void {
        echo '<div class="bsbm-edit-itinerary-panel" style="max-width:900px; margin-top:25px; padding:20px; border:1px solid #c3c4c7; background:#fff;">';
        echo '<h3>' . esc_html__('Edit Itinerary Day', 'blue-sheep-business-manager') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $this->renderItineraryFormSecurity($quotation_id, $version_id, 'update');
        echo '<input type="hidden" name="day_id" value="' . esc_attr((string) absint($day['id'] ?? 0)) . '">';
        $this->renderItineraryFields($day);

        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Itinerary Day', 'blue-sheep-business-manager') . '</button> ';
        echo '<a class="button" href="' . esc_url($this->quotationUrl($quotation_id)) . '">' . esc_html__('Cancel', 'blue-sheep-business-manager') . '</a></p>';
        echo '</form>';

        $this->renderDeleteItineraryDayForm($quotation_id, $version_id, absint($day['id'] ?? 0));
        echo '</div>';
    }

    private function renderDeleteItineraryDayForm(
        int $quotation_id,
        int $version_id,
        int $day_id
    ): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px;">';
        $this->renderItineraryFormSecurity($quotation_id, $version_id, 'delete');
        echo '<input type="hidden" name="day_id" value="' . esc_attr((string) $day_id) . '">';
        echo '<button type="submit" class="button button-link-delete" onclick="return confirm(\'Remove this itinerary day?\');">';
        echo esc_html__('Remove Itinerary Day', 'blue-sheep-business-manager') . '</button></form>';
    }

    private function renderItineraryFormSecurity(
        int $quotation_id,
        int $version_id,
        string $action
    ): void {
        echo '<input type="hidden" name="action" value="bsbm_manage_quotation_itinerary">';
        wp_nonce_field(
            'bsbm_manage_quotation_itinerary_' . $version_id,
            'bsbm_itinerary_nonce'
        );

        echo '<input type="hidden" name="quotation_id" value="' . esc_attr((string) $quotation_id) . '">';
        echo '<input type="hidden" name="version_id" value="' . esc_attr((string) $version_id) . '">';
        echo '<input type="hidden" name="bsbm_itinerary_action" value="' . esc_attr($action) . '">';
    }

    private function renderItineraryFields(array $day = []): void
    {
        $activities = $this->decodeActivities($day['activities'] ?? '');

        $values = [
            'day_number' => $day['day_number'] ?? 1,
            'itinerary_date' => $day['itinerary_date'] ?? '',
            'title' => $day['title'] ?? '',
            'start_location' => $day['start_location'] ?? '',
            'end_location' => $day['end_location'] ?? '',
            'route_description' => $day['route_description'] ?? '',
            'estimated_travel_minutes' => $day['estimated_travel_minutes'] ?? '',
            'activities' => implode("\n", $activities),
            'overnight_location' => $day['overnight_location'] ?? '',
            'meal_arrangements' => $day['meal_arrangements'] ?? '',
            'description' => $day['description'] ?? '',
            'sort_order' => $day['sort_order'] ?? 0,
        ];

        echo '<table class="form-table"><tbody>';

        $this->renderItineraryInputRow('day_number', __('Day Number', 'blue-sheep-business-manager'), $values['day_number'], 'number', 'min="1" step="1" required');
        $this->renderItineraryInputRow('itinerary_date', __('Date', 'blue-sheep-business-manager'), $values['itinerary_date'], 'date');
        $this->renderItineraryInputRow('title', __('Day Title', 'blue-sheep-business-manager'), $values['title'], 'text', 'maxlength="190" required');
        $this->renderItineraryInputRow('start_location', __('Starting Location', 'blue-sheep-business-manager'), $values['start_location'], 'text', 'maxlength="190"');
        $this->renderItineraryInputRow('end_location', __('Destination', 'blue-sheep-business-manager'), $values['end_location'], 'text', 'maxlength="190"');

        echo '<tr><th scope="row"><label for="bsbm-itinerary-route">' . esc_html__('Route Details', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<textarea name="day[route_description]" id="bsbm-itinerary-route" rows="3" class="large-text">' . esc_textarea($values['route_description']) . '</textarea></td></tr>';

        $this->renderItineraryInputRow('estimated_travel_minutes', __('Estimated Travel Time (Minutes)', 'blue-sheep-business-manager'), $values['estimated_travel_minutes'], 'number', 'min="0" step="1');

        echo '<tr><th scope="row"><label for="bsbm-itinerary-activities">' . esc_html__('Activities', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<textarea name="day[activities]" id="bsbm-itinerary-activities" rows="4" class="large-text" placeholder="One activity per line">' . esc_textarea($values['activities']) . '</textarea>';
        echo '<p class="description">' . esc_html__('Enter one activity per line.', 'blue-sheep-business-manager') . '</p></td></tr>';

        $this->renderItineraryInputRow('overnight_location', __('Overnight Location', 'blue-sheep-business-manager'), $values['overnight_location'], 'text', 'maxlength="190"');

        echo '<tr><th scope="row"><label for="bsbm-itinerary-meals">' . esc_html__('Meal Arrangements', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<textarea name="day[meal_arrangements]" id="bsbm-itinerary-meals" rows="2" class="large-text">' . esc_textarea($values['meal_arrangements']) . '</textarea></td></tr>';

        echo '<tr><th scope="row"><label for="bsbm-itinerary-description">' . esc_html__('Day Description', 'blue-sheep-business-manager') . '</label></th><td>';
        echo '<textarea name="day[description]" id="bsbm-itinerary-description" rows="4" class="large-text">' . esc_textarea($values['description']) . '</textarea></td></tr>';

        $this->renderItineraryInputRow('sort_order', __('Display Order', 'blue-sheep-business-manager'), $values['sort_order'], 'number', 'min="0" step="1');

        echo '</tbody></table>';
    }

    private function renderItineraryInputRow(
        string $field,
        string $label,
        $value,
        string $type = 'text',
        string $attributes = ''
    ): void {
        $id = 'bsbm-itinerary-' . str_replace('_', '-', $field);

        echo '<tr><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input type="' . esc_attr($type) . '" name="day[' . esc_attr($field) . ']" id="' . esc_attr($id) . '" value="' . esc_attr((string) $value) . '" class="regular-text" ' . $attributes . '>';
        echo '</td></tr>';
    }

    private function decodeActivities($activities): array
    {
        if (is_array($activities)) {
            return array_values(array_filter(array_map('strval', $activities), 'strlen'));
        }

        if (!is_string($activities) || trim($activities) === '') {
            return [];
        }

        $decoded = json_decode($activities, true);

        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded), 'strlen'));
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $activities)), 'strlen'));
    }

    private function formatTravelDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remaining_minutes = $minutes % 60;

        if ($hours > 0 && $remaining_minutes > 0) {
            return sprintf(__('%1$d hr %2$d min', 'blue-sheep-business-manager'), $hours, $remaining_minutes);
        }

        if ($hours > 0) {
            return sprintf(__('%d hr', 'blue-sheep-business-manager'), $hours);
        }

        return sprintf(__('%d min', 'blue-sheep-business-manager'), $remaining_minutes);
    }

    private function renderTotals(array $version): void
    {
        $currency = sanitize_text_field($version['currency'] ?? '');

        echo '<h3 style="margin-top:20px;">' . esc_html__('Quotation Totals', 'blue-sheep-business-manager') . '</h3>';
        echo '<table class="widefat striped" style="max-width:500px;"><tbody>';

        $totals = [
            __('Subtotal', 'blue-sheep-business-manager') => $version['subtotal'] ?? '0.00',
            __('Discount', 'blue-sheep-business-manager') => $version['discount'] ?? '0.00',
            __('Total Amount', 'blue-sheep-business-manager') => $version['total_amount'] ?? '0.00',
        ];

        foreach ($totals as $label => $amount) {
            echo '<tr><th>' . esc_html($label) . '</th>';
            echo '<td>' . esc_html(trim($currency . ' ' . number_format((float) $amount, 2))) . '</td></tr>';
        }

        echo '</tbody></table>';
    }

    private function quotationUrl(int $quotation_id): string
    {
        return add_query_arg([
            'page' => 'bsbm-quotations',
            'view' => $quotation_id,
        ], admin_url('admin.php'));
    }

    private function renderSearchForm(string $search): void
    {
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="bsbm-quotations">';
        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="bsbm-quotation-search">';
        echo esc_html__('Search quotations', 'blue-sheep-business-manager');
        echo '</label>';
        echo '<input type="search" id="bsbm-quotation-search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Search reference or customer', 'blue-sheep-business-manager') . '">';
        submit_button(__('Search Quotations', 'blue-sheep-business-manager'), 'button', '', false);
        echo '</p></form>';
    }

    /**
     * Render a quotation table, including an empty-state message.
     */
    private function renderTable(
        array $quotations,
        string $empty_message = ''
    ): void {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';

        $headers = [
            __('Reference', 'blue-sheep-business-manager'),
            __('Customer', 'blue-sheep-business-manager'),
            __('Status', 'blue-sheep-business-manager'),
            __('Currency', 'blue-sheep-business-manager'),
            __('Total', 'blue-sheep-business-manager'),
            __('Created', 'blue-sheep-business-manager'),
            __('Action', 'blue-sheep-business-manager'),
        ];

        foreach ($headers as $header) {
            echo '<th scope="col">' . esc_html($header) . '</th>';
        }

        echo '</tr></thead><tbody>';

        if (empty($quotations)) {
            echo '<tr><td colspan="7">';
            echo esc_html($empty_message ?: __('No quotations found.', 'blue-sheep-business-manager'));
            echo '</td></tr>';
        } else {
            foreach ($quotations as $quotation) {
                $quotation_id = absint($quotation['id'] ?? 0);
                $reference = $quotation['reference_number'] ?? '';
                $customer = $quotation['customer_name'] ?? '';
                $status = $quotation['status'] ?? '';
                $currency = $quotation['currency'] ?? '';
                $total = $quotation['total_amount'] ?? '0.00';
                $created_at = $quotation['created_at'] ?? '';

                echo '<tr>';
                echo '<td><strong>' . esc_html($reference) . '</strong></td>';
                echo '<td>' . esc_html($customer ?: 'Not available') . '</td>';
                echo '<td><span class="bsbm-status bsbm-status-' . esc_attr(sanitize_html_class($status)) . '">';
                echo esc_html(ucwords(str_replace('_', ' ', $status))) . '</span></td>';
                echo '<td>' . esc_html($currency ?: 'Not set') . '</td>';
                echo '<td>' . esc_html($currency ? $currency . ' ' : '') . esc_html(number_format((float) $total, 2)) . '</td>';
                echo '<td>' . esc_html($created_at ? mysql2date(get_option('date_format'), $created_at) : 'Not available') . '</td>';
                echo '<td><a href="' . esc_url($this->quotationUrl($quotation_id)) . '">';
                echo esc_html__('View Details', 'blue-sheep-business-manager') . '</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    /**
     * Render pagination using independent query parameters per table.
     *
     * @param int    $current_page   Current page.
     * @param int    $total_pages    Total pages.
     * @param string $search         Search term.
     * @param string $page_key       Query parameter for this table.
     * @param string $other_page_key Query parameter for the other table.
     * @param int    $other_page     Current page of the other table.
     */
    private function renderPagination(
        int $current_page,
        int $total_pages,
        string $search,
        string $page_key = 'paged',
        string $other_page_key = 'sent_paged',
        int $other_page = 1
    ): void {
        if ($total_pages <= 1) {
            return;
        }

        $base_url = add_query_arg(
            [
                'page' => 'bsbm-quotations',
                's' => $search,
                $page_key => '%#%',
                $other_page_key => max(1, $other_page),
            ],
            admin_url('admin.php')
        );

        echo '<div class="tablenav"><div class="tablenav-pages">';

        echo wp_kses_post(
            paginate_links([
                'base' => $base_url,
                'format' => '',
                'current' => $current_page,
                'total' => $total_pages,
                'prev_text' => __('&laquo; Previous', 'blue-sheep-business-manager'),
                'next_text' => __('Next &raquo;', 'blue-sheep-business-manager'),
            ])
        );

        echo '</div></div>';
    }


    /**
     * Render an informational admin notice.
     *
     * @param string $message Notice message.
     */
    private function renderInfo(string $message): void
    {
        echo '<div class="notice notice-info"><p>';
        echo esc_html($message);
        echo '</p></div>';
    }

    /**
     * Render a success admin notice.
     *
     * @param string $message Notice message.
     */
    private function renderSuccess(string $message): void
    {
        echo '<div class="notice notice-success is-dismissible"><p>';
        echo esc_html($message);
        echo '</p></div>';
    }

    /**
     * Render an error admin notice.
     *
     * @param string $message Notice message.
     */
    private function renderError(string $message): void
    {
        echo '<div class="notice notice-error"><p>';
        echo esc_html($message);
        echo '</p></div>';
    }
}