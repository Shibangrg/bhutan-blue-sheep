<?php

namespace BlueSheep\BusinessManager\Modules\Notifications;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coordinates notification creation and email delivery.
 */
final class NotificationService
{
    /**
     * Notification repository.
     */
    private NotificationRepository $repository;

    /**
     * Initialize the notification service.
     *
     * @param NotificationRepository|null $repository
     */
    public function __construct(
        ?NotificationRepository $repository = null
    ) {
        $this->repository = $repository ?? new NotificationRepository();
    }

    /**
     * Send an email and record its delivery result.
     *
     * Notifications already marked as sent are not sent again.
     *
     * @param string       $event_type          Notification event.
     * @param string       $related_entity_type Related entity type.
     * @param int          $related_entity_id   Related entity ID.
     * @param string       $recipient           Recipient email address.
     * @param string       $subject             Email subject.
     * @param string       $message             Email message.
     * @param array|string $headers             Optional email headers.
     * @param array        $attachments         Optional attachments.
     *
     * @return array
     */
    public function sendEmail(
        string $event_type,
        string $related_entity_type,
        int $related_entity_id,
        string $recipient,
        string $subject,
        string $message,
        array|string $headers = [],
        array $attachments = []
    ): array {
        $recipient = sanitize_email($recipient);
        $subject = sanitize_text_field($subject);

        if (
            !is_email($recipient)
            || $subject === ''
            || trim($message) === ''
            || $related_entity_id <= 0
            || $event_type === ''
            || $related_entity_type === ''
        ) {
            return [
                'success' => false,
                'status' => 'invalid_request',
                'notification_id' => null,
                'error' => 'Invalid notification details.',
            ];
        }

        $notification_id = $this->repository->create(
            $event_type,
            $related_entity_type,
            $related_entity_id,
            $recipient
        );

        if ($notification_id === false) {
            return [
                'success' => false,
                'status' => 'log_creation_failed',
                'notification_id' => null,
                'error' => 'Unable to create the notification log record.',
            ];
        }

        $notification = $this->repository->getById(
            $notification_id
        );

        if ($notification === null) {
            return [
                'success' => false,
                'status' => 'log_retrieval_failed',
                'notification_id' => $notification_id,
                'error' => 'Unable to retrieve the notification log record.',
            ];
        }

        if ($notification['delivery_status'] === 'sent') {
            return [
                'success' => true,
                'status' => 'already_sent',
                'notification_id' => $notification_id,
                'error' => null,
            ];
        }

        $sent = wp_mail(
            $recipient,
            $subject,
            $message,
            $headers,
            $attachments
        );

        $delivery_status = $sent ? 'sent' : 'failed';

        $recorded = $this->repository->recordAttempt(
            $notification_id,
            $delivery_status
        );

        if (!$recorded) {
            return [
                'success' => false,
                'status' => 'logging_failed',
                'notification_id' => $notification_id,
                'delivery_success' => $sent,
                'error' => 'Email delivery result could not be recorded.',
            ];
        }

        return [
            'success' => $sent,
            'status' => $delivery_status,
            'notification_id' => $notification_id,
            'error' => $sent
                ? null
                : 'WordPress email delivery failed.',
        ];
    }

    /**
     * Send a test email without creating a business notification record.
     *
     * Test emails are deliberately excluded from customer notification
     * history and business-event duplicate prevention.
     *
     * @param string $recipient Recipient email address.
     * @param string $subject   Email subject.
     * @param string $message   HTML email message.
     *
     * @return array
     */
    public function sendTestEmail(
        string $recipient,
        string $subject,
        string $message
    ): array {
        $recipient = sanitize_email($recipient);
        $subject = sanitize_text_field($subject);

        if (
            !is_email($recipient)
            || $subject === ''
            || trim($message) === ''
        ) {
            return [
                'success' => false,
                'status' => 'invalid_request',
                'error' => 'A valid recipient, subject and message are required.',
            ];
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        $sent = wp_mail(
            $recipient,
            $subject,
            $message,
            $headers
        );

        return [
            'success' => $sent,
            'status' => $sent ? 'sent' : 'failed',
            'error' => $sent
                ? null
                : 'WordPress could not send the test email. Check the mail configuration.',
        ];
    }

    /**
     * Retrieve notification details.
     *
     * @param int $notification_id Notification ID.
     *
     * @return array|null
     */
    public function getNotification(int $notification_id): ?array
    {
        return $this->repository->getById($notification_id);
    }

    /**
     * Retrieve notification history for an entity.
     *
     * @param string $entity_type Related entity type.
     * @param int    $entity_id   Related entity ID.
     *
     * @return array
     */
    public function getEntityNotifications(
        string $entity_type,
        int $entity_id
    ): array {
        return $this->repository->getByEntity(
            $entity_type,
            $entity_id
        );
    }
}
