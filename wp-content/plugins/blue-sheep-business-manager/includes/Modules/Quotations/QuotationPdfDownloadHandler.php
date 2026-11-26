<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles secure administrator quotation PDF downloads.
 */
final class QuotationPdfDownloadHandler
{
    private QuotationPdfDataProvider $data_provider;

    private QuotationPdfGenerator $pdf_generator;

    /**
     * Initialize the download handler.
     */
    public function __construct(
        ?QuotationPdfDataProvider $data_provider = null,
        ?QuotationPdfGenerator $pdf_generator = null
    ) {
        $this->data_provider = $data_provider
            ?? new QuotationPdfDataProvider();

        $this->pdf_generator = $pdf_generator
            ?? new QuotationPdfGenerator();
    }

    /**
     * Register the administrator download endpoint.
     */
    public function register(): void
    {
        add_action(
            'admin_post_bsbm_download_quotation_pdf',
            [$this, 'handleDownload']
        );
    }

    /**
     * Generate a secure download URL for a quotation.
     */
    public function getDownloadUrl(int $quotation_id): string
    {
        if ($quotation_id <= 0) {
            return '';
        }

        $url = add_query_arg(
            [
                'action' => 'bsbm_download_quotation_pdf',
                'quotation_id' => $quotation_id,
            ],
            admin_url('admin-post.php')
        );

        return wp_nonce_url(
            $url,
            'bsbm_download_quotation_pdf_' . $quotation_id
        );
    }

    /**
     * Process the PDF download request.
     */
    public function handleDownload(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You are not allowed to download quotations.', 'blue-sheep-business-manager'),
                esc_html__('Permission denied', 'blue-sheep-business-manager'),
                ['response' => 403]
            );
        }

        $quotation_id = isset($_GET['quotation_id'])
            ? absint(wp_unslash($_GET['quotation_id']))
            : 0;

        if ($quotation_id <= 0) {
            wp_die(
                esc_html__('A valid quotation ID is required.', 'blue-sheep-business-manager'),
                esc_html__('Invalid quotation', 'blue-sheep-business-manager'),
                ['response' => 400]
            );
        }

        check_admin_referer(
            'bsbm_download_quotation_pdf_' . $quotation_id
        );

        $data = $this->data_provider->getQuotationPdfData(
            $quotation_id
        );

        if (is_wp_error($data)) {
            wp_die(
                esc_html($data->get_error_message()),
                esc_html__('Quotation PDF error', 'blue-sheep-business-manager'),
                ['response' => 404]
            );
        }

        $pdf = $this->pdf_generator->generate($data);

        if (is_wp_error($pdf)) {
            wp_die(
                esc_html($pdf->get_error_message()),
                esc_html__('PDF generation failed', 'blue-sheep-business-manager'),
                ['response' => 500]
            );
        }

        $reference = sanitize_file_name(
            (string) ($data['quotation']['reference_number'] ?? 'quotation')
        );

        $version_number = absint(
            $data['version']['version_number'] ?? 1
        );

        $filename = sprintf(
            '%s-v%d.pdf',
            $reference !== '' ? $reference : 'quotation',
            max(1, $version_number)
        );

        /*
         * Remove buffered output so the PDF response is not corrupted
         * by previously generated HTML or whitespace.
         */
        while (ob_get_level() > 0) {
            if (!ob_end_clean()) {
                break;
            }
        }

        nocache_headers();

        header('Content-Type: application/pdf');
        header(
            'Content-Disposition: attachment; filename="' . $filename . '"'
        );
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . strlen($pdf));
        header('X-Content-Type-Options: nosniff');

        echo $pdf;
        exit;
    }
}
