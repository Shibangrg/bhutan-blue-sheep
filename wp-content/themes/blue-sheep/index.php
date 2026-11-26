<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * General fallback template.
 *
 * Displays the title and content of the current WordPress post or page.
 * The dedicated front-page.php template handles the homepage separately.
 */

get_header();

?>

<main id="main-content">

    <?php if (have_posts()) : ?>

        <?php while (have_posts()) : ?>

            <?php the_post(); ?>

            <article <?php post_class('content-container'); ?>>

                <header class="entry-header">
                    <h1 class="entry-title">
                        <?php the_title(); ?>
                    </h1>
                </header>

                <div class="entry-content">
                    <?php
                    the_content();

                    wp_link_pages(array(
                        'before' => '<div class="page-links">' .
                            esc_html__('Pages:', 'blue-sheep'),
                        'after'  => '</div>',
                    ));
                    ?>
                </div>

            </article>

        <?php endwhile; ?>

    <?php else : ?>

        <div class="content-container">
            <h1>
                <?php
                esc_html_e(
                    'Welcome to Bhutan Blue Sheep Tours & Travels',
                    'blue-sheep'
                );
                ?>
            </h1>

            <p>
                <?php
                esc_html_e(
                    'No content is currently available.',
                    'blue-sheep'
                );
                ?>
            </p>
        </div>

    <?php endif; ?>

</main>

<?php

get_footer();
