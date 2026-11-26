<?php

if (!defined('ABSPATH')) {
    exit;
}

get_header();

/**
 * Retrieve published content for homepage sections.
 */
function blue_sheep_get_homepage_items($post_type, $limit = 3) {

    return new WP_Query(array(
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ));
}

?>

<main id="main-content" class="homepage">

    <!-- HERO SECTION -->
    <section class="hero-section">
        <div class="hero-section__overlay"></div>

        <div class="hero-section__content site-container">

            <span class="hero-section__eyebrow">
                BHUTAN BLUE SHEEP TOURS & TRAVELS
            </span>

            <h1>
                Discover the Kingdom
                of Happiness
            </h1>

            <p>
                Experience the timeless beauty of Bhutan through
                its extraordinary landscapes, living traditions
                and meaningful journeys.
            </p>

            <div class="hero-section__actions">

                <a
                    href="<?php echo esc_url(home_url('/tour-packages/')); ?>"
                    class="button button--gold"
                >
                    Explore Our Tours
                </a>

                <a
                    href="<?php echo esc_url(home_url('/contact-us/')); ?>"
                    class="button button--outline"
                >
                    Plan Your Journey
                </a>

            </div>

        </div>
    </section>


    <!-- INTRODUCTION SECTION -->
    <section class="intro-section section-padding">

        <div class="content-container">

            <span class="section-eyebrow">
                WELCOME TO BHUTAN
            </span>

            <h2>
                A Journey Beyond the Ordinary
            </h2>

            <p>
                Discover Bhutan with journeys shaped around its
                rich cultural heritage, breathtaking Himalayan
                landscapes and the warmth of its people.
            </p>

            <p>
                Whether you seek cultural exploration, mountain
                adventures or a personalized holiday, we help
                create meaningful travel experiences across
                the Kingdom of Bhutan.
            </p>

            <a
                href="<?php echo esc_url(home_url('/about-us/')); ?>"
                class="text-link"
            >
                Discover Our Story →
            </a>

        </div>

    </section>


    <!-- DESTINATIONS SECTION -->
    <?php
    $destinations = blue_sheep_get_homepage_items(
        'bsbm_destination',
        3
    );

    if ($destinations->have_posts()) :
    ?>

        <section class="homepage-section destinations-section">

            <div class="site-container">

                <div class="section-heading">

                    <div>
                        <span class="section-eyebrow">
                            EXPLORE BHUTAN
                        </span>

                        <h2>Discover Our Destinations</h2>
                    </div>

                    <a
                        href="<?php echo esc_url(home_url('/destinations/')); ?>"
                        class="text-link"
                    >
                        View All Destinations →
                    </a>

                </div>

                <div class="content-grid">

                    <?php while ($destinations->have_posts()) : ?>

                        <?php $destinations->the_post(); ?>

                        <article class="content-card">

                            <a
                                href="<?php the_permalink(); ?>"
                                class="content-card__image"
                            >

                                <?php if (has_post_thumbnail()) : ?>

                                    <?php the_post_thumbnail('large'); ?>

                                <?php else : ?>

                                    <div class="content-card__placeholder"></div>

                                <?php endif; ?>

                            </a>

                            <div class="content-card__body">

                                <h3>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h3>

                                <p>
                                    <?php
                                    echo esc_html(
                                        wp_trim_words(
                                            get_the_excerpt(),
                                            22
                                        )
                                    );
                                    ?>
                                </p>

                                <a
                                    href="<?php the_permalink(); ?>"
                                    class="text-link"
                                >
                                    Explore Destination →
                                </a>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </div>

            </div>

        </section>

    <?php
    endif;

    wp_reset_postdata();
    ?>


    <!-- TOUR PACKAGES SECTION -->
    <?php
    $tours = blue_sheep_get_homepage_items(
        'bsbm_tour',
        3
    );

    if ($tours->have_posts()) :
    ?>

        <section class="homepage-section tours-section">

            <div class="site-container">

                <div class="section-heading">

                    <div>
                        <span class="section-eyebrow">
                            FIND YOUR JOURNEY
                        </span>

                        <h2>Explore Our Tour Packages</h2>
                    </div>

                    <a
                        href="<?php echo esc_url(home_url('/tour-packages/')); ?>"
                        class="text-link"
                    >
                        View All Tours →
                    </a>

                </div>

                <div class="content-grid">

                    <?php while ($tours->have_posts()) : ?>

                        <?php $tours->the_post(); ?>

                        <article class="content-card">

                            <a
                                href="<?php the_permalink(); ?>"
                                class="content-card__image"
                            >

                                <?php if (has_post_thumbnail()) : ?>

                                    <?php the_post_thumbnail('large'); ?>

                                <?php else : ?>

                                    <div class="content-card__placeholder"></div>

                                <?php endif; ?>

                            </a>

                            <div class="content-card__body">

                                <h3>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h3>

                                <p>
                                    <?php
                                    echo esc_html(
                                        wp_trim_words(
                                            get_the_excerpt(),
                                            22
                                        )
                                    );
                                    ?>
                                </p>

                                <a
                                    href="<?php the_permalink(); ?>"
                                    class="text-link"
                                >
                                    Discover This Tour →
                                </a>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </div>

            </div>

        </section>

    <?php
    endif;

    wp_reset_postdata();
    ?>


    <!-- EXPERIENCES SECTION -->
    <?php
    $experiences = blue_sheep_get_homepage_items(
        'bsbm_experience',
        3
    );

    if ($experiences->have_posts()) :
    ?>

        <section class="homepage-section experiences-section">

            <div class="site-container">

                <div class="section-heading">

                    <div>
                        <span class="section-eyebrow">
                            EXPERIENCE BHUTAN
                        </span>

                        <h2>Journeys Worth Remembering</h2>
                    </div>

                    <a
                        href="<?php echo esc_url(home_url('/experiences/')); ?>"
                        class="text-link"
                    >
                        Explore Experiences →
                    </a>

                </div>

                <div class="content-grid">

                    <?php while ($experiences->have_posts()) : ?>

                        <?php $experiences->the_post(); ?>

                        <article class="content-card">

                            <a
                                href="<?php the_permalink(); ?>"
                                class="content-card__image"
                            >

                                <?php if (has_post_thumbnail()) : ?>

                                    <?php the_post_thumbnail('large'); ?>

                                <?php else : ?>

                                    <div class="content-card__placeholder"></div>

                                <?php endif; ?>

                            </a>

                            <div class="content-card__body">

                                <h3>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h3>

                                <p>
                                    <?php
                                    echo esc_html(
                                        wp_trim_words(
                                            get_the_excerpt(),
                                            22
                                        )
                                    );
                                    ?>
                                </p>

                                <a
                                    href="<?php the_permalink(); ?>"
                                    class="text-link"
                                >
                                    Discover More →
                                </a>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </div>

            </div>

        </section>

    <?php
    endif;

    wp_reset_postdata();
    ?>


    <!-- JOURNEY CTA SECTION -->
    <section class="journey-cta">

        <div class="journey-cta__content">

            <span class="section-eyebrow">
                YOUR BHUTAN JOURNEY STARTS HERE
            </span>

            <h2>
                Let Us Help You Discover Bhutan
            </h2>

            <p>
                Tell us what you have in mind. We will help you
                plan a journey that reflects your interests,
                schedule and travel preferences.
            </p>

            <a
                href="<?php echo esc_url(home_url('/contact-us/')); ?>"
                class="button button--gold"
            >
                Plan Your Trip
            </a>

        </div>

    </section>

</main>

<?php

get_footer();
