<?php

namespace BlueSheep\BusinessManager\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Records database migration activity and recovery status.
 */
final class MigrationLogger {

    private const OPTION_NAME = 'bsbm_migration_logs';

    private const MAX_LOG_ENTRIES = 100;

    /**
     * Record a migration event.
     */
    public function log(
        int $version,
        string $status,
        string $message
    ): void {

        $logs = $this->getLogs();

        $logs[] = array(
            'version' => $version,
            'status' => sanitize_key($status),
            'message' => sanitize_text_field($message),
            'timestamp' => current_time('mysql', true),
        );

        if (count($logs) > self::MAX_LOG_ENTRIES) {
            $logs = array_slice(
                $logs,
                -self::MAX_LOG_ENTRIES
            );
        }

        update_option(self::OPTION_NAME, $logs, false);
    }

    /**
     * Retrieve all migration logs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLogs(): array {

        $logs = get_option(self::OPTION_NAME, array());

        return is_array($logs) ? $logs : array();
    }

    /**
     * Get the latest recorded status for a migration version.
     */
    public function getLatestStatus(int $version): ?string {

        $logs = array_reverse($this->getLogs());

        foreach ($logs as $log) {

            if (
                isset($log['version'], $log['status'])
                && (int) $log['version'] === $version
            ) {
                return (string) $log['status'];
            }
        }

        return null;
    }

    /**
     * Determine whether a migration requires recovery review.
     *
     * Failed or interrupted migrations must be inspected
     * before another execution attempt.
     */
    public function requiresRecovery(int $version): bool {

        $status = $this->getLatestStatus($version);

        return in_array(
            $status,
            array('started', 'failed', 'recovery_required'),
            true
        );
    }

    /**
     * Clear all migration logs.
     */
    public function clearLogs(): bool {

        return delete_option(self::OPTION_NAME);
    }
}
