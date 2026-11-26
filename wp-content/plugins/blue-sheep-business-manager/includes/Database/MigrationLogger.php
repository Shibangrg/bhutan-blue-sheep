<?php

namespace BlueSheep\BusinessManager\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Records database migration activity and recovery status.
 *
 * Logging failures must be reported so migration execution
 * cannot silently continue without a reliable audit record.
 */
final class MigrationLogger {

    private const OPTION_NAME = 'bsbm_migration_logs';

    private const MAX_LOG_ENTRIES = 100;

    /**
     * Record a migration event.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    public function log(
        int $version,
        string $status,
        string $message
    ): void {

        if ($version <= 0) {
            throw new \InvalidArgumentException(
                'Migration log versions must be greater than zero.'
            );
        }

        $status = sanitize_key($status);
        $message = sanitize_text_field($message);

        if ($status === '') {
            throw new \InvalidArgumentException(
                'Migration log status cannot be empty.'
            );
        }

        if ($message === '') {
            throw new \InvalidArgumentException(
                'Migration log message cannot be empty.'
            );
        }

        $logs = $this->getLogs();

        $logs[] = array(
            'version' => $version,
            'status' => $status,
            'message' => $message,
            'timestamp' => current_time('mysql', true),
        );

        if (count($logs) > self::MAX_LOG_ENTRIES) {
            $logs = array_slice(
                $logs,
                -self::MAX_LOG_ENTRIES
            );
        }

        $updated = update_option(
            self::OPTION_NAME,
            $logs,
            false
        );

        // WordPress may return false when the stored value
        // is already identical. Verify the actual stored value.
        if (!$updated) {

            $stored_logs = get_option(
                self::OPTION_NAME,
                array()
            );

            if ($stored_logs !== $logs) {
                throw new \RuntimeException(
                    'Failed to persist the migration log entry.'
                );
            }
        }
    }

    /**
     * Retrieve all migration logs.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \UnexpectedValueException
     */
    public function getLogs(): array {

        $logs = get_option(
            self::OPTION_NAME,
            array()
        );

        if (!is_array($logs)) {
            throw new \UnexpectedValueException(
                'The stored migration logs are invalid.'
            );
        }

        foreach ($logs as $log) {

            if (
                !is_array($log)
                || !isset(
                    $log['version'],
                    $log['status'],
                    $log['message'],
                    $log['timestamp']
                )
            ) {
                throw new \UnexpectedValueException(
                    'A stored migration log entry is incomplete.'
                );
            }

            $version = $log['version'];

            if (
                !is_int($version)
                && !(
                    is_string($version)
                    && ctype_digit($version)
                )
            ) {
                throw new \UnexpectedValueException(
                    'A stored migration log version is invalid.'
                );
            }

            if (
                (int) $version <= 0
                || !is_string($log['status'])
                || $log['status'] === ''
                || !is_string($log['message'])
                || !is_string($log['timestamp'])
                || $log['timestamp'] === ''
            ) {
                throw new \UnexpectedValueException(
                    'A stored migration log entry contains invalid data.'
                );
            }
        }

        return array_values($logs);
    }

    /**
     * Get the latest recorded status for a migration version.
     */
    public function getLatestStatus(int $version): ?string {

        $logs = array_reverse($this->getLogs());

        foreach ($logs as $log) {

            if ((int) $log['version'] === $version) {
                return $log['status'];
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
            array(
                'started',
                'failed',
                'recovery_required',
            ),
            true
        );
    }

    /**
     * Clear all migration logs.
     *
     * This method should only be used by a controlled
     * development or maintenance process.
     */
    public function clearLogs(): bool {

        return delete_option(self::OPTION_NAME);
    }
}