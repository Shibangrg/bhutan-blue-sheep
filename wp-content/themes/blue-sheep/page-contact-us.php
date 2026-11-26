<?php
/**
 * Contact Us Page Template.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $contact_image = get_the_post_thumbnail_url(
        get_the_ID(),
        'full'
    );
?>

<main id="main-content" class="contact-page">

    <!-- Contact Hero -->
    <section
        class="contact-hero <?php echo $contact_image ? 'contact-hero--image' : 'contact-hero--no-image'; ?>"
        <?php if ($contact_image) : ?>
            style="background-image: url('<?php echo esc_url($contact_image); ?>');"
        <?php endif; ?>
    >
        <div class="contact-hero__overlay">
            <div class="site-container">

                <p class="contact-hero__eyebrow">
                    Get in Touch
                </p>

                <h1><?php the_title(); ?></h1>

                <p class="contact-hero__description">
                    Have a question or thinking about visiting Bhutan?
                    We'd love to hear from you and help you start
                    planning your journey.
                </p>

            </div>
        </div>
    </section>

    <!-- Contact Content -->
    <section class="contact-main">
        <div class="site-container">

            <div class="contact-main__heading">
                <p class="contact-section__eyebrow">
                    Start a Conversation
                </p>

                <h2>Let's Plan Your Bhutan Journey</h2>

                <p>
                    Tell us a little about what you have in mind.
                    Whether you are considering a cultural visit,
                    an adventure, a family holiday, or a customized
                    private journey, we welcome your enquiry.
                </p>
            </div>

            <div class="contact-content">
                <?php the_content(); ?>
            </div>

        </div>
    </section>

    <!-- Contact Call to Action -->
    <section class="contact-cta">
        <div class="site-container">

            <p class="contact-section__eyebrow">
                Discover the Kingdom of Happiness
            </p>

            <h2>Your Journey Begins with a Conversation</h2>

            <p>
                Share your ideas and let us help you explore
                the possibilities of travelling through Bhutan.
            </p>

        </div>
    </section>

</main>

<?php
endwhile;

get_footer();
