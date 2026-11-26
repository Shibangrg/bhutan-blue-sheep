<?php

namespace BlueSheep\BusinessManager\Enquiry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the public customer enquiry form.
 */
final class EnquiryForm
{
    /**
     * Register the enquiry form shortcode.
     */
    public function register(): void
    {
        add_shortcode(
            'bsbm_enquiry_form',
            [$this, 'render']
        );
    }

    /**
     * Render the public enquiry form.
     *
     * @return string
     */
    public function render(): string
    {

         // Load conditional enquiry form behaviour.
        wp_enqueue_script(
            'bsbm-enquiry-form',
            BSBM_URL . 'assets/js/enquiry-form.js',
            array(),
            BSBM_VERSION,
            true
        );

        $destinations = get_posts([
            'post_type'      => 'bsbm_destination',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $tours = get_posts([
            'post_type'      => 'bsbm_tour',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $interests = [
            'cultural_festival_tours' => 'Cultural and Festival Tours',
            'trekking_adventure' => 'Trekking and Adventure',
            'photography_travel' => 'Photography and Travel Experiences',
            'family_holidays' => 'Family Holidays',
            'group_tours' => 'Group Tours',
            'transport_hotel' => 'Transportation and Hotel Arrangements',
        ];

        ob_start();
        ?>

        <?php
        $response_status = '';

        if (
            isset($_GET['bsbm_enquiry']) &&
            is_string($_GET['bsbm_enquiry'])
        ) {
            $response_status = sanitize_key(
                wp_unslash($_GET['bsbm_enquiry'])
            );
        }

        $response_messages = [
            'success' => [
                'class' => 'success',
                'message' => 'Thank you for your enquiry. Your request has been received successfully. Our team will contact you soon.',
            ],
            'save_failed' => [
                'class' => 'error',
                'message' => 'We could not save your enquiry at this time. Please try again later or contact us directly.',
            ],
            'invalid' => [
                'class' => 'error',
                'message' => 'Some enquiry information could not be validated. Please review the form and try again.',
            ],
            'expired' => [
                'class' => 'error',
                'message' => 'Your form session has expired. Please refresh the page and try again.',
            ],
            'spam' => [
                'class' => 'error',
                'message' => 'We could not process this submission. Please review the form and try again.',
            ],
            'rate_limited' => [
                'class' => 'error',
                'message' => 'You have reached the enquiry submission limit. Please wait 15 minutes before trying again.',
            ],
        ];

        if (isset($response_messages[$response_status])) :
            $response = $response_messages[$response_status];
        ?>
            <div
                class="bsbm-form-response bsbm-form-response--<?php echo esc_attr($response['class']); ?>"
                role="status"
                aria-live="polite"
            >
                <?php echo esc_html($response['message']); ?>
            </div>
        <?php endif; ?>


        <form
            class="bsbm-enquiry-form"
            method="post"
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        >

            <input
                type="hidden"
                name="action"
                value="bsbm_submit_enquiry"
            >

            <input
                type="hidden"
                name="form_started_at"
                value="<?php echo esc_attr((string) time()); ?>"
            >

            <?php
            wp_nonce_field(
                'bsbm_submit_enquiry',
                'bsbm_enquiry_nonce'
            );
            ?>

            <!-- Anti-spam honeypot -->
            <div
                class="bsbm-enquiry-form__honeypot"
                aria-hidden="true"
            >
                <label for="bsbm-website">
                    Leave this field empty
                </label>

                <input
                    type="text"
                    name="website"
                    id="bsbm-website"
                    tabindex="-1"
                    autocomplete="off"
                >
            </div>

            <!-- Customer Information -->
            <section class="bsbm-form-section">

                <h3>Your Contact Information</h3>

                <div class="bsbm-form-grid">

                    <div class="bsbm-form-field">
                        <label for="bsbm-full-name">
                            Full Name *
                        </label>

                        <input
                            type="text"
                            id="bsbm-full-name"
                            name="full_name"
                            maxlength="190"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div class="bsbm-form-field">
                        <label for="bsbm-email">
                            Email Address *
                        </label>

                        <input
                            type="email"
                            id="bsbm-email"
                            name="email"
                            maxlength="254"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="bsbm-form-field">
                        <label for="bsbm-phone">
                            Phone / WhatsApp Number
                        </label>

                        <input
                            type="tel"
                            id="bsbm-phone"
                            name="phone"
                            maxlength="50"
                            autocomplete="tel"
                        >
                    </div>

                    <div class="bsbm-form-field">
                        <label for="bsbm-country">
                            Country of Residence
                        </label>

                        <input
                            type="text"
                            id="bsbm-country"
                            name="country"
                            maxlength="100"
                            autocomplete="country-name"
                        >
                    </div>

                </div>

            </section>

            <!-- Enquiry Type -->
            <section class="bsbm-form-section">

                <h3>What Are You Interested In?</h3>

                <div class="bsbm-form-field">

                    <label for="bsbm-enquiry-type">
                        Enquiry Type *
                    </label>

                    <select
                        id="bsbm-enquiry-type"
                        name="enquiry_type"
                        required
                    >
                        <option value="">
                            Select an enquiry type
                        </option>

                        <option value="custom_trip">
                            Custom Trip
                        </option>

                        <option value="package_reservation">
                            Package Reservation
                        </option>

                        <option value="general">
                            General Enquiry
                        </option>

                    </select>

                </div>

            </section>

            <!-- Trip Information -->
            <section
                class="bsbm-form-section"
                data-bsbm-trip-fields
                hidden
            >

                <h3>Tell Us About Your Journey</h3>

                <!-- Package Selection -->
                <div
                    class="bsbm-form-field"
                    data-bsbm-package-fields
                    hidden
                >

                    <label for="bsbm-tour">
                        Select a Tour Package
                    </label>

                    <select
                        id="bsbm-tour"
                        name="tour_id"
                    >

                        <option value="">
                            Select a tour package
                        </option>

                        <?php foreach ($tours as $tour) : ?>

                            <option value="<?php echo esc_attr(
                                (string) $tour->ID
                            ); ?>">
                                <?php echo esc_html(
                                    get_the_title($tour)
                                ); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="bsbm-form-grid">

                    <div class="bsbm-form-field">
                        <label for="bsbm-start-date">
                            Preferred Start Date
                        </label>

                        <input
                            type="date"
                            id="bsbm-start-date"
                            name="preferred_start_date"
                        >
                    </div>

                    <div class="bsbm-form-field">
                        <label for="bsbm-end-date">
                            Preferred End Date
                        </label>

                        <input
                            type="date"
                            id="bsbm-end-date"
                            name="preferred_end_date"
                        >
                    </div>

                    <div class="bsbm-form-field">
                        <label for="bsbm-flexible-dates">
                            Are Your Travel Dates Flexible?
                        </label>

                        <select
                            id="bsbm-flexible-dates"
                            name="dates_flexible"
                        >
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>

                    <div class="bsbm-form-field">
                        <label for="bsbm-travellers">
                            Total Number of Travellers *
                        </label>

                        <input
                            type="number"
                            id="bsbm-travellers"
                            name="traveller_count"
                            min="1"
                            max="500"
                        >
                    </div>

                    <div class="bsbm-form-field">
                        <label for="bsbm-adults">
                            Number of Adults
                        </label>

                        <input
                            type="number"
                            id="bsbm-adults"
                            name="adults_count"
                            min="0"
                            max="500"
                        >
                    </div>

                    <div class="bsbm-form-field">
                        <label for="bsbm-children">
                            Number of Children
                        </label>

                        <input
                            type="number"
                            id="bsbm-children"
                            name="children_count"
                            min="0"
                            max="500"
                        >
                    </div>

                </div>

                <!-- Destinations -->
                <div class="bsbm-form-field">

                    <label for="bsbm-destinations">
                        Preferred Destinations
                    </label>

                    <select
                        id="bsbm-destinations"
                        name="destinations[]"
                        multiple
                    >

                        <?php foreach ($destinations as $destination) : ?>

                            <option value="<?php echo esc_attr(
                                (string) $destination->ID
                            ); ?>">
                                <?php echo esc_html(
                                    get_the_title($destination)
                                ); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <p class="bsbm-form-help">
                        You can select multiple destinations.
                    </p>

                </div>

                <!-- Interests -->
                <fieldset class="bsbm-form-field">

                    <legend>What Interests You?</legend>

                    <div class="bsbm-interest-options">

                        <?php foreach ($interests as $value => $label) : ?>

                            <label class="bsbm-interest-option">

                                <input
                                    type="checkbox"
                                    name="interests[]"
                                    value="<?php echo esc_attr($value); ?>"
                                >

                                <span>
                                    <?php echo esc_html($label); ?>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>

                </fieldset>

                <div class="bsbm-form-field">

                    <label for="bsbm-budget">
                        Estimated Budget
                    </label>

                    <input
                        type="text"
                        id="bsbm-budget"
                        name="budget_range"
                        maxlength="100"
                        placeholder="Optional"
                    >

                </div>

            </section>

            <!-- Additional Requirements -->
            <section class="bsbm-form-section">

                <h3>Additional Information</h3>

                <div class="bsbm-form-field">

                    <label for="bsbm-requirements">
                        Tell Us More About Your Enquiry
                    </label>

                    <textarea
                        id="bsbm-requirements"
                        name="additional_requirements"
                        rows="6"
                        maxlength="10000"
                        placeholder="Share any questions, preferences, or special requirements..."
                    ></textarea>

                </div>

            </section>

            <div class="bsbm-form-submit">

                <button type="submit">
                    Send Enquiry
                </button>

                <p>
                    Submitting an enquiry does not confirm a booking.
                    Our team will review your request and contact you.
                </p>

            </div>

        </form>

        <?php

        return (string) ob_get_clean();
    }
}
