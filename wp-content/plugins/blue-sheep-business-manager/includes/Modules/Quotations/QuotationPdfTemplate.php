<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds the HTML document used for quotation PDF generation.
 */
final class QuotationPdfTemplate
{
    /**
     * Render the complete quotation document.
     *
     * @param array $data Prepared quotation data.
     *
     * @return string|WP_Error
     */
    public function render(array $data): string|\WP_Error
    {
        if (
            empty($data['quotation'])
            || empty($data['customer'])
            || empty($data['version'])
            || !isset($data['items'])
            || !isset($data['itinerary'])
            || !isset($data['summary'])
        ) {
            return new \WP_Error(
                'invalid_pdf_data',
                'The quotation data is incomplete.'
            );
        }

        $quotation = $data['quotation'];
        $customer = $data['customer'];
        $version = $data['version'];
        $items = $data['items'];
        $itinerary = $data['itinerary'];
        $summary = $data['summary'];

        $reference = $this->escape(
            $quotation['reference_number'] ?? ''
        );

        $customer_name = $this->escape(
            $customer['full_name'] ?? ''
        );

        $currency = $this->escape(
            $summary['currency'] ?? ''
        );

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Quotation ' . $reference . '</title>
<style>
    @page {
        size: A4;
        margin: 35mm 16mm 24mm 16mm;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 9pt;
        line-height: 1.55;
        color: #263238;
    }

    h1, h2, h3, p {
        margin-top: 0;
    }

    h2 {
        page-break-after: avoid;
        break-after: avoid;
    }

    .section-block {
        page-break-inside: auto;
    }

    .section-intro {
        page-break-after: avoid;
        break-after: avoid;
    }

    .keep-together {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    h1 {
        font-size: 22pt;
        color: #183B32;
        margin-bottom: 5px;
    }

    h2 {
        font-size: 13pt;
        color: #183B32;
        border-bottom: 1px solid #D8E1DC;
        padding-bottom: 6px;
        margin-top: 24px;
        margin-bottom: 12px;
    }

    h3 {
        font-size: 10pt;
        color: #183B32;
        margin-bottom: 5px;
    }

    .header {
        position: fixed;
        top: -27mm;
        left: 0;
        right: 0;
        height: 23mm;
        padding-bottom: 2mm;
        border-bottom: 1.5px solid #183B32;
    }

    .header-table {
        width: 100%;
        height: 21mm;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .brand-logo-cell {
        width: 20mm;
        vertical-align: middle;
        text-align: left;
    }

    .brand-logo {
        display: block;
        width: 17mm;
        height: 17mm;
    }

    .brand-copy {
        vertical-align: middle;
        padding-left: 2mm;
        white-space: nowrap;
    }

    .brand {
        display: block;
        white-space: nowrap;
        font-size: 9.5pt;
        line-height: 1.25;
        font-weight: bold;
        color: #183B32;
    }

    .brand-subtitle {
        display: block;
        margin-top: 2mm;
        white-space: nowrap;
        font-size: 7pt;
        line-height: 1.2;
        color: #64756D;
    }

    .header-reference {
        width: 38mm;
        vertical-align: middle;
        text-align: right;
        font-size: 7.5pt;
        line-height: 1.5;
        color: #52645B;
        white-space: nowrap;
    }

    .footer {
        position: fixed;
        bottom: -15mm;
        left: 0;
        right: 0;
        border-top: 1px solid #D8E1DC;
        padding-top: 6px;
        font-size: 7pt;
        color: #718078;
        text-align: center;
    }

    .document-title {
        margin-bottom: 18px;
    }

    .document-subtitle {
        color: #718078;
        font-size: 9pt;
    }

    .info-table,
    .cost-table,
    .summary-table {
        width: 100%;
        border-collapse: collapse;
    }

    .info-table td {
        width: 50%;
        vertical-align: top;
        padding: 8px 10px;
        background: #F5F8F6;
        border: 4px solid #FFFFFF;
    }

    .field-label {
        font-size: 7pt;
        color: #718078;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .field-value {
        font-size: 9pt;
        font-weight: bold;
        margin-top: 3px;
    }

    .cost-table th {
        background: #183B32;
        color: #FFFFFF;
        font-size: 8pt;
        text-align: left;
        padding: 9px 7px;
    }

    .cost-table td {
        padding: 8px 7px;
        border-bottom: 1px solid #E5EBE7;
        vertical-align: top;
    }

    .cost-table tr {
        page-break-inside: avoid;
    }

    .numeric {
        text-align: right;
        white-space: nowrap;
    }

    .summary-wrapper {
        width: 100%;
        margin-top: 12px;
    }

    .summary-table {
        width: 55%;
        margin-left: auto;
    }

    .summary-table td {
        padding: 5px 7px;
        text-align: right;
    }

    .summary-total td {
        border-top: 2px solid #183B32;
        font-size: 12pt;
        font-weight: bold;
        color: #183B32;
        padding-top: 9px;
    }

    .itinerary-day {
        border: 1px solid #DCE5DF;
        margin-bottom: 14px;
        padding: 12px;
        page-break-inside: auto;
    }

    .day-heading {
        page-break-after: avoid;
        break-after: avoid;
    }

    .day-heading {
        background: #F0F5F1;
        padding: 8px 10px;
        margin: -12px -12px 10px;
    }

    .day-number {
        font-size: 8pt;
        color: #718078;
        text-transform: uppercase;
    }

    .day-title {
        font-size: 12pt;
        font-weight: bold;
        color: #183B32;
    }

    .detail-row {
        margin-bottom: 6px;
    }

    .detail-label {
        font-weight: bold;
        color: #52645B;
    }

    .activities {
        margin: 5px 0 8px 17px;
        padding: 0;
    }

    .activities li {
        margin-bottom: 3px;
    }

    .terms {
        background: #F5F8F6;
        padding: 12px;
        margin-top: 8px;
        page-break-inside: auto;
    }

    .terms h3 {
        page-break-after: avoid;
        break-after: avoid;
    }

    .terms p {
        margin-bottom: 0;
        orphans: 3;
        widows: 3;
    }

    .terms-content {
        white-space: normal;
    }

    .terms-empty {
        color: #718078;
        font-style: italic;
        border: 1px dashed #C8D5CD;
        padding: 10px;
    }

    .muted {
        color: #718078;
    }

    .empty-state {
        color: #718078;
        font-style: italic;
        padding: 10px 0;
    }

    .section-note {
        color: #718078;
        font-size: 8pt;
    }

    table {
        page-break-inside: auto;
    }
</style>
</head>
<body>';

        $html .= $this->renderHeader($reference);

        $html .= '
<main>
    <div class="document-title keep-together">
        <h1>Travel Quotation</h1>
        <div class="document-subtitle">
            Customized travel proposal and itinerary
        </div>
    </div>';

        $html .= $this->renderCustomerDetails(
            $customer,
            $quotation,
            $version
        );

        $html .= $this->renderCostItems(
            $items,
            $summary,
            $currency
        );

        $html .= $this->renderItinerary($itinerary);

        $html .= $this->renderTerms($version);

        $html .= '
</main>
<div class="footer">
    Bhutan Blue Sheep Tours &amp; Travels
    <br>
    Journeys through Bhutan
</div>
</body>
</html>';

        return $html;
    }

    /**
     * Render the fixed document header.
     */
    private function renderHeader(string $reference): string
    {
        $logo = $this->getCompanyLogoDataUri();

        return '
<div class="header">
    <table class="header-table">
        <tr>
            <td class="brand-logo-cell">
                <img class="brand-logo" src="' . esc_attr($logo) . '" alt="Bhutan Blue Sheep Tours &amp; Travels logo">
            </td>
            <td class="brand-copy">
                <span class="brand">Bhutan Blue Sheep Tours &amp; Travels</span>
                <span class="brand-subtitle">Bhutan Travel Experiences</span>
            </td>
            <td class="header-reference">
                <strong>QUOTATION</strong><br>
                Reference: ' . $reference . '
            </td>
        </tr>
    </table>
</div>';
    }

    /**
     * Return the official company logo embedded in this template.
     *
     * The image is embedded as a data URI so Dompdf does not need to
     * access the WordPress theme logo setting or fetch a remote image.
     */
    private function getCompanyLogoDataUri(): string
    {
        $logo_data = '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';

        return 'data:image/jpeg;base64,' . $logo_data;
    }

    /**
     * Render customer and quotation information.
     */
    private function renderCustomerDetails(
        array $customer,
        array $quotation,
        array $version
    ): string {
        $fields = [
            'Customer Name' => $customer['full_name'] ?? '',
            'Email Address' => $customer['email'] ?? '',
            'Phone Number' => $customer['phone'] ?? '',
            'Country' => $customer['country'] ?? '',
            'Quotation Reference' => $quotation['reference_number'] ?? '',
            'Version' => $version['version_number'] ?? '',
            'Quotation Date' => $quotation['created_at'] ?? '',
            'Valid Until' => $version['valid_until'] ?? '',
        ];

        $html = '<div class="section-block"><h2>Quotation Details</h2><table class="info-table">';

        $field_entries = array_chunk($fields, 2, true);

        foreach ($field_entries as $row) {
            $html .= '<tr>';

            foreach ($row as $label => $value) {
                $html .= '
<td>
    <div class="field-label">'
                    . $this->escape($label) .
                '</div>
    <div class="field-value">'
                    . $this->escape($value !== '' ? $value : 'Not provided') .
                '</div>
</td>';
            }

            if (count($row) === 1) {
                $html .= '<td></td>';
            }

            $html .= '</tr>';
        }

        $html .= '</table></div>';

        return $html;
    }

    /**
     * Render financial quotation items and totals.
     */
    private function renderCostItems(
        array $items,
        array $summary,
        string $currency
    ): string {
        $html = '
<h2>Quotation Cost Breakdown</h2>
<table class="cost-table">
<thead>
<tr>
    <th style="width: 10%;">No.</th>
    <th style="width: 48%;">Description</th>
    <th class="numeric" style="width: 14%;">Quantity</th>
    <th class="numeric" style="width: 14%;">Unit Cost</th>
    <th class="numeric" style="width: 14%;">Total</th>
</tr>
</thead>
<tbody>';

        if (empty($items)) {
            $html .= '
<tr>
    <td colspan="5" class="empty-state">
        No quotation cost items have been added.
    </td>
</tr>';
        } else {
            $counter = 1;

            foreach ($items as $item) {
                $html .= '
<tr>
    <td>' . $counter . '</td>
    <td>
        <strong>' . $this->escape($item['title'] ?? '') . '</strong>
        <br>
        <span class="muted">'
                    . nl2br($this->escape($item['description'] ?? '')) .
                '</span>
    </td>
    <td class="numeric">'
                    . $this->escape($item['quantity'] ?? 0) .
                '</td>
    <td class="numeric">'
                    . $this->formatMoney($item['unit_amount'] ?? 0, $currency) .
                '</td>
    <td class="numeric">'
                    . $this->formatMoney($item['total_amount'] ?? 0, $currency) .
                '</td>
</tr>';

                $counter++;
            }
        }

        $subtotal = $summary['subtotal'] ?? 0;
        $discount = $summary['discount'] ?? 0;
        $total = $summary['total_amount'] ?? 0;

        $html .= '
</tbody>
</table>

<div class="summary-wrapper">
<table class="summary-table">
<tr>
    <td>Subtotal</td>
    <td><strong>'
            . $this->formatMoney($subtotal, $currency) .
        '</strong></td>
</tr>
<tr>
    <td>Discount</td>
    <td><strong>'
            . $this->formatMoney($discount, $currency) .
        '</strong></td>
</tr>
<tr class="summary-total">
    <td>Total</td>
    <td>'
            . $this->formatMoney($total, $currency) .
        '</td>
</tr>';

        if (!empty($summary['deposit_type'])) {
            $html .= '
<tr>
    <td>Deposit</td>
    <td><strong>'
                . $this->escape($summary['deposit_value'] ?? 0)
                . ' ' . $this->escape($summary['deposit_type']) .
            '</strong></td>
</tr>';
        }

        $html .= '</table></div></div>';

        return $html;
    }

    /**
     * Render day-by-day itinerary.
     */
    private function renderItinerary(array $itinerary): string
    {
        $html = '<div class="section-block"><h2>Travel Itinerary</h2>';

        if (empty($itinerary)) {
            return $html . '
<p class="empty-state">
    No itinerary details have been added to this quotation.
</p></div>';
        }

        foreach ($itinerary as $day) {
            $day_number = $this->escape($day['day_number'] ?? '');
            $title = $this->escape($day['title'] ?? '');

            $html .= '
<div class="itinerary-day">
    <div class="day-heading">
        <div class="day-number">Day ' . $day_number . '</div>
        <div class="day-title">' . $title . '</div>
    </div>';

            if (!empty($day['itinerary_date'])) {
                $html .= $this->renderDetail(
                    'Date',
                    $day['itinerary_date']
                );
            }

            if (!empty($day['start_location'])) {
                $html .= $this->renderDetail(
                    'Starting Location',
                    $day['start_location']
                );
            }

            if (!empty($day['end_location'])) {
                $html .= $this->renderDetail(
                    'Ending Location',
                    $day['end_location']
                );
            }

            if (!empty($day['route_description'])) {
                $html .= $this->renderDetail(
                    'Route',
                    $day['route_description']
                );
            }

            if (
                isset($day['estimated_travel_minutes'])
                && $day['estimated_travel_minutes'] !== null
            ) {
                $minutes = (int) $day['estimated_travel_minutes'];

                $html .= $this->renderDetail(
                    'Estimated Travel Time',
                    $minutes . ' minutes'
                );
            }

            if (!empty($day['activities_list'])) {
                $html .= '<div class="detail-row">
<strong>Activities</strong>
<ul class="activities">';

                foreach ($day['activities_list'] as $activity) {
                    $html .= '<li>'
                        . $this->escape($activity)
                        . '</li>';
                }

                $html .= '</ul></div>';
            }

            if (!empty($day['overnight_location'])) {
                $html .= $this->renderDetail(
                    'Overnight Stay',
                    $day['overnight_location']
                );
            }

            if (!empty($day['meal_arrangements'])) {
                $html .= $this->renderDetail(
                    'Meals',
                    $day['meal_arrangements']
                );
            }

            if (!empty($day['description'])) {
                $html .= $this->renderDetail(
                    'Additional Details',
                    $day['description']
                );
            }

            $html .= '</div>';
        }

        return $html . '</div>';
    }

    /**
     * Render an individual itinerary detail.
     */
    private function renderDetail(
        string $label,
        string $value
    ): string {
        return '
<div class="detail-row">
    <span class="detail-label">'
            . $this->escape($label) .
        ':</span>
    ' . nl2br($this->escape($value)) . '
</div>';
    }

    /**
     * Render quotation terms and conditions.
     */
    private function renderTerms(array $version): string
    {
        $cancellation_terms = trim(
            (string) ($version['cancellation_terms'] ?? '')
        );

        $general_terms = trim(
            (string) ($version['terms_and_conditions'] ?? '')
        );

        $html = '<div class="section-block">';
        $html .= '<h2>Terms and Conditions</h2>';

        if ($cancellation_terms !== '') {
            $html .= '
<div class="terms">
    <h3>Cancellation and Amendment Policy</h3>
    <div class="terms-content">'
                . nl2br($this->escape($cancellation_terms)) .
            '</div>
</div>';
        }

        if ($general_terms !== '') {
            $html .= '
<div class="terms">
    <h3>General Terms</h3>
    <div class="terms-content">'
                . nl2br($this->escape($general_terms)) .
            '</div>
</div>';
        }

        if ($cancellation_terms === '' && $general_terms === '') {
            $html .= '
<div class="terms-empty">
    Terms and conditions have not been added to this quotation.
    Please add the applicable terms before sending this document to the customer.
</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Format a monetary value.
     */
    private function formatMoney($amount, string $currency): string
    {
        if (!is_numeric($amount)) {
            $amount = 0;
        }

        return $this->escape($currency)
            . ' '
            . number_format((float) $amount, 2, '.', ',');
    }

    /**
     * Escape a value for safe HTML output.
     */
    private function escape($value): string
    {
        return esc_html((string) $value);
    }
}
