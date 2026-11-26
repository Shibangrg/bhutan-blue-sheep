<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generates quotation PDF documents using Dompdf.
 */
final class QuotationPdfGenerator
{
    private QuotationPdfTemplate $template;

    /**
     * Initialize the PDF generator.
     */
    public function __construct(
        ?QuotationPdfTemplate $template = null
    ) {
        $this->template = $template ?? new QuotationPdfTemplate();
    }

    /**
     * Generate a PDF from prepared quotation data.
     *
     * Returns the generated PDF as binary data.
     *
     * @param array $data Prepared quotation data.
     *
     * @return string|\WP_Error
     */
    public function generate(array $data): string|\WP_Error
    {
        $html = $this->template->render($data);

        if (is_wp_error($html)) {
            return $html;
        }

        if (!is_string($html) || trim($html) === '') {
            return new \WP_Error(
                'empty_pdf_template',
                'The quotation PDF template produced no content.'
            );
        }

        try {
            $options = new Options();

            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $options->set('isPhpEnabled', false);
            $options->set('isJavascriptEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isFontSubsettingEnabled', true);

            $options->setChroot([
                BSBM_PATH,
            ]);

            $dompdf = new Dompdf($options);

            $dompdf->setPaper('A4', 'portrait');

            $dompdf->loadHtml($html, 'UTF-8');

            $dompdf->render();

            $pdf = $dompdf->output();

            if (
                !is_string($pdf)
                || $pdf === ''
                || !str_starts_with($pdf, '%PDF-')
            ) {
                return new \WP_Error(
                    'pdf_output_invalid',
                    'The PDF generator did not produce a valid PDF document.'
                );
            }

            return $pdf;

        } catch (Throwable $exception) {
            error_log(
                'Blue Sheep Business Manager PDF generation failed: '
                . $exception->getMessage()
            );

            return new \WP_Error(
                'pdf_generation_failed',
                'The quotation PDF could not be generated. Please try again.'
            );
        }
    }
}
