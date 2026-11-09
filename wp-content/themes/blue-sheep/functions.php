<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme setup.
 */
function blue_sheep_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');

    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ));

    register_nav_menus(array(
        'primary' => __('Primary Navigation', 'blue-sheep'),
        'footer'  => __('Footer Navigation', 'blue-sheep'),
    ));
}
add_action('after_setup_theme', 'blue_sheep_theme_setup');

/**
 * Enqueue theme styles.
 */
function blue_sheep_enqueue_assets() {
    wp_enqueue_style(
        'blue-sheep-style',
        get_stylesheet_uri(),
        array(),
        wp_get_theme()->get('Version')
    );
}
add_action('wp_enqueue_scripts', 'blue_sheep_enqueue_assets');