<?php

namespace BlueSheep\BusinessManager\Modules\Notifications;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages notification settings and email templates.
 */
final class NotificationSettingsService
{
    private const OPTION_NAME = 'bsbm_notification_settings';

    /**
     * Return the default notification configuration.
     */
    public function getDefaults(): array
    {
        return [
            'enabled' => false,
            'subject' => 'We have received your enquiry',
            'body' => '
                <p>Dear {customer_name},</p>

                <p>Thank you for contacting Bhutan Blue Sheep Tours & Travels.</p>

                <p>We have successfully received your enquiry
                (Reference: {enquiry_reference}) submitted on {enquiry_date}.</p>

                <p>Our team will review your requirements and get back to you.</p>

                <p>Thank you,<br>
                Bhutan Blue Sheep Tours & Travels</p>
            ',
        ];
    }

    /**
     * Retrieve saved settings merged with defaults.
     */
    public function getSettings(): array
    {
        $saved_settings = get_option(self::OPTION_NAME, []);

        if (!is_array($saved_settings)) {
            $saved_settings = [];
        }

        $settings = wp_parse_args(
            $saved_settings,
            $this->getDefaults()
        );

        return [
            'enabled' => (bool) $settings['enabled'],
            'subject' => (string) $settings['subject'],
            'body' => (string) $settings['body'],
        ];
    }

    /**
     * Save notification settings.
     *
     * @param array $settings Submitted settings.
     *
     * @return bool
     */
    public function saveSettings(array $settings): bool
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $current_settings = $this->getSettings();

        $enabled = isset($settings['enabled'])
            ? (bool) $settings['enabled']
            : false;

        $subject = isset($settings['subject'])
            && is_string($settings['subject'])
            ? sanitize_text_field($settings['subject'])
            : $current_settings['subject'];

        $body = isset($settings['body'])
            && is_string($settings['body'])
            ? wp_kses_post($settings['body'])
            : $current_settings['body'];

        if ($subject === '' || trim(wp_strip_all_tags($body)) === '') {
            return false;
        }

        $new_settings = [
            'enabled' => $enabled,
            'subject' => $subject,
            'body' => $body,
        ];

        return update_option(
            self::OPTION_NAME,
            $new_settings,
            false
        );
    }

    /**
     * Check whether acknowledgement notifications are enabled.
     */
    public function isEnabled(): bool
    {
        $settings = $this->getSettings();

        return $settings['enabled'];
    }

    /**
     * Restore default templates and disable notifications.
     *
     * @return bool
     */
    public function resetSettings(): bool
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        return update_option(
            self::OPTION_NAME,
            $this->getDefaults(),
            false
        );
    }
}
