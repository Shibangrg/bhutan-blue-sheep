<?php

namespace BlueSheep\BusinessManager\Modules\Notifications;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders notification email templates using dynamic placeholders.
 */
final class NotificationTemplateRenderer
{
    /**
     * Render an email subject.
     *
     * @param string $template Template subject.
     * @param array  $data     Placeholder values.
     *
     * @return string
     */
    public function renderSubject(
        string $template,
        array $data
    ): string {
        $replacements = $this->prepareReplacements($data, false);

        $subject = strtr($template, $replacements);

        return sanitize_text_field($subject);
    }

    /**
     * Render an HTML email body.
     *
     * Placeholder values are escaped before insertion.
     *
     * @param string $template Template body.
     * @param array  $data     Placeholder values.
     *
     * @return string
     */
    public function renderBody(
        string $template,
        array $data
    ): string {
        $replacements = $this->prepareReplacements($data, true);

        $body = strtr($template, $replacements);

        return wp_kses_post($body);
    }

    /**
     * Render the complete notification template.
     *
     * @param string $subject_template Subject template.
     * @param string $body_template    Body template.
     * @param array  $data             Placeholder values.
     *
     * @return array
     */
    public function render(
        string $subject_template,
        string $body_template,
        array $data
    ): array {
        return [
            'subject' => $this->renderSubject(
                $subject_template,
                $data
            ),
            'body' => $this->renderBody(
                $body_template,
                $data
            ),
        ];
    }

    /**
     * Prepare supported placeholder replacements.
     *
     * Supported placeholders:
     * - {customer_name}
     * - {enquiry_reference}
     * - {enquiry_date}
     *
     * @param array $data         Placeholder values.
     * @param bool  $escape_html  Whether to escape for HTML output.
     *
     * @return array
     */
    private function prepareReplacements(
        array $data,
        bool $escape_html
    ): array {
        $supported_placeholders = [
            'customer_name',
            'enquiry_reference',
            'enquiry_date',
        ];

        $replacements = [];

        foreach ($supported_placeholders as $placeholder) {
            $value = $data[$placeholder] ?? '';

            if (!is_scalar($value)) {
                $value = '';
            }

            $value = sanitize_text_field((string) $value);

            $replacements['{' . $placeholder . '}'] = $escape_html
                ? esc_html($value)
                : $value;
        }

        return $replacements;
    }
}
