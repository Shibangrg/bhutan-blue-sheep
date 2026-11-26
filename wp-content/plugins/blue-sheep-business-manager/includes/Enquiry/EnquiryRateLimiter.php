<?php

namespace BlueSheep\BusinessManager\Enquiry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controls repeated public enquiry submissions.
 *
 * Uses WordPress transients and stores no raw IP address.
 */
final class EnquiryRateLimiter
{
    /**
     * Maximum submission attempts per window.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Rate limit window: 15 minutes.
     */
    private const WINDOW = 15 * MINUTE_IN_SECONDS;

    /**
     * Generate a transient key using a hashed client IP.
     */
    private function getTransientKey(): ?string
    {
        if (
            !isset($_SERVER['REMOTE_ADDR']) ||
            !is_string($_SERVER['REMOTE_ADDR'])
        ) {
            return null;
        }

        $ip_address = trim($_SERVER['REMOTE_ADDR']);

        if (filter_var(
            $ip_address,
            FILTER_VALIDATE_IP
        ) === false) {
            return null;
        }

        $hash = hash_hmac(
            'sha256',
            $ip_address,
            wp_salt('auth')
        );

        return 'bsbm_enquiry_rate_' . $hash;
    }

    /**
     * Determine whether the submission limit is reached.
     */
    public function isLimited(): bool
    {
        $key = $this->getTransientKey();

        if ($key === null) {
            return false;
        }

        $attempts = get_transient($key);

        return (
            $attempts !== false &&
            (int) $attempts >= self::MAX_ATTEMPTS
        );
    }

    /**
     * Record a submission attempt.
     */
    public function recordAttempt(): void
    {
        $key = $this->getTransientKey();

        if ($key === null) {
            return;
        }

        $attempts = get_transient($key);

        if ($attempts === false) {
            $attempts = 0;
        }

        $attempts = min(
            (int) $attempts + 1,
            self::MAX_ATTEMPTS
        );

        set_transient(
            $key,
            $attempts,
            self::WINDOW
        );
    }
}
