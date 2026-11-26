<?php
/**
 * Travel Journal Archive Template.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="main-content" class="journal-archive">

    <section class="journal-intro">
        <div class="site-container">
            <p class="journal-intro__eyebrow">Stories from the Kingdom</p>

            <h1>The Bhutan Travel Journal</h1>

            <p>
                Discover the stories, traditions, destinations, and
                experiences that make Bhutan a remarkable place to explore.
            </p>
            <h3> 
                You can also share your experiences with us.
            </h3>
        </div>
    </section>

    <section class="journal-listing">
        <div class="site-container">

            <?php if (have_posts()) : ?>

                <div class="journal-grid">

                    <?php while (have_posts()) : the_post(); ?>

                        <article <?php post_class('journal-card'); ?>>

                            <a
                                class="journal-card__image"
                                href="<?php the_permalink(); ?>"
                                aria-label="<?php echo esc_attr(
                                    sprintf(
                                        __('Read %s', 'blue-sheep'),
                                        get_the_title()
                                    )
                                ); ?>"
                            >
                                <?php if (has_post_thumbnail()) : ?>
                                    <?php the_post_thumbnail('large'); ?>
                                <?php else : ?>
                                    <span class="journal-card__placeholder">
                                        Bhutan Travel Journal
                                    </span>
                                <?php endif; ?>
                            </a>

                            <div class="journal-card__content">

                                <p class="journal-card__date">
                                    <?php echo esc_html(
                                        get_the_date('F j, Y')
                                    ); ?>
                                </p>

                                <h2>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h2>

                                <p class="journal-card__excerpt">
                                    <?php echo esc_html(
                                        wp_trim_words(
                                            get_the_excerpt(),
                                            30
                                        )
                                    ); ?>
                                </p>

                                <a
                                    class="journal-card__link"
                                    href="<?php the_permalink(); ?>"
                                >
                                    Read Story
                                </a>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </div>

                <div class="journal-pagination">
                    <?php
                    the_posts_pagination(array(
                        'mid_size'  => 2,
                        'prev_text' => __('Previous', 'blue-sheep'),
                        'next_text' => __('Next', 'blue-sheep'),
                    ));
                    ?>
                </div>

            <?php else : ?>

                <div class="journal-empty">
                    <h2>Stories Coming Soon</h2>

                    <p>
                        We are preparing stories about Bhutan's
                        destinations, traditions, culture, and people.
                        Visit again soon.
                    </p>
                </div>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php get_footer(); ?>
