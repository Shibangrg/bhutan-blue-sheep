<?php
/**
 * Experiences Archive Template.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="main-content" class="experiences-archive">

    <section class="experiences-intro">
        <div class="site-container">

            <p class="experiences-intro__eyebrow">
                Experience the Kingdom
            </p>

            <h1>Discover Bhutan Through Its Experiences</h1>

            <p>
                Go beyond sightseeing and discover the traditions,
                landscapes, festivals, and everyday life that make
                Bhutan a truly special destination.
            </p>

        </div>
    </section>

    <section class="experiences-listing">
        <div class="site-container">

            <?php if (have_posts()) : ?>

                <div class="experience-grid">

                    <?php
                    while (have_posts()) :
                        the_post();
                    ?>

                        <article <?php post_class('experience-card'); ?>>

                            <a
                                class="experience-card__image"
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
                                <?php else : ?>
                                    <span class="experience-card__placeholder">
                                        Bhutan Experience
                                    </span>
                                <?php endif; ?>
                            </a>

                            <div class="experience-card__content">

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
                                    class="experience-card__link"
                                    href="<?php the_permalink(); ?>"
                                >
                                    Discover Experience
                                </a>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </div>

                <div class="experience-pagination">
                    <?php
                    the_posts_pagination(array(
                        'mid_size'  => 2,
                        'prev_text' => __('Previous', 'blue-sheep'),
                        'next_text' => __('Next', 'blue-sheep'),
                    ));
                    ?>
                </div>

            <?php else : ?>

                <div class="experiences-empty">
                    <h2>Meaningful Experiences Await</h2>

                    <p>
                        We are preparing experiences that will help
                        you discover the culture, traditions, and
                        natural beauty of Bhutan. Check back soon.
                    </p>
                </div>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php get_footer(); ?>
