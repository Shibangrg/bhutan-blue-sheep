<?php

namespace BlueSheep\BusinessManager\Core;

use BlueSheep\BusinessManager\Content\ContentTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles core plugin initialization.
 */
final class Plugin
{
    /**
     * Initialize the plugin.
     */
    public function init(): void
    {
        $content_types = new ContentTypes();
        $content_types->register();
    }
}
