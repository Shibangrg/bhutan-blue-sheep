<?php
/**
 * Gallery Page Template.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();
?>

<main id="main-content" class="gallery-page">

    <section class="gallery-intro">
        <div class="site-container">

            <p class="gallery-intro__eyebrow">
                Moments Across the Kingdom
            </p>

            <h1><?php the_title(); ?></h1>

            <p>
                Explore Bhutan through its breathtaking landscapes,
                vibrant festivals, ancient architecture, and the
                everyday moments that make every journey memorable.
            </p>

        </div>
    </section>

    <section class="gallery-section">
        <div class="site-container">

            <div class="gallery-content">
                <?php the_content(); ?>
            </div>

        </div>
    </section>

    <section class="gallery-cta">
        <div class="site-container">

            <p class="gallery-cta__eyebrow">
                Your Journey Starts Here
            </p>

            <h2>Come Experience Bhutan for Yourself</h2>

            <p>
                Let these moments inspire your next journey through
                the Kingdom of Happiness.
            </p>

            <a
                class="gallery-cta__button"
                href="<?php echo esc_url(home_url('/contact-us/')); ?>"
            >
                Plan Your Trip
            </a>

        </div>
    </section>

</main>

<?php
endwhile;

get_footer();
