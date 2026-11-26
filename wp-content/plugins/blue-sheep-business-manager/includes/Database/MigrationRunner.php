<?php

namespace BlueSheep\BusinessManager\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coordinates database migrations.
 *
 * Migration execution remains disabled until explicitly approved.
 */
final class MigrationRunner {

    private SchemaVersion $schema_version;

    private MigrationValidator $validator;

    private MigrationLogger $logger;

    /**
     * Keep migration execution disabled during development.
     */
    private const EXECUTION_ENABLED = false;

    /**
     * Registered migrations.
     *
     * @var MigrationInterface[]
     */
    private array $migrations = array();

    /**
     * Initialize the migration runner.
     */
    public function __construct() {
        $this->schema_version = new SchemaVersion();
        $this->validator = new MigrationValidator();
        $this->logger = new MigrationLogger();
    }

    /**
     * Register a migration.
     *
     * @throws \InvalidArgumentException
     */
    public function register(MigrationInterface $migration): void {

        $version = $migration->getVersion();

        if ($version <= 0) {
            throw new \InvalidArgumentException(
                'Migration versions must be greater than zero.'
            );
        }

        if (isset($this->migrations[$version])) {
            throw new \InvalidArgumentException(
                'A migration with this version is already registered.'
            );
        }

        $this->migrations[$version] = $migration;
    }

    /**
     * Return pending migrations in ascending version order.
     *
     * @return MigrationInterface[]
     */
    public function getPendingMigrations(): array {

        ksort($this->migrations);

        $installed_version = $this->schema_version->getInstalledVersion();
        $current_version = $this->schema_version->getCurrentVersion();

        if ($installed_version > $current_version) {
            throw new \RuntimeException(
                'The installed database schema is newer than the schema '
                . 'supported by this plugin release. Upgrade the plugin '
                . 'before continuing.'
            );
        }

        $this->validator->validateCompleteness(
            $this->migrations,
            $installed_version,
            $current_version
        );

        return array_values(
            array_filter(
                $this->migrations,
                static fn (MigrationInterface $migration): bool =>
                    $migration->getVersion() > $installed_version
            )
        );
    }

    /**
     * Generate a database-specific migration lock name.
     *
     * @param object $wpdb WordPress database connection.
     */
    private function getLockName(object $wpdb): string {

        $database_name = defined('DB_NAME') ? DB_NAME : '';
        $table_prefix = isset($wpdb->prefix) ? $wpdb->prefix : '';

        return 'bsbm_migration_'
            . substr(
                hash(
                    'sha256',
                    $database_name . ':' . $table_prefix
                ),
                0,
                40
            );
    }

    /**
     * Acquire a database-level migration lock.
     *
     * @throws \RuntimeException
     */
    private function acquireLock(
        object $wpdb,
        string $lock_name
    ): void {

        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT GET_LOCK(%s, 0)',
                $lock_name
            )
        );

        if ((string) $result !== '1') {
            throw new \RuntimeException(
                'Unable to acquire the database migration lock. '
                . 'Another migration may be running, or the database '
                . 'does not support the required locking operation.'
            );
        }
    }

    /**
     * Release the database-level migration lock.
     *
     * @throws \RuntimeException
     */
    private function releaseLock(
        object $wpdb,
        string $lock_name
    ): void {

        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT RELEASE_LOCK(%s)',
                $lock_name
            )
        );

        if ((string) $result !== '1') {
            throw new \RuntimeException(
                'The database migration lock could not be confirmed as released.'
            );
        }
    }

    /**
     * Execute pending migrations.
     *
     * @throws \RuntimeException
     */
    public function run(): void {

        if (!self::EXECUTION_ENABLED) {
            throw new \RuntimeException(
                'Database migration execution is currently disabled.'
            );
        }

        global $wpdb;

        if (
            !is_object($wpdb)
            || !method_exists($wpdb, 'get_var')
            || !method_exists($wpdb, 'prepare')
        ) {
            throw new \RuntimeException(
                'The WordPress database connection is unavailable.'
            );
        }

        $lock_name = $this->getLockName($wpdb);

        $this->acquireLock($wpdb, $lock_name);

        $execution_exception = null;
        $release_exception = null;

        try {

            // Check whether the latest installed migration
            // requires recovery inspection.
            $installed_version = $this->schema_version
                ->getInstalledVersion();

            if (
                $installed_version > 0
                && $this->logger->requiresRecovery($installed_version)
            ) {
                throw new \RuntimeException(
                    'The installed schema version requires recovery review.'
                );
            }

            $pending_migrations = $this->getPendingMigrations();

            foreach ($pending_migrations as $migration) {

                $version = $migration->getVersion();

                // Stop if a previous attempt requires manual inspection.
                if ($this->logger->requiresRecovery($version)) {
                    throw new \RuntimeException(
                        'Migration version '
                        . $version
                        . ' requires recovery review before it can run again.'
                    );
                }

                // Record the start before performing database changes.
                // If logging fails, the migration must not execute.
                $this->logger->log(
                    $version,
                    'started',
                    'Migration execution started.'
                );

                try {

                    // Execute the migration.
                    $migration->up();

                    // Update the schema version after successful execution.
                    $updated = $this->schema_version
                        ->updateInstalledVersion($version);

                    if (!$updated) {
                        throw new \RuntimeException(
                            'Failed to save the installed schema version.'
                        );
                    }

                    // Verify that the schema version was persisted.
                    if (
                        $this->schema_version->getInstalledVersion()
                        !== $version
                    ) {
                        throw new \RuntimeException(
                            'Schema version verification failed.'
                        );
                    }

                } catch (\Throwable $exception) {

                    // Record the failure without masking the original error.
                    try {

                        $this->logger->log(
                            $version,
                            'failed',
                            $exception->getMessage()
                        );

                    } catch (\Throwable $logging_exception) {

                        throw new \RuntimeException(
                            'Migration version '
                            . $version
                            . ' failed, and its failure could not be recorded. '
                            . 'Original error: '
                            . $exception->getMessage()
                            . ' Logging error: '
                            . $logging_exception->getMessage(),
                            0,
                            $exception
                        );
                    }

                    throw new \RuntimeException(
                        'Database migration failed at version '
                        . $version
                        . '. Review the database state before retrying. '
                        . 'Original error: '
                        . $exception->getMessage(),
                        0,
                        $exception
                    );
                }

                // Record success separately. If this fails, the schema
                // version has already been updated. Do not mark the
                // completed migration as failed.
                try {

                    $this->logger->log(
                        $version,
                        'success',
                        'Migration completed successfully.'
                    );

                } catch (\Throwable $logging_exception) {

                    throw new \RuntimeException(
                        'Migration version '
                        . $version
                        . ' was applied and its schema version was updated, '
                        . 'but the success event could not be recorded. '
                        . 'Manual inspection is required. Logging error: '
                        . $logging_exception->getMessage(),
                        0,
                        $logging_exception
                    );
                }
            }

        } catch (\Throwable $exception) {

            $execution_exception = $exception;
        }

        // Always attempt to release the database lock.
        try {

            $this->releaseLock($wpdb, $lock_name);

        } catch (\Throwable $exception) {

            $release_exception = $exception;
        }
        // Preserve the execution error if one occurred.
        if ($execution_exception !== null) {

            if ($release_exception !== null) {
                throw new \RuntimeException(
                    'Migration execution failed: '
                    . $execution_exception->getMessage()
                    . ' The database migration lock could not be confirmed '
                    . 'as released: '
                    . $release_exception->getMessage(),
                    0,
                    $execution_exception
                );
            }

            throw $execution_exception;
        }

        if ($release_exception !== null) {
            throw $release_exception;
        }
    }
}