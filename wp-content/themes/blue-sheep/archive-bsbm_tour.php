<?php
/**
 * Tour Packages Archive Template.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="main-content" class="tours-archive">

    <section class="tours-intro">
        <div class="site-container">
            <p class="tours-intro__eyebrow">Explore Bhutan</p>

            <h1>Discover Our Tour Packages</h1>

            <p>
                Explore Bhutan through thoughtfully designed journeys
                that bring you closer to its landscapes, traditions,
                culture, and people.
            </p>
        </div>
    </section>

    <section class="tours-listing">
        <div class="site-container">

            <?php if (have_posts()) : ?>

                <div class="tour-grid">

                    <?php
                    while (have_posts()) :
                        the_post();
                    ?>

                        <article <?php post_class('tour-card'); ?>>

                            <a
                                class="tour-card__image"
                                href="<?php the_permalink(); ?>"
                                aria-label="<?php echo esc_attr(
                                    sprintf(
                                        __('Explore %s', 'blue-sheep'),
                                        get_the_title()
                                    )
                                ); ?>"
                            >
                                <?php if (has_post_thumbnail()) : ?>
                                    <?php the_post_thumbnail('large'); ?>
                                <?php endif; ?>
                            </a>

                            <div class="tour-card__content">

                                <h2>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h2>

                                <p>
                                    <?php echo esc_html(
                                        wp_trim_words(
                                            get_the_excerpt(),
                                            28
                                        )
                                    ); ?>
                                </p>

                                <a
                                    class="tour-card__link"
                                    href="<?php the_permalink(); ?>"
                                >
                                    Explore Tour
                                </a>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </div>

                <div class="tour-pagination">
                    <?php
                    the_posts_pagination(array(
                        'mid_size'  => 2,
                        'prev_text' => __('Previous', 'blue-sheep'),
                        'next_text' => __('Next', 'blue-sheep'),
                    ));
                    ?>
                </div>

            <?php else : ?>

                <div class="tours-empty">
                    <h2>Our Journeys Are Coming Soon</h2>
                    <p>
                        We are preparing memorable journeys through
                        Bhutan. Please check back soon.
                    </p>
                </div>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php get_footer(); ?>
