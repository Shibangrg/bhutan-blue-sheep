<?php

namespace BlueSheep\BusinessManager\Content;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers public website content types.
 */
final class ContentTypes
{
    /**
     * Register content type hooks.
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerPostTypes']);
    }

    /**
     * Register all custom post types.
     */
    public function registerPostTypes(): void
    {
        $this->registerPostType(
            'bsbm_tour',
            'Tour Packages',
            'Tour Package',
            'tour-packages'
        );

        $this->registerPostType(
            'bsbm_destination',
            'Destinations',
            'Destination',
            'destinations'
        );

        $this->registerPostType(
            'bsbm_experience',
            'Experiences',
            'Experience',
            'experiences'
        );

        $this->registerPostType(
            'bsbm_journal',
            'Travel Journal',
            'Journal Article',
            'travel-journal'
        );

        $this->registerPostType(
            'bsbm_testimonial',
            'Testimonials',
            'Testimonial',
            'testimonials'
        );
    }

    /**
     * Register an individual custom post type.
     */
    private function registerPostType(
        string $slug,
        string $plural,
        string $singular,
        string $rewrite
    ): void {
        $labels = [
            'name'                  => $plural,
            'singular_name'         => $singular,
            'add_new'               => 'Add New',
            'add_new_item'          => 'Add New ' . $singular,
            'edit_item'             => 'Edit ' . $singular,
            'new_item'              => 'New ' . $singular,
            'view_item'             => 'View ' . $singular,
            'search_items'          => 'Search ' . $plural,
            'not_found'             => 'No ' . strtolower($plural) . ' found.',
            'not_found_in_trash'    => 'No ' . strtolower($plural) . ' found in Trash.',
            'all_items'             => 'All ' . $plural,
            'menu_name'             => $plural,
        ];

        register_post_type($slug, [
            'labels'             => $labels,
            'public'             => true,
            'show_in_rest'       => true,
            'has_archive'        => true,
            'rewrite'            => [
                'slug'       => $rewrite,
                'with_front' => false,
            ],
            'supports'           => [
                'title',
                'editor',
                'excerpt',
                'thumbnail',
                'revisions',
            ],
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-admin-site-alt3',
        ]);
    }
}
