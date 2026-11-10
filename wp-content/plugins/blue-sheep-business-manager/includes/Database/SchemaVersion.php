<?php

namespace BlueSheep\BusinessManager\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tracks the installed business database schema version.
 */
final class SchemaVersion {

    /**
     * Current schema version defined by the plugin.
     */
    private const CURRENT_VERSION = 0;

    /**
     * WordPress option used to store the installed schema version.
     */
    private const OPTION_NAME = 'bsbm_schema_version';

    /**
     * Get the currently installed schema version.
     *
     * @throws \UnexpectedValueException
     */
    public function getInstalledVersion(): int {

        $stored_version = get_option(self::OPTION_NAME, 0);

        if (
            !is_int($stored_version)
            && !(is_string($stored_version) && ctype_digit($stored_version))
        ) {
            throw new \UnexpectedValueException(
                'The stored database schema version is invalid.'
            );
        }

        $version = (int) $stored_version;

        if ($version < 0) {
            throw new \UnexpectedValueException(
                'The stored database schema version cannot be negative.'
            );
        }

        return $version;
    }

    /**
     * Get the schema version supported by this plugin release.
     */
    public function getCurrentVersion(): int {

        return self::CURRENT_VERSION;
    }

    /**
     * Update the installed schema version.
     *
     * This should only be called by the migration runner
     * after a migration has completed successfully.
     */
    public function updateInstalledVersion(int $version): bool {

        if ($version < 0 || $version > self::CURRENT_VERSION) {
            return false;
        }

        if ($version === $this->getInstalledVersion()) {
            return true;
        }

        return update_option(self::OPTION_NAME, $version, false);
    }
}
