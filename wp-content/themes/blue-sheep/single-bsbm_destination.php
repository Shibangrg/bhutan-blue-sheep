<?php
/**
 * Individual Destination Detail Template.
 *
 * Displays individual destination pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="main-content" class="destination-single">

    <?php
    while (have_posts()) :
        the_post();

        $destination_image = get_the_post_thumbnail_url(
            get_the_ID(),
            'full'
        );
    ?>

        <article <?php post_class('destination-detail'); ?>>

            <?php if ($destination_image) : ?>
                <section
                    class="destination-hero"
                    style="background-image: url('<?php echo esc_url($destination_image); ?>');"
                >
                    <div class="destination-hero__overlay">
                        <div class="site-container">
                            <p class="destination-hero__eyebrow">
                                Explore Bhutan
                            </p>

                            <h1 class="destination-hero__title">
                                <?php the_title(); ?>
                            </h1>
                        </div>
                    </div>
                </section>
            <?php else : ?>
                <section class="destination-hero destination-hero--no-image">
                    <div class="site-container">
                        <p class="destination-hero__eyebrow">
                            Explore Bhutan
                        </p>

                        <h1 class="destination-hero__title">
                            <?php the_title(); ?>
                        </h1>
                    </div>
                </section>
            <?php endif; ?>

            <section class="destination-content-section">
                <div class="site-container destination-content">

                    <div class="destination-content__main">

                        <div class="destination-content__intro">
                            <p class="destination-content__eyebrow">
                                Discover the Destination
                            </p>

                            <h2>
                                Experience <?php the_title(); ?>
                            </h2>
                        </div>

                        <div class="destination-content__body">
                            <?php the_content(); ?>
                        </div>

                    </div>

                </div>
            </section>

        </article>

    <?php endwhile; ?>

    <section class="destination-related">
        <div class="site-container">

            <div class="destination-related__heading">
                <p class="destination-content__eyebrow">
                    Continue Exploring
                </p>

                <h2>Discover More Destinations</h2>
            </div>

            <div class="destination-grid">

                <?php
                $related_destinations = new WP_Query(array(
                    'post_type'      => 'bsbm_destination',
                    'posts_per_page' => 3,
                    'post__not_in'   => array(get_the_ID()),
                    'orderby'        => 'rand',
                    'post_status'    => 'publish',
                ));

                if ($related_destinations->have_posts()) :
                    while ($related_destinations->have_posts()) :
                        $related_destinations->the_post();
                ?>

                    <article class="destination-card">

                        <a
                            class="destination-card__image"
                            href="<?php the_permalink(); ?>"
                        >
                            <?php if (has_post_thumbnail()) : ?>
                                <?php the_post_thumbnail('large'); ?>
                            <?php endif; ?>
                        </a>

                        <div class="destination-card__content">

                            <h3>
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </h3>

                            <p>
                                <?php echo esc_html(
                                    wp_trim_words(
                                        get_the_excerpt(),
                                        24
                                    )
                                ); ?>
                            </p>

                            <a
                                class="destination-card__link"
                                href="<?php the_permalink(); ?>"
                            >
                                Explore Destination
                            </a>

                        </div>

                    </article>

                <?php
                    endwhile;
                    wp_reset_postdata();
                else :
                ?>

                    <p>More destinations will be added soon.</p>

                <?php endif; ?>

            </div>

        </div>
    </section>

</main>

<?php get_footer(); ?>
