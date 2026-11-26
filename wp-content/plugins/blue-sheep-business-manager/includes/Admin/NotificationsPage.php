<?php

namespace BlueSheep\BusinessManager\Admin;

use BlueSheep\BusinessManager\Modules\Notifications\NotificationService;
use BlueSheep\BusinessManager\Modules\Notifications\NotificationSettingsService;
use BlueSheep\BusinessManager\Modules\Notifications\NotificationTemplateRenderer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders and manages notification settings.
 */
final class NotificationsPage
{
    private NotificationSettingsService $settings_service;

    private NotificationTemplateRenderer $template_renderer;

    private NotificationService $notification_service;

    /**
     * Initialize the page.
     */
    public function __construct(
        ?NotificationSettingsService $settings_service = null,
        ?NotificationTemplateRenderer $template_renderer = null,
        ?NotificationService $notification_service = null
    ) {
        $this->settings_service = $settings_service
            ?? new NotificationSettingsService();

        $this->template_renderer = $template_renderer
            ?? new NotificationTemplateRenderer();

        $this->notification_service = $notification_service
            ?? new NotificationService();
    }

    /**
     * Render the notification settings page.
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

        $notice = '';

        if (
            isset($_SERVER['REQUEST_METHOD'])
            && $_SERVER['REQUEST_METHOD'] === 'POST'
        ) {
            $notice = $this->handleSubmission();
        }

        $settings = $this->settings_service->getSettings();

        $preview = $this->template_renderer->render(
            $settings['subject'],
            $settings['body'],
            $this->getSampleData()
        );

        ?>
        <div class="wrap">
            <h1>
                <?php
                esc_html_e(
                    'Notification Settings',
                    'blue-sheep-business-manager'
                );
                ?>
            </h1>

            <p>
                <?php
                esc_html_e(
                    'Configure the automated acknowledgement email sent after a customer successfully submits an enquiry.',
                    'blue-sheep-business-manager'
                );
                ?>
            </p>

            <?php $this->renderNotice($notice); ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=bsbm-notifications')); ?>">
                <?php wp_nonce_field('bsbm_save_notification_settings', 'bsbm_notification_settings_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="bsbm-notifications-enabled">
                                    <?php
                                    esc_html_e(
                                        'Acknowledgement Emails',
                                        'blue-sheep-business-manager'
                                    );
                                    ?>
                                </label>
                            </th>

                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        id="bsbm-notifications-enabled"
                                        name="enabled"
                                        value="1"
                                        <?php checked($settings['enabled']); ?>
                                    >

                                    <?php
                                    esc_html_e(
                                        'Enable automated enquiry acknowledgement emails',
                                        'blue-sheep-business-manager'
                                    );
                                    ?>
                                </label>

                                <p class="description">
                                    <?php
                                    esc_html_e(
                                        'Keep this disabled until SMTP configuration and end-to-end email testing are complete.',
                                        'blue-sheep-business-manager'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="bsbm-notification-subject">
                                    <?php
                                    esc_html_e(
                                        'Email Subject',
                                        'blue-sheep-business-manager'
                                    );
                                    ?>
                                </label>
                            </th>

                            <td>
                                <input
                                    type="text"
                                    id="bsbm-notification-subject"
                                    name="subject"
                                    class="regular-text"
                                    maxlength="255"
                                    value="<?php echo esc_attr($settings['subject']); ?>"
                                    required
                                >
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="bsbm-notification-body">
                                    <?php
                                    esc_html_e(
                                        'Email Body',
                                        'blue-sheep-business-manager'
                                    );
                                    ?>
                                </label>
                            </th>

                            <td>
                                <textarea
                                    id="bsbm-notification-body"
                                    name="body"
                                    class="large-text code"
                                    rows="18"
                                    required
                                ><?php echo esc_textarea($settings['body']); ?></textarea>

                                <p class="description">
                                    <?php
                                    esc_html_e(
                                        'You can use permitted HTML elements to format the email.',
                                        'blue-sheep-business-manager'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2>
                    <?php
                    esc_html_e(
                        'Supported Placeholders',
                        'blue-sheep-business-manager'
                    );
                    ?>
                </h2>

                <p>
                    <?php
                    esc_html_e(
                        'These placeholders will be replaced with the corresponding enquiry information when the email is generated.',
                        'blue-sheep-business-manager'
                    );
                    ?>
                </p>

                <table class="widefat striped" style="max-width: 700px;">
                    <thead>
                        <tr>
                            <th>
                                <?php
                                esc_html_e(
                                    'Placeholder',
                                    'blue-sheep-business-manager'
                                );
                                ?>
                            </th>
                            <th>
                                <?php
                                esc_html_e(
                                    'Description',
                                    'blue-sheep-business-manager'
                                );
                                ?>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td><code>{customer_name}</code></td>
                            <td>
                                <?php
                                esc_html_e(
                                    'Customer name provided with the enquiry.',
                                    'blue-sheep-business-manager'
                                );
                                ?>
                            </td>
                        </tr>

                        <tr>
                            <td><code>{enquiry_reference}</code></td>
                            <td>
                                <?php
                                esc_html_e(
                                    'Internal enquiry reference.',
                                    'blue-sheep-business-manager'
                                );
                                ?>
                            </td>
                        </tr>

                        <tr>
                            <td><code>{enquiry_date}</code></td>
                            <td>
                                <?php
                                esc_html_e(
                                    'Date the enquiry was submitted.',
                                    'blue-sheep-business-manager'
                                );
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php
                submit_button(
                    __(
                        'Save Notification Settings',
                        'blue-sheep-business-manager'
                    )
                );
                ?>
            </form>

            <hr>

            <h2>
                <?php
                esc_html_e(
                    'Email Preview',
                    'blue-sheep-business-manager'
                );
                ?>
            </h2>

            <p>
                <?php
                esc_html_e(
                    'Preview the current saved template using sample enquiry information.',
                    'blue-sheep-business-manager'
                );
                ?>
            </p>

            <div class="notice notice-info inline">
                <p>
                    <strong>
                        <?php esc_html_e('Subject:', 'blue-sheep-business-manager'); ?>
                    </strong>
                    <?php echo esc_html($preview['subject']); ?>
                </p>
            </div>

            <div
                style="
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    padding: 24px;
                    max-width: 750px;
                    margin-top: 15px;
                "
            >
                <?php echo wp_kses_post($preview['body']); ?>
            </div>

            <hr>

            <h2>
                <?php
                esc_html_e(
                    'Send Test Email',
                    'blue-sheep-business-manager'
                );
                ?>
            </h2>

            <p>
                <?php
                esc_html_e(
                    'Send the current saved template to an email address for delivery testing. This does not create a customer enquiry or business notification record.',
                    'blue-sheep-business-manager'
                );
                ?>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=bsbm-notifications')); ?>">
                <?php wp_nonce_field('bsbm_send_test_notification', 'bsbm_test_notification_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="bsbm-test-recipient">
                                    <?php
                                    esc_html_e(
                                        'Test Recipient',
                                        'blue-sheep-business-manager'
                                    );
                                    ?>
                                </label>
                            </th>

                            <td>
                                <input
                                    type="email"
                                    id="bsbm-test-recipient"
                                    name="test_recipient"
                                    class="regular-text"
                                    required
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php
                submit_button(
                    __(
                        'Send Test Email',
                        'blue-sheep-business-manager'
                    ),
                    'secondary',
                    'submit',
                    true
                );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Process submitted settings or test email requests.
     */
    private function handleSubmission(): string
    {
        if (!current_user_can('manage_options')) {
            return 'error';
        }

        if (isset($_POST['bsbm_notification_settings_nonce'])) {
            return $this->handleSettingsSubmission();
        }

        if (isset($_POST['bsbm_test_notification_nonce'])) {
            return $this->handleTestEmailSubmission();
        }

        return '';
    }

    /**
     * Save notification settings.
     */
    private function handleSettingsSubmission(): string
    {
        if (
            !is_string($_POST['bsbm_notification_settings_nonce'])
        ) {
            return 'error';
        }

        $nonce = sanitize_text_field(
            wp_unslash($_POST['bsbm_notification_settings_nonce'])
        );

        if (
            !wp_verify_nonce(
                $nonce,
                'bsbm_save_notification_settings'
            )
        ) {
            return 'error';
        }

        $settings = [
            'enabled' => isset($_POST['enabled'])
                && $_POST['enabled'] === '1',
            'subject' => isset($_POST['subject'])
                && is_string($_POST['subject'])
                ? sanitize_text_field(wp_unslash($_POST['subject']))
                : '',
            'body' => isset($_POST['body'])
                && is_string($_POST['body'])
                ? wp_kses_post(wp_unslash($_POST['body']))
                : '',
        ];

        if (
            $settings['subject'] === ''
            || trim(wp_strip_all_tags($settings['body'])) === ''
        ) {
            return 'error';
        }

        $saved = $this->settings_service->saveSettings($settings);

        if ($saved) {
            return 'settings_saved';
        }

        $current = $this->settings_service->getSettings();

        if (
            $current['enabled'] === $settings['enabled']
            && $current['subject'] === $settings['subject']
            && $current['body'] === $settings['body']
        ) {
            return 'unchanged';
        }

        return 'error';
    }

    /**
     * Send a test email using the current saved template.
     */
    private function handleTestEmailSubmission(): string
    {
        if (
            !is_string($_POST['bsbm_test_notification_nonce'])
        ) {
            return 'error';
        }

        $nonce = sanitize_text_field(
            wp_unslash($_POST['bsbm_test_notification_nonce'])
        );

        if (
            !wp_verify_nonce(
                $nonce,
                'bsbm_send_test_notification'
            )
        ) {
            return 'error';
        }

        $recipient = isset($_POST['test_recipient'])
            && is_string($_POST['test_recipient'])
            ? sanitize_email(wp_unslash($_POST['test_recipient']))
            : '';

        if (!is_email($recipient)) {
            return 'invalid_recipient';
        }

        $settings = $this->settings_service->getSettings();

        $rendered = $this->template_renderer->render(
            $settings['subject'],
            $settings['body'],
            $this->getSampleData()
        );

        $result = $this->notification_service->sendTestEmail(
            $recipient,
            $rendered['subject'],
            $rendered['body']
        );

        return $result['success']
            ? 'test_sent'
            : 'test_failed';
    }

    /**
     * Return sample data for email previews and test delivery.
     */
    private function getSampleData(): array
    {
        return [
            'customer_name' => 'Sample Customer',
            'enquiry_reference' => 'BSB-ENQ-0001',
            'enquiry_date' => wp_date(
                get_option('date_format')
            ),
        ];
    }

    /**
     * Render admin notices.
     */
    private function renderNotice(string $notice): void
    {
        $messages = [
            'settings_saved' => [
                'success',
                __(
                    'Notification settings saved successfully.',
                    'blue-sheep-business-manager'
                ),
            ],
            'unchanged' => [
                'info',
                __(
                    'Your notification settings are already up to date.',
                    'blue-sheep-business-manager'
                ),
            ],
            'test_sent' => [
                'success',
                __(
                    'WordPress accepted the test email for sending. Check the recipient inbox to verify delivery.',
                    'blue-sheep-business-manager'
                ),
            ],
            'test_failed' => [
                'error',
                __(
                    'WordPress could not send the test email. Check your SMTP and mail configuration.',
                    'blue-sheep-business-manager'
                ),
            ],
            'invalid_recipient' => [
                'error',
                __(
                    'Please provide a valid test recipient email address.',
                    'blue-sheep-business-manager'
                ),
            ],
            'error' => [
                'error',
                __(
                    'Unable to process the request. Please check the submitted values and try again.',
                    'blue-sheep-business-manager'
                ),
            ],
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        [$type, $message] = $messages[$notice];

        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
}
