<?php
/**
 * About Us Page Template.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $about_image = get_the_post_thumbnail_url(
        get_the_ID(),
        'full'
    );
?>

<main id="main-content" class="about-page">

    <!-- About Hero -->
    <section
        class="about-hero <?php echo $about_image ? 'about-hero--image' : 'about-hero--no-image'; ?>"
        <?php if ($about_image) : ?>
            style="background-image: url('<?php echo esc_url($about_image); ?>');"
        <?php endif; ?>
    >
        <div class="about-hero__overlay">
            <div class="site-container">

                <p class="about-hero__eyebrow">
                    Get to Know Us
                </p>

                <h1><?php the_title(); ?></h1>

                <p class="about-hero__description">
                    Discover Bhutan through its landscapes,
                    traditions, culture, and people.
                </p>

            </div>
        </div>
    </section>

    <!-- Main About Content -->
    <section class="about-content-section">
        <div class="site-container">

            <div class="about-content">
                <?php the_content(); ?>
            </div>

        </div>
    </section>

    <!-- Travel Philosophy -->
    <section class="about-philosophy">
        <div class="site-container">

            <div class="about-philosophy__heading">
                <p class="about-section__eyebrow">
                    Discover Bhutan Your Way
                </p>

                <h2>More Than Just a Destination</h2>

                <p>
                    Every journey offers a different way to discover
                    Bhutan. Explore its culture, experience its
                    landscapes, and create memories along the way.
                </p>
            </div>

            <div class="about-philosophy__grid">

                <article class="about-value-card">
                    <span class="about-value-card__number">01</span>

                    <h3>Culture and Heritage</h3>

                    <p>
                        Discover Bhutan's traditions, festivals,
                        monasteries, architecture, and cultural
                        heritage.
                    </p>
                </article>

                <article class="about-value-card">
                    <span class="about-value-card__number">02</span>

                    <h3>Nature and Adventure</h3>

                    <p>
                        Explore mountain landscapes, peaceful valleys,
                        trekking routes, and the natural beauty of Bhutan.
                    </p>
                </article>

                <article class="about-value-card">
                    <span class="about-value-card__number">03</span>

                    <h3>Journeys Made Personal</h3>

                    <p>
                        Explore travel possibilities through private,
                        family, group, and customized journeys.
                    </p>
                </article>

            </div>

        </div>
    </section>

    <!-- Call to Action -->
    <section class="about-cta">
        <div class="site-container">

            <p class="about-section__eyebrow">
                Your Bhutan Journey Begins Here
            </p>

            <h2>Let Us Help You Discover Bhutan</h2>

            <p>
                Share your travel ideas with us and start planning
                a journey through the Kingdom of Happiness.
            </p>

            <a
                class="about-cta__button"
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
