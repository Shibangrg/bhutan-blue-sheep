<?php

namespace BlueSheep\BusinessManager\Modules\Bookings;

defined('ABSPATH') || exit;

/**
 * Handles booking conversion and booking business rules.
 */
class BookingService
{
    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Booking repository.
     *
     * @var BookingRepository
     */
    private $booking_repository;

    /**
     * Database table names.
     *
     * @var string
     */
    private $quotations_table;

    private $quotation_versions_table;
    private $enquiries_table;
    private $activity_log_table;

    /**
     * Initialize the service.
     *
     * @param BookingRepository|null $booking_repository
     */
    public function __construct(?BookingRepository $booking_repository = null)
    {
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->booking_repository = $booking_repository
            ?? new BookingRepository();

        $this->quotations_table = $wpdb->prefix . 'bs_quotations';
        $this->quotation_versions_table = $wpdb->prefix . 'bs_quotation_versions';
        $this->enquiries_table = $wpdb->prefix . 'bs_enquiries';
        $this->activity_log_table = $wpdb->prefix . 'bs_activity_log';
    }

    /**
     * Convert an accepted quotation into a booking.
     *
     * The quotation row is locked during conversion to prevent
     * concurrent requests from creating duplicate bookings.
     *
     * @param int    $quotation_id       Quotation ID.
     * @param string $travel_start_date Travel start date (Y-m-d).
     * @param string $travel_end_date   Travel end date (Y-m-d).
     * @param int    $traveller_count    Confirmed traveller count.
     * @param string $reason            Optional conversion notes.
     *
     * @return int|\WP_Error Booking ID or error.
     */
    public function createBooking(
        int $quotation_id,
        string $travel_start_date,
        string $travel_end_date,
        int $traveller_count,
        string $reason = ''
    ) {
        if ($quotation_id <= 0) {
            return new \WP_Error(
                'invalid_quotation',
                'A valid quotation is required.'
            );
        }

        if (!$this->isValidDate($travel_start_date)) {
            return new \WP_Error(
                'invalid_start_date',
                'Enter a valid travel start date.'
            );
        }

        if (!$this->isValidDate($travel_end_date)) {
            return new \WP_Error(
                'invalid_end_date',
                'Enter a valid travel end date.'
            );
        }

        if ($travel_start_date > $travel_end_date) {
            return new \WP_Error(
                'invalid_date_range',
                'The travel end date cannot be earlier than the start date.'
            );
        }

        if ($traveller_count < 1) {
            return new \WP_Error(
                'invalid_traveller_count',
                'The traveller count must be at least one.'
            );
        }

        $reason = sanitize_textarea_field($reason);

        /*
         * Start transaction.
         */
        if ($this->wpdb->query('START TRANSACTION') === false) {
            return new \WP_Error(
                'transaction_start_failed',
                'Unable to start the booking transaction.'
            );
        }

        try {
            /*
             * Lock the quotation row.
             *
             * This serializes conversion attempts for the same
             * quotation, even though the bookings table does not
             * have a unique constraint on quotation_id.
             */
            $quotation = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "
                    SELECT
                        id,
                        enquiry_id,
                        customer_id,
                        current_version_id,
                        status
                    FROM {$this->quotations_table}
                    WHERE id = %d
                    FOR UPDATE
                    ",
                    $quotation_id
                ),
                ARRAY_A
            );

            if (!$quotation) {
                throw new \RuntimeException(
                    'The requested quotation could not be found.'
                );
            }

            $accepted_version_id = (int) $quotation['current_version_id'];

            if ($accepted_version_id <= 0) {
                throw new \RuntimeException(
                    'The quotation does not have a current version.'
                );
            }

            /*
             * Verify the current quotation version.
             */
            $version = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "
                    SELECT
                        id,
                        quotation_id,
                        version_number,
                        status,
                        accepted_at
                    FROM {$this->quotation_versions_table}
                    WHERE id = %d
                      AND quotation_id = %d
                    FOR UPDATE
                    ",
                    $accepted_version_id,
                    $quotation_id
                ),
                ARRAY_A
            );

            if (!$version) {
                throw new \RuntimeException(
                    'The current quotation version could not be found.'
                );
            }

            if ($version['status'] !== 'accepted' || empty($version['accepted_at'])) {
                throw new \RuntimeException(
                    'Only an accepted quotation version can be converted into a booking.'
                );
            }

            /*
             * Prevent duplicate booking conversion.
             */
            $existing_booking_id = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "
                    SELECT id
                    FROM {$this->wpdb->prefix}bs_bookings
                    WHERE quotation_id = %d
                    LIMIT 1
                    ",
                    $quotation_id
                )
            );

            if ($existing_booking_id) {
                throw new \RuntimeException(
                    'A booking already exists for this quotation.'
                );
            }

            /*
             * Verify the associated enquiry.
             *
             * Only a quoted enquiry can transition to converted.
             */
            $enquiry = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "
                    SELECT id, status
                    FROM {$this->enquiries_table}
                    WHERE id = %d
                    FOR UPDATE
                    ",
                    (int) $quotation['enquiry_id']
                ),
                ARRAY_A
            );

            if (!$enquiry) {
                throw new \RuntimeException(
                    'The enquiry associated with this quotation could not be found.'
                );
            }

            if ($enquiry['status'] !== 'quoted') {
                throw new \RuntimeException(
                    'The enquiry must have quoted status before it can be converted.'
                );
            }

            /*
             * Generate a unique booking reference.
             */
            $booking_reference = $this->generateBookingReference();

            /*
             * Create booking.
             *
             * A newly created booking is not automatically confirmed.
             */
            $now = current_time('mysql', true);

            $booking_id = $this->booking_repository->create([
                'customer_id' => (int) $quotation['customer_id'],
                'quotation_id' => $quotation_id,
                'accepted_version_id' => $accepted_version_id,
                'booking_reference' => $booking_reference,
                'travel_start_date' => $travel_start_date,
                'travel_end_date' => $travel_end_date,
                'traveller_count' => $traveller_count,
                'status' => 'pending_confirmation',
                'confirmed_at' => null,
                'confirmation_exception_by' => null,
                'confirmation_exception_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($booking_id === false) {
                throw new \RuntimeException(
                    'The booking record could not be created.'
                );
            }

            /*
             * Update enquiry status.
             *
             * The conditional update protects against unexpected
             * changes to the enquiry status.
             */
            $updated = $this->wpdb->update(
                $this->enquiries_table,
                [
                    'status' => 'converted',
                    'updated_at' => $now,
                ],
                [
                    'id' => (int) $quotation['enquiry_id'],
                    'status' => 'quoted',
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                    '%s',
                ]
            );

            if ($updated !== 1) {
                throw new \RuntimeException(
                    'The enquiry status could not be updated.'
                );
            }

            /*
             * Record booking creation in activity log.
             */
            $user_id = get_current_user_id();

            $booking_description = sprintf(
                'Booking %s was created from quotation %d, version %d.',
                $booking_reference,
                $quotation_id,
                (int) $version['version_number']
            );

            $booking_log = $this->wpdb->insert(
                $this->activity_log_table,
                [
                    'user_id' => $user_id > 0 ? $user_id : null,
                    'entity_type' => 'booking',
                    'entity_id' => $booking_id,
                    'action' => 'booking_created',
                    'description' => $booking_description,
                    'created_at' => $now,
                    'previous_status' => null,
                    'new_status' => 'pending_confirmation',
                    'reason' => $reason !== '' ? $reason : null,
                ],
                [
                    '%d',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($booking_log === false) {
                throw new \RuntimeException(
                    'The booking activity record could not be created.'
                );
            }

            /*
             * Record enquiry status transition.
             */
            $enquiry_description = sprintf(
                'Enquiry converted into booking %s from accepted quotation %d.',
                $booking_reference,
                $quotation_id
            );

            $enquiry_log = $this->wpdb->insert(
                $this->activity_log_table,
                [
                    'user_id' => $user_id > 0 ? $user_id : null,
                    'entity_type' => 'enquiry',
                    'entity_id' => (int) $quotation['enquiry_id'],
                    'action' => 'enquiry_converted_to_booking',
                    'description' => $enquiry_description,
                    'created_at' => $now,
                    'previous_status' => 'quoted',
                    'new_status' => 'converted',
                    'reason' => $reason !== '' ? $reason : null,
                ],
                [
                    '%d',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($enquiry_log === false) {
                throw new \RuntimeException(
                    'The enquiry activity record could not be created.'
                );
            }

            /*
             * Commit all changes together.
             */
            if ($this->wpdb->query('COMMIT') === false) {
                throw new \RuntimeException(
                    'The booking transaction could not be committed.'
                );
            }

            return (int) $booking_id;
        } catch (\Throwable $exception) {
            $this->wpdb->query('ROLLBACK');

            return new \WP_Error(
                'booking_conversion_failed',
                $exception->getMessage()
            );
        }
    }

    /**
     * Validate a date using strict YYYY-MM-DD formatting.
     *
     * @param string $date Date string.
     * @return bool
     */
    private function isValidDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('!Y-m-d', $date);

        if (!$parsed) {
            return false;
        }

        $errors = \DateTime::getLastErrors();

        if (
            $errors !== false
            && (
                $errors['warning_count'] > 0
                || $errors['error_count'] > 0
            )
        ) {
            return false;
        }

        return $parsed->format('Y-m-d') === $date;
    }

    /**
     * Generate a booking reference.
     *
     * Format: BSB-YYYYMMDD-XXXXXXXX
     *
     * @return string
     */
    private function generateBookingReference(): string
    {
        return sprintf(
            'BSB-%s-%s',
            gmdate('Ymd'),
            strtoupper(bin2hex(random_bytes(4)))
        );
    }
}
