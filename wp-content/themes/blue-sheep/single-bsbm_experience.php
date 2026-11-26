<?php
/**
 * Individual Experience Detail Template.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="main-content" class="experience-single">

    <?php
    while (have_posts()) :
        the_post();

        $experience_image = get_the_post_thumbnail_url(
            get_the_ID(),
            'full'
        );
    ?>

        <article <?php post_class('experience-detail'); ?>>

            <?php if ($experience_image) : ?>

                <section
                    class="experience-hero"
                    style="background-image: url('<?php echo esc_url($experience_image); ?>');"
                >
                    <div class="experience-hero__overlay">
                        <div class="site-container">

                            <p class="experience-hero__eyebrow">
                                Experience Bhutan
                            </p>

                            <h1 class="experience-hero__title">
                                <?php the_title(); ?>
                            </h1>

                        </div>
                    </div>
                </section>

            <?php else : ?>

                <section class="experience-hero experience-hero--no-image">
                    <div class="site-container">

                        <p class="experience-hero__eyebrow">
                            Experience Bhutan
                        </p>

                        <h1 class="experience-hero__title">
                            <?php the_title(); ?>
                        </h1>

                    </div>
                </section>

            <?php endif; ?>

            <section class="experience-detail-section">
                <div class="site-container">

                    <div class="experience-detail__layout">

                        <div class="experience-detail__main">

                            <p class="experience-detail__eyebrow">
                                Discover Something Special
                            </p>

                            <h2>
                                A Closer Look at Bhutan
                            </h2>

                            <div class="experience-detail__content">
                                <?php the_content(); ?>
                            </div>

                        </div>

                        <aside class="experience-detail__sidebar">

                            <div class="experience-enquiry-card">

                                <p class="experience-enquiry-card__eyebrow">
                                    Plan Your Experience
                                </p>

                                <h2>
                                    Make This Part of Your Journey
                                </h2>

                                <p>
                                    Interested in experiencing this
                                    part of Bhutan? Contact our team
                                    to discuss your travel plans and
                                    arrangements.
                                </p>

                                <a
                                    class="experience-enquiry-card__button"
                                    href="<?php echo esc_url(
                                        home_url('/contact-us/')
                                    ); ?>"
                                >
                                    Enquire Now
                                </a>

                            </div>

                        </aside>

                    </div>

                </div>
            </section>

        </article>

    <?php endwhile; ?>

    <section class="experience-related">
        <div class="site-container">

            <div class="experience-related__heading">
                <p class="experience-detail__eyebrow">
                    Continue Exploring
                </p>

                <h2>Discover More Experiences</h2>
            </div>

            <div class="experience-grid">

                <?php
                $current_experience_id = get_queried_object_id();

                $related_experiences = new WP_Query(array(
                    'post_type'      => 'bsbm_experience',
                    'posts_per_page' => 3,
                    'post__not_in'   => array($current_experience_id),
                    'orderby'        => 'rand',
                    'post_status'    => 'publish',
                ));

                if ($related_experiences->have_posts()) :
                    while ($related_experiences->have_posts()) :
                        $related_experiences->the_post();
                ?>

                    <article class="experience-card">

                        <a
                            class="experience-card__image"
                            href="<?php the_permalink(); ?>"
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
                                        24
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

                <?php
                    endwhile;
                    wp_reset_postdata();
                else :
                ?>

                    <p>More experiences will be added soon.</p>

                <?php endif; ?>

            </div>

        </div>
    </section>

</main>

<?php get_footer(); ?>
