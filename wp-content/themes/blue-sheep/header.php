<?php

if (!defined('ABSPATH')) {
    exit;
}
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <div class="site-header__inner">

        <a
            class="site-brand"
            href="<?php echo esc_url(home_url('/')); ?>"
            aria-label="<?php esc_attr_e('Bhutan Blue Sheep Tours and Travels Home', 'blue-sheep'); ?>"
        >
            <span class="site-brand__media">
                <img
                    class="site-brand__logo"
                    src="<?php echo esc_url(get_theme_file_uri('/assets/images/logo.png')); ?>"
                    alt=""
                >

                <video
                    class="site-brand__video"
                    muted
                    loop
                    playsinline
                    preload="metadata"
                    aria-hidden="true"
                    tabindex="-1"
                >
                    <source
                        src="<?php echo esc_url(get_theme_file_uri('/assets/videos/header-transition.mp4')); ?>"
                        type="video/mp4"
                    >
                </video>
            </span>

            <span class="site-brand__name">
                Bhutan Blue Sheep
            </span>
        </a>

        <button
            class="site-navigation__toggle"
            type="button"
            aria-label="Open navigation menu"
            aria-expanded="false"
            aria-controls="primary-navigation"
        >
            <span class="menu-toggle__line"></span>
            <span class="menu-toggle__line"></span>
            <span class="menu-toggle__line"></span>
        </button>

        <nav
            id="primary-navigation"
            class="site-navigation"
            aria-label="<?php esc_attr_e('Primary navigation', 'blue-sheep'); ?>"
        >
            <?php
            wp_nav_menu(array(
                'theme_location' => 'primary',
                'container'      => false,
                'fallback_cb'    => false,
            ));
            ?>
        </nav>

    </div>
</header>