<?php

namespace BlueSheep\BusinessManager\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coordinates database migrations.
 *
 * Migration execution remains disabled until the migration
 * system and database schema are fully approved.
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
     * Execute pending migrations.
     *
     * Each migration attempt and result is recorded.
     *
     * @throws \RuntimeException
     */
    public function run(): void {

        if (!self::EXECUTION_ENABLED) {
            throw new \RuntimeException(
                'Database migration execution is currently disabled.'
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

// Record the start of this execution attempt.
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

                $this->logger->log(
                    $version,
                    'success',
                    'Migration completed successfully.'
                );

            } catch (\Throwable $exception) {

                $this->logger->log(
                    $version,
                    'failed',
                    $exception->getMessage()
                );

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
        }
    }
}
