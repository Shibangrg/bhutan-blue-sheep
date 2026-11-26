<?php

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>

<main id="main-content" class="destinations-archive">

    <!-- DESTINATIONS INTRODUCTION -->
    <section class="destinations-intro">
        <div class="site-container">

            <span class="section-eyebrow">
                <?php esc_html_e('EXPLORE BHUTAN', 'blue-sheep'); ?>
            </span>

            <h1 class="section-title">
                <?php esc_html_e('Discover Our Destinations', 'blue-sheep'); ?>
            </h1>

            <p class="destinations-intro__description">
                <?php
                esc_html_e(
                    'Explore the remarkable landscapes, cultural heritage and hidden treasures of Bhutan. Discover destinations that offer unforgettable experiences across the Kingdom of Happiness.',
                    'blue-sheep'
                );
                ?>
            </p>

        </div>
    </section>

    <!-- DESTINATION COLLECTION -->
    <section class="destinations-listing">
        <div class="site-container">

            <?php if (have_posts()) : ?>

                <div class="destination-grid">

                    <?php while (have_posts()) : ?>

                        <?php the_post(); ?>

                        <article <?php post_class('destination-card'); ?>>

                            <a
                                class="destination-card__image-link"
                                href="<?php the_permalink(); ?>"
                                aria-label="<?php
                                    echo esc_attr(
                                        sprintf(
                                            __('Explore %s', 'blue-sheep'),
                                            get_the_title()
                                        )
                                    );
                                ?>"
                            >

                                <?php if (has_post_thumbnail()) : ?>

                                    <?php
                                    the_post_thumbnail(
                                        'large',
                                        array(
                                            'class' => 'destination-card__image',
                                            'loading' => 'lazy',
                                        )
                                    );
                                    ?>

                                <?php else : ?>

                                    <div class="destination-card__placeholder">
                                        <?php
                                        esc_html_e(
                                            'Image coming soon',
                                            'blue-sheep'
                                        );
                                        ?>
                                    </div>

                                <?php endif; ?>

                            </a>

                            <div class="destination-card__content">

                                <h2 class="destination-card__title">
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h2>

                                <div class="destination-card__description">
                                    <?php
                                    echo esc_html(
                                        wp_trim_words(
                                            get_the_excerpt(),
                                            30,
                                            '...'
                                        )
                                    );
                                    ?>
                                </div>

                                <a
                                    class="destination-card__link"
                                    href="<?php the_permalink(); ?>"
                                >
                                    <?php
                                    esc_html_e(
                                        'Explore Destination',
                                        'blue-sheep'
                                    );
                                    ?>
                                    &rarr;
                                </a>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </div>

                <!-- PAGINATION -->
                <nav
                    class="destination-pagination"
                    aria-label="<?php esc_attr_e('Destination pages', 'blue-sheep'); ?>"
                >
                    <?php
                    the_posts_pagination(
                        array(
                            'mid_size' => 2,
                            'prev_text' => __('Previous', 'blue-sheep'),
                            'next_text' => __('Next', 'blue-sheep'),
                        )
                    );
                    ?>
                </nav>

            <?php else : ?>

                <div class="destinations-empty">

                    <h2>
                        <?php
                        esc_html_e(
                            'No destinations available yet.',
                            'blue-sheep'
                        );
                        ?>
                    </h2>

                    <p>
                        <?php
                        esc_html_e(
                            'Please check back soon to discover more of Bhutan.',
                            'blue-sheep'
                        );
                        ?>
                    </p>

                </div>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php

get_footer();
