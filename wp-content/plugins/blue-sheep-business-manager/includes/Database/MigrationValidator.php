<?php

namespace BlueSheep\BusinessManager\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validates registered database migrations.
 */
final class MigrationValidator {

    /**
     * Validate registered migration versions.
     *
     * @param MigrationInterface[] $migrations
     */
    public function validate(array $migrations): void {

        $versions = array();

        foreach ($migrations as $migration) {

            if (!$migration instanceof MigrationInterface) {
                throw new \InvalidArgumentException(
                    'All registered migrations must implement MigrationInterface.'
                );
            }

            $version = $migration->getVersion();

            if ($version <= 0) {
                throw new \InvalidArgumentException(
                    'Migration versions must be greater than zero.'
                );
            }

            if (in_array($version, $versions, true)) {
                throw new \InvalidArgumentException(
                    'Duplicate migration version detected: ' . $version
                );
            }

            $versions[] = $version;
        }
    }

    /**
     * Validate migration version completeness.
     *
     * Every unapplied version must have a registered migration.
     *
     * @param MigrationInterface[] $migrations
     */
    public function validateCompleteness(
        array $migrations,
        int $installed_version,
        int $current_version
    ): void {

        if ($installed_version < 0 || $current_version < 0) {
            throw new \InvalidArgumentException(
                'Schema versions cannot be negative.'
            );
        }

        if ($installed_version > $current_version) {
            throw new \InvalidArgumentException(
                'Installed schema version cannot exceed current schema version.'
            );
        }

        $this->validate($migrations);

        $registered_versions = array();

        foreach ($migrations as $migration) {

            $version = $migration->getVersion();

            if ($version > $current_version) {
                throw new \InvalidArgumentException(
                    'Migration version exceeds the current supported schema version: '
                    . $version
                );
            }

            $registered_versions[] = $version;
        }

        for (
            $version = $installed_version + 1;
            $version <= $current_version;
            $version++
        ) {
            if (!in_array($version, $registered_versions, true)) {
                throw new \RuntimeException(
                    'Missing database migration version: ' . $version
                );
            }
        }
    }
}
