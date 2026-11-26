<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme setup.
 */
function blue_sheep_theme_setup() {

    // Enable theme features.
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');

    // Enable HTML5 markup.
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ));

    // Register navigation menus.
    register_nav_menus(array(
        'primary' => __('Primary Navigation', 'blue-sheep'),
        'footer'  => __('Footer Navigation', 'blue-sheep'),
    ));
}
add_action('after_setup_theme', 'blue_sheep_theme_setup');


/**
 * Enqueue theme fonts, stylesheets and scripts.
 */

function blue_sheep_enqueue_assets() {

    // Load Google Fonts.
    wp_enqueue_style(
        'blue-sheep-google-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Dancing+Script:wght@400;500;600;700&display=swap',
        array(),
        null
    );

    // Load the main theme stylesheet.
    wp_enqueue_style(
        'blue-sheep-style',
        get_stylesheet_uri(),
        array('blue-sheep-google-fonts'),
        wp_get_theme()->get('Version')
    );

    // Load the header video transition script.
    wp_enqueue_script(
        'blue-sheep-header-transition',
        get_theme_file_uri('/assets/js/header-transition.js'),
        array(),
        filemtime(get_theme_file_path('/assets/js/header-transition.js')),
        true
    );

    // Load the prayer flag navigation animation.
    wp_enqueue_script(
        'blue-sheep-prayer-flag-hover',
        get_theme_file_uri('/assets/js/prayer-flag-hover.js'),
        array(),
        wp_get_theme()->get('Version'),
        true
    );

    // Provide the prayer flag video URL to JavaScript.
    wp_localize_script(
        'blue-sheep-prayer-flag-hover',
        'blueSheepPrayerFlag',
        array(
            'videoUrl' => get_theme_file_uri('/assets/videos/hover.mp4'),
            'imageUrl' => get_theme_file_uri('/assets/images/flag.png'),
        )
    );

}
add_action('wp_enqueue_scripts', 'blue_sheep_enqueue_assets');
