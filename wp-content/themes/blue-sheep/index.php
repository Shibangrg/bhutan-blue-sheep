<?php

if (!defined('ABSPATH')) {
    exit;
}

get_header();

if (have_posts()) :
    while (have_posts()) :
        the_post();
        ?>

        <main id="main-content">
            <article <?php post_class(); ?>>
                <h1><?php the_title(); ?></h1>
                <div>
                    <?php the_content(); ?>
                </div>
            </article>
        </main>

        <?php
    endwhile;
else :
    ?>

    <main id="main-content">
        <h1><?php esc_html_e('Welcome to Bhutan Blue Sheep Tours & Travels', 'blue-sheep'); ?></h1>
    </main>

    <?php
endif;

get_footer();