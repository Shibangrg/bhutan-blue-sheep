<?php

namespace BlueSheep\BusinessManager\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Automatically loads Business Manager classes.
 */
final class Autoloader {

    /**
     * Register the class autoloader.
     */
    public static function register(): void {
        spl_autoload_register(array(__CLASS__, 'load'));
    }

    /**
     * Load a plugin class from its namespace.
     */
    public static function load(string $class): void {

        $prefix = 'BlueSheep\\BusinessManager\\';

        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative_class = substr($class, strlen($prefix));

        $file = BSBM_PATH . 'includes/'
            . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class)
            . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
}