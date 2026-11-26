<?php
/**
 * Individual Tour Package Template.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="main-content" class="tour-single">

    <?php
    while (have_posts()) :
        the_post();

        $tour_image = get_the_post_thumbnail_url(
            get_the_ID(),
            'full'
        );
    ?>

        <article <?php post_class('tour-detail'); ?>>

            <?php if ($tour_image) : ?>

                <section
                    class="tour-hero"
                    style="background-image: url('<?php echo esc_url($tour_image); ?>');"
                >
                    <div class="tour-hero__overlay">
                        <div class="site-container">

                            <p class="tour-hero__eyebrow">
                                Bhutan Blue Sheep Tours &amp; Travels
                            </p>

                            <h1 class="tour-hero__title">
                                <?php the_title(); ?>
                            </h1>

                        </div>
                    </div>
                </section>

            <?php else : ?>

                <section class="tour-hero tour-hero--no-image">
                    <div class="site-container">

                        <p class="tour-hero__eyebrow">
                            Bhutan Blue Sheep Tours &amp; Travels
                        </p>

                        <h1 class="tour-hero__title">
                            <?php the_title(); ?>
                        </h1>

                    </div>
                </section>

            <?php endif; ?>

            <section class="tour-detail-section">
                <div class="site-container">

                    <div class="tour-detail__layout">

                        <div class="tour-detail__main">

                            <div class="tour-detail__heading">
                                <p class="tour-detail__eyebrow">
                                    Discover Your Journey
                                </p>

                                <h2>
                                    Experience Bhutan Your Way
                                </h2>
                            </div>

                            <div class="tour-detail__content">
                                <?php the_content(); ?>
                            </div>

                        </div>

                        <aside class="tour-detail__sidebar">

                            <div class="tour-enquiry-card">

                                <p class="tour-enquiry-card__eyebrow">
                                    Start Planning
                                </p>

                                <h2>
                                    Interested in This Journey?
                                </h2>

                                <p>
                                    Tell us about your travel plans.
                                    Our team will help you explore
                                    the possibilities and prepare
                                    a journey suited to your needs.
                                </p>

                                <a
                                    class="tour-enquiry-card__button"
                                    href="<?php echo esc_url(
                                        home_url('/contact-us/')
                                    ); ?>"
                                >
                                    Plan Your Trip
                                </a>

                            </div>

                        </aside>

                    </div>

                </div>
            </section>

        </article>

    <?php endwhile; ?>

    <section class="tour-related">
        <div class="site-container">

            <div class="tour-related__heading">
                <p class="tour-detail__eyebrow">
                    More to Explore
                </p>

                <h2>Discover Other Journeys</h2>
            </div>

            <div class="tour-grid">

                <?php
                $current_tour_id = get_queried_object_id();

                $related_tours = new WP_Query(array(
                    'post_type'      => 'bsbm_tour',
                    'posts_per_page' => 3,
                    'post__not_in'   => array($current_tour_id),
                    'orderby'        => 'rand',
                    'post_status'    => 'publish',
                ));

                if ($related_tours->have_posts()) :
                    while ($related_tours->have_posts()) :
                        $related_tours->the_post();
                ?>

                    <article class="tour-card">

                        <a
                            class="tour-card__image"
                            href="<?php the_permalink(); ?>"
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
                                        24
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

                <?php
                    endwhile;
                    wp_reset_postdata();
                else :
                ?>

                    <p>More journeys will be added soon.</p>

                <?php endif; ?>

            </div>

        </div>
    </section>

</main>

<?php get_footer(); ?>
