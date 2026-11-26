<?php

namespace BlueSheep\BusinessManager\Enquiry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validates customer enquiry submissions.
 *
 * This class validates and sanitizes submitted data.
 * It does not insert or modify database records.
 */
final class EnquiryValidator
{
    /**
     * Supported enquiry types.
     */
    private const ENQUIRY_TYPES = [
        'custom_trip',
        'package_reservation',
        'general',
    ];

    /**
     * Supported customer interests.
     */
    private const INTERESTS = [
        'cultural_festival_tours',
        'trekking_adventure',
        'photography_travel',
        'family_holidays',
        'group_tours',
        'transport_hotel',
    ];

    /**
     * Validate an enquiry submission.
     *
     * @param array<string, mixed> $input Submitted form data.
     *
     * @return array{
     *     valid: bool,
     *     errors: array<string, string>,
     *     data: array<string, mixed>
     * }
     */
    public function validate(array $input): array
    {
        $input = wp_unslash($input);

        $errors = [];
        $data = [];

        /*
         * Honeypot spam check.
         */
        if (!empty($input['website'])) {
            $errors['_form'] = 'Unable to process this submission.';
        }

        /*
         * Customer information.
         */
        $full_name = $this->getText($input, 'full_name');
        $email = sanitize_email(
            $this->getText($input, 'email')
        );

        $phone = $this->getText($input, 'phone');
        $country = $this->getText($input, 'country');

        if ($full_name === '') {
            $errors['full_name'] = 'Please enter your full name.';
        } elseif (mb_strlen($full_name, 'UTF-8') > 190) {
            $errors['full_name'] = 'Your name is too long.';
        }

        if ($email === '' || !is_email($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (strlen($email) > 254) {
            $errors['email'] = 'Your email address is too long.';
        }

        if (mb_strlen($phone, 'UTF-8') > 50) {
            $errors['phone'] = 'Your phone number is too long.';
        }

        if (mb_strlen($country, 'UTF-8') > 100) {
            $errors['country'] = 'Your country name is too long.';
        }

        $data['full_name'] = $full_name;
        $data['email'] = $email;
        $data['phone'] = $phone !== '' ? $phone : null;
        $data['country'] = $country !== '' ? $country : null;

        /*
         * Enquiry type.
         */
        $enquiry_type = sanitize_key(
            $this->getText($input, 'enquiry_type')
        );

        if (!in_array(
            $enquiry_type,
            self::ENQUIRY_TYPES,
            true
        )) {
            $errors['enquiry_type'] = 'Please select a valid enquiry type.';
        }

        $data['enquiry_type'] = $enquiry_type;

        /*
         * Additional requirements.
         */
        $requirements = sanitize_textarea_field(
            $this->getText($input, 'additional_requirements')
        );

        if (mb_strlen($requirements, 'UTF-8') > 10000) {
            $errors['additional_requirements'] =
                'Your additional requirements exceed the allowed length.';
        }

        $data['additional_requirements'] =
            $requirements !== '' ? $requirements : null;

        /*
         * General enquiries do not require trip information.
         */
        if ($enquiry_type === 'general') {
            $data = array_merge($data, [
                'tour_id' => null,
                'preferred_start_date' => null,
                'preferred_end_date' => null,
                'dates_flexible' => 0,
                'traveller_count' => null,
                'adults_count' => null,
                'children_count' => null,
                'destinations' => [],
                'interests' => [],
                'budget_range' => null,
            ]);
        } elseif (in_array(
            $enquiry_type,
            ['custom_trip', 'package_reservation'],
            true
        )) {
            $this->validateTripFields($input, $enquiry_type, $data, $errors);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => empty($errors) ? $data : [],
        ];
    }

    /**
     * Validate trip-specific fields.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function validateTripFields(
        array $input,
        string $enquiry_type,
        array &$data,
        array &$errors
    ): void {

        /*
         * Package selection.
         */
        $tour_id = $this->getOptionalInteger(
            $input,
            'tour_id',
            $errors
        );

        if ($enquiry_type === 'package_reservation') {
            if (!$tour_id) {
                $errors['tour_id'] = 'Please select a tour package.';
            } elseif (!$this->isPublishedPost($tour_id, 'bsbm_tour')) {
                $errors['tour_id'] = 'The selected tour package is unavailable.';
            }
        }

        $data['tour_id'] =
            $enquiry_type === 'package_reservation' ? $tour_id : null;

        /*
         * Preferred travel dates.
         */
        $start_date = $this->getOptionalDate(
            $input,
            'preferred_start_date',
            $errors
        );

        $end_date = $this->getOptionalDate(
            $input,
            'preferred_end_date',
            $errors
        );

        if (
            $start_date !== null &&
            $end_date !== null &&
            $end_date < $start_date
        ) {
            $errors['preferred_end_date'] =
                'The end date cannot be earlier than the start date.';
        }

        $data['preferred_start_date'] = $start_date;
        $data['preferred_end_date'] = $end_date;

        /*
         * Flexible date preference.
         */
        $dates_flexible = $this->getText(
            $input,
            'dates_flexible'
        );

        if (!in_array($dates_flexible, ['0', '1'], true)) {
            $errors['dates_flexible'] =
                'Please select whether your travel dates are flexible.';
        }

        $data['dates_flexible'] = $dates_flexible === '1' ? 1 : 0;

        /*
         * Traveller counts.
         */
        $traveller_count = $this->getOptionalInteger(
            $input,
            'traveller_count',
            $errors
        );

        $adults_count = $this->getOptionalInteger(
            $input,
            'adults_count',
            $errors
        );

        $children_count = $this->getOptionalInteger(
            $input,
            'children_count',
            $errors
        );

        if ($traveller_count === null || $traveller_count < 1) {
            $errors['traveller_count'] =
                'Please enter at least one traveller.';
        } elseif ($traveller_count > 500) {
            $errors['traveller_count'] =
                'The traveller count exceeds the allowed limit.';
        }

        if (
            $adults_count !== null &&
            $adults_count > 500
        ) {
            $errors['adults_count'] =
                'The adult count exceeds the allowed limit.';
        }

        if (
            $children_count !== null &&
            $children_count > 500
        ) {
            $errors['children_count'] =
                'The child count exceeds the allowed limit.';
        }

        if (
            $traveller_count !== null &&
            $adults_count !== null &&
            $children_count !== null &&
            ($adults_count + $children_count) !== $traveller_count
        ) {
            $errors['traveller_count'] =
                'Adult and child counts must equal the total number of travellers.';
        }

        $data['traveller_count'] = $traveller_count;
        $data['adults_count'] = $adults_count;
        $data['children_count'] = $children_count;

        /*
         * Validate destination selections.
         */
        $data['destinations'] = $this->validateDestinations(
            $input,
            $errors
        );

        /*
         * Validate interests.
         */
        $data['interests'] = $this->validateInterests(
            $input,
            $errors
        );

        /*
         * Budget range.
         */
        $budget_range = $this->getText(
            $input,
            'budget_range'
        );

        if (mb_strlen($budget_range, 'UTF-8') > 100) {
            $errors['budget_range'] = 'The budget description is too long.';
        }

        $data['budget_range'] =
            $budget_range !== '' ? $budget_range : null;
    }

    /**
     * Validate selected destination posts.
     *
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     * @return int[]
     */
    private function validateDestinations(
        array $input,
        array &$errors
    ): array {

        $submitted = $input['destinations'] ?? [];

        if (!is_array($submitted)) {
            $errors['destinations'] = 'Invalid destination selection.';
            return [];
        }

        if (count($submitted) > 30) {
            $errors['destinations'] = 'Too many destinations selected.';
            return [];
        }

        $destination_ids = [];

        foreach ($submitted as $value) {
            if (
                !is_scalar($value) ||
                !ctype_digit((string) $value)
            ) {
                $errors['destinations'] = 'Invalid destination selection.';
                return [];
            }

            $destination_id = (int) $value;

            if (
                $destination_id < 1 ||
                !$this->isPublishedPost(
                    $destination_id,
                    'bsbm_destination'
                )
            ) {
                $errors['destinations'] =
                    'One or more selected destinations are unavailable.';

                return [];
            }

            $destination_ids[] = $destination_id;
        }

        return array_values(array_unique($destination_ids));
    }

    /**
     * Validate selected interests.
     *
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     * @return string[]
     */
    private function validateInterests(
        array $input,
        array &$errors
    ): array {

        $submitted = $input['interests'] ?? [];

        if (!is_array($submitted)) {
            $errors['interests'] = 'Invalid interest selection.';
            return [];
        }

        $interests = [];

        foreach ($submitted as $value) {
            if (!is_scalar($value)) {
                $errors['interests'] = 'Invalid interest selection.';
                return [];
            }

            $interest = sanitize_key((string) $value);

            if (!in_array($interest, self::INTERESTS, true)) {
                $errors['interests'] = 'An unsupported interest was selected.';
                return [];
            }

            $interests[] = $interest;
        }

        return array_values(array_unique($interests));
    }

    /**
     * Validate an optional integer field.
     *
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    
    private function getOptionalInteger(
        array $input,
        string $key,
        array &$errors
    ): ?int {

        $value = $input[$key] ?? '';

        if ($value === '') {
            return null;
        }

        if (
            !is_scalar($value) ||
            !preg_match('/^\d+$/', (string) $value)
        ) {
            $errors[$key] = 'Please enter a valid whole number.';
            return null;
        }

        $normalized = ltrim((string) $value, '0');
        $normalized = $normalized === '' ? '0' : $normalized;

        $max = (string) PHP_INT_MAX;

        if (
            strlen($normalized) > strlen($max) ||
            (
                strlen($normalized) === strlen($max) &&
                strcmp($normalized, $max) > 0
            )
        ) {
            $errors[$key] = 'The number exceeds the allowed limit.';
            return null;
        }

        return (int) $normalized;
    }


    /**
     * Validate an optional calendar date.
     *
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    private function getOptionalDate(
        array $input,
        string $key,
        array &$errors
    ): ?string {

        $value = $this->getText($input, $key);

        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        $date_errors = \DateTimeImmutable::getLastErrors();

        if (
            !$date ||
            $date->format('Y-m-d') !== $value ||
            (
                $date_errors !== false &&
                (
                    $date_errors['warning_count'] > 0 ||
                    $date_errors['error_count'] > 0
                )
            )
        ) {
            $errors[$key] = 'Please enter a valid date.';
            return null;
        }

        return $value;
    }

    /**
     * Check whether a WordPress post exists, is published,
     * and belongs to the expected post type.
     */
    private function isPublishedPost(
        int $post_id,
        string $expected_type
    ): bool {

        $post = get_post($post_id);

        return (
            $post instanceof \WP_Post &&
            $post->post_type === $expected_type &&
            $post->post_status === 'publish'
        );
    }

    /**
     * Retrieve and sanitize a scalar text value.
     *
     * @param array<string, mixed> $input
     */
    private function getText(
        array $input,
        string $key
    ): string {

        $value = $input[$key] ?? '';

        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        return sanitize_text_field((string) $value);
    }
}
