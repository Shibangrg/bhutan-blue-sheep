<?php

namespace BlueSheep\BusinessManager\Modules\Notifications;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles database operations for notification records.
 */
final class NotificationRepository
{
    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Notification log table name.
     */
    private string $table;

    /**
     * Initialize the notification repository.
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'bs_notification_log';
    }

    /**
     * Create a notification record.
     *
     * Returns the existing record ID if the same event/entity
     * combination has already been registered.
     *
     * @param string      $event_type            Notification event.
     * @param string      $related_entity_type   Related entity type.
     * @param int         $related_entity_id     Related entity ID.
     * @param string|null $recipient_reference   Recipient reference.
     *
     * @return int|false Notification ID or false on failure.
     */
    public function create(
        string $event_type,
        string $related_entity_type,
        int $related_entity_id,
        ?string $recipient_reference = null
    ): int|false {
        $event_type = sanitize_key($event_type);
        $related_entity_type = sanitize_key($related_entity_type);

        if (
            $event_type === ''
            || $related_entity_type === ''
            || $related_entity_id <= 0
        ) {
            return false;
        }

        /*
         * Prevent duplicate event/entity records.
         *
         * The database unique index provides the final
         * concurrency-safe protection once Migration 3
         * has been executed.
         */
        $existing = $this->getByEventAndEntity(
            $event_type,
            $related_entity_type,
            $related_entity_id
        );

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $inserted = $this->wpdb->insert(
            $this->table,
            [
                'event_type'          => $event_type,
                'related_entity_type' => $related_entity_type,
                'related_entity_id'   => $related_entity_id,
                'recipient_reference' => $recipient_reference !== null
                    ? sanitize_text_field($recipient_reference)
                    : null,
                'delivery_status'     => 'pending',
                'provider_reference'  => null,
                'attempt_count'       => 0,
                'last_attempt_at'     => null,
                'created_at'          => current_time('mysql', true),
            ],
            [
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
            ]
        );

        if ($inserted === false) {
            /*
             * Recheck in case another request inserted
             * the same event/entity combination.
             */
            $existing = $this->getByEventAndEntity(
                $event_type,
                $related_entity_type,
                $related_entity_id
            );

            if ($existing !== null) {
                return (int) $existing['id'];
            }

            return false;
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Retrieve a notification by ID.
     *
     * @param int $notification_id Notification ID.
     *
     * @return array|null
     */
    public function getById(int $notification_id): ?array
    {
        if ($notification_id <= 0) {
            return null;
        }

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT *
                FROM {$this->table}
                WHERE id = %d",
                $notification_id
            ),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    /**
     * Retrieve a notification by event and related entity.
     *
     * @param string $event_type          Notification event.
     * @param string $related_entity_type Related entity type.
     * @param int    $related_entity_id   Related entity ID.
     *
     * @return array|null
     */
    public function getByEventAndEntity(
        string $event_type,
        string $related_entity_type,
        int $related_entity_id
    ): ?array {
        if (
            $event_type === ''
            || $related_entity_type === ''
            || $related_entity_id <= 0
        ) {
            return null;
        }

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT *
                FROM {$this->table}
                WHERE event_type = %s
                  AND related_entity_type = %s
                  AND related_entity_id = %d
                LIMIT 1",
                $event_type,
                $related_entity_type,
                $related_entity_id
            ),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    /**
     * Retrieve notification records for a related entity.
     *
     * @param string $related_entity_type Related entity type.
     * @param int    $related_entity_id   Related entity ID.
     *
     * @return array
     */
    public function getByEntity(
        string $related_entity_type,
        int $related_entity_id
    ): array {
        if (
            $related_entity_type === ''
            || $related_entity_id <= 0
        ) {
            return [];
        }

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT *
                FROM {$this->table}
                WHERE related_entity_type = %s
                  AND related_entity_id = %d
                ORDER BY created_at DESC, id DESC",
                $related_entity_type,
                $related_entity_id
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Record a notification delivery attempt.
     *
     * Increments the attempt count and updates the latest
     * delivery status, provider reference, and attempt time.
     *
     * @param int         $notification_id  Notification ID.
     * @param string      $delivery_status  Delivery status.
     * @param string|null $provider_reference Provider reference.
     *
     * @return bool
     */
    public function recordAttempt(
        int $notification_id,
        string $delivery_status,
        ?string $provider_reference = null
    ): bool {
        if ($notification_id <= 0) {
            return false;
        }

        $allowed_statuses = [
            'pending',
            'sent',
            'failed',
        ];

        $delivery_status = sanitize_key($delivery_status);

        if (!in_array($delivery_status, $allowed_statuses, true)) {
            return false;
        }

        $updated = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table}
                SET delivery_status = %s,
                    provider_reference = %s,
                    attempt_count = attempt_count + 1,
                    last_attempt_at = %s
                WHERE id = %d",
                $delivery_status,
                $provider_reference !== null
                    ? sanitize_text_field($provider_reference)
                    : null,
                current_time('mysql', true),
                $notification_id
            )
        );

        return $updated !== false && $updated > 0;
    }
}
