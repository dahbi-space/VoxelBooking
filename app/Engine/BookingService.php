<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Booking creation service with GDPR consent evidence capture.
 *
 * Per PRD Phase 1 and .ai/23-VoxelBooking-Legal-Logging.md §5:
 * - Consent must be recorded with the exact text shown
 * - consent_text_shown stores verbatim what the customer agreed to
 * - Changing the tenant's consent_text does not retroactively change recorded consents
 * - Consent records are legal-hold data and are never anonymized or deleted
 *
 * Every booking creation path (timeslot, resource, capacity, event) MUST go
 * through this service to ensure consent is captured correctly.
 */
final class BookingService
{
    /**
     * Create a booking with GDPR consent evidence.
     *
     * @param array<string, mixed> $bookingData Required keys:
     *   - tenant_id: ULID
     *   - customer_id: ULID
     *   - booking_pattern: 'timeslot'|'resource'|'capacity'|'event'
     *   - start_datetime: 'Y-m-d H:i:s'
     *   - end_datetime: 'Y-m-d H:i:s'
     *   Optional keys: service_id, staff_id, resource_id, event_id,
     *   party_size, notes, customer_timezone, source, custom_field_data
     *
     * @param array<string, mixed> $tenant The tenant record (must contain consent settings)
     * @param bool $consentGiven Whether the customer checked the consent checkbox
     *
     * @return array{id: string, consent_recorded: bool} The created booking ID and consent status
     *
     * @throws \InvalidArgumentException If required fields are missing
     * @throws \RuntimeException If tenant requires consent but it was not given
     */
    public static function createBooking(array $bookingData, array $tenant, bool $consentGiven): array
    {
        // Validate required fields
        $required = ['tenant_id', 'customer_id', 'booking_pattern', 'start_datetime', 'end_datetime'];
        foreach ($required as $field) {
            if (empty($bookingData[$field])) {
                throw new \InvalidArgumentException("Missing required booking field: {$field}");
            }
        }

        // Consent enforcement: if tenant requires consent, it must be given
        // Exception: admin-created bookings bypass consent (consent is a customer
        // action, not an admin action). Consent can be captured later via recordConsent().
        $requiresConsent = (int) ($tenant['requires_consent'] ?? 1) === 1;
        $isAdminSource = ($bookingData['source'] ?? 'web') === 'admin';

        if ($requiresConsent && !$consentGiven && !$isAdminSource) {
            throw new \RuntimeException(
                'Booking requires consent. The customer must agree to the privacy terms before booking.'
            );
        }

        $bookingId = Ulid::generate();

        // Build consent evidence — captures the EXACT text shown at booking time
        $consentGivenAt = null;
        $consentTextShown = null;

        if ($consentGiven && $requiresConsent) {
            $consentGivenAt = date('Y-m-d H:i:s');
            $consentTextShown = self::resolveConsentText($tenant);
        }

        // Build the INSERT
        $columns = [
            'id', 'tenant_id', 'customer_id', 'booking_pattern',
            'start_datetime', 'end_datetime',
        ];
        $values = [
            $bookingId,
            $bookingData['tenant_id'],
            $bookingData['customer_id'],
            $bookingData['booking_pattern'],
            $bookingData['start_datetime'],
            $bookingData['end_datetime'],
        ];

        // Optional fields
        $optionalFields = [
            'service_id', 'staff_id', 'resource_id', 'event_id',
            'party_size', 'notes', 'customer_timezone', 'source', 'status',
        ];

        foreach ($optionalFields as $field) {
            if (isset($bookingData[$field])) {
                $columns[] = $field;
                $values[] = $bookingData[$field];
            }
        }

        // Custom field data (JSON)
        if (isset($bookingData['custom_field_data'])) {
            $columns[] = 'custom_field_data';
            $values[] = is_string($bookingData['custom_field_data'])
                ? $bookingData['custom_field_data']
                : json_encode($bookingData['custom_field_data']);
        }

        // Consent evidence — always included, NULL when not applicable
        $columns[] = 'consent_given_at';
        $columns[] = 'consent_text_shown';
        $values[] = $consentGivenAt;
        $values[] = $consentTextShown;

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode('`, `', $columns);

        Database::execute(
            "INSERT INTO `bookings` (`{$columnList}`) VALUES ({$placeholders})",
            $values
        );

        // Audit log the booking creation with consent status and source
        AuditLog::log(
            'booking.created',
            'booking',
            $bookingId,
            [
                'booking_pattern'  => $bookingData['booking_pattern'],
                'source'           => $bookingData['source'] ?? 'web',
                'consent_given'    => $consentGiven,
                'consent_recorded' => $consentGivenAt !== null,
            ],
            $bookingData['tenant_id'],
        );

        return [
            'id'               => $bookingId,
            'consent_recorded' => $consentGivenAt !== null,
        ];
    }

    /**
     * Resolve the consent text that should be stored with the booking.
     *
     * This captures the EXACT text the customer saw at booking time.
     * If the tenant has no custom consent text, a sensible default is used.
     * The privacy_policy_url is appended if configured.
     *
     * This text is immutable once recorded — changing the tenant's consent_text
     * does NOT retroactively change existing consent records (GDPR Art. 7(1)).
     */
    public static function resolveConsentText(array $tenant): string
    {
        $text = trim($tenant['consent_text'] ?? '');

        if ($text === '') {
            $text = 'I agree to the processing of my personal data for booking purposes.';
        }

        $policyUrl = trim($tenant['privacy_policy_url'] ?? '');

        if ($policyUrl !== '') {
            $text .= ' Privacy policy: ' . $policyUrl;
        }

        // Truncate to field limit (VARCHAR(500))
        if (mb_strlen($text) > 500) {
            $text = mb_substr($text, 0, 497) . '...';
        }

        return $text;
    }

    /**
     * Record consent evidence on an existing booking.
     *
     * Used when consent is collected after initial booking creation
     * (e.g., admin-created bookings where consent is captured later).
     *
     * Only logs an audit event if the UPDATE actually changed a row.
     * If consent already existed (WHERE consent_given_at IS NULL fails)
     * or the booking ID is invalid, no audit event is emitted.
     */
    public static function recordConsent(string $bookingId, array $tenant): void
    {
        $consentText = self::resolveConsentText($tenant);

        $affectedRows = Database::execute(
            'UPDATE `bookings` SET
                `consent_given_at` = NOW(),
                `consent_text_shown` = ?,
                `updated_at` = NOW()
            WHERE `id` = ? AND `consent_given_at` IS NULL',
            [$consentText, $bookingId]
        );

        // Only audit-log if a row was actually changed
        if ($affectedRows > 0) {
            AuditLog::log(
                'booking.consent_recorded',
                'booking',
                $bookingId,
                ['consent_text_length' => mb_strlen($consentText)],
            );
        }
    }

    /**
     * Check if a booking has consent evidence recorded.
     */
    public static function hasConsent(string $bookingId): bool
    {
        $rows = Database::query(
            'SELECT `consent_given_at` FROM `bookings` WHERE `id` = ? LIMIT 1',
            [$bookingId]
        );

        return isset($rows[0]['consent_given_at']);
    }

    // ── Self-service booking management ──

    /**
     * Find a booking by ID.
     */
    public static function findById(string $bookingId): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `bookings` WHERE `id` = ? LIMIT 1',
            [$bookingId]
        );

        return $rows[0] ?? null;
    }

    /**
     * Find a booking by ID with related entity names for the manage page.
     *
     * Returns the booking row plus service_name, staff_name,
     * resource_name, event_name, and customer email/name.
     */
    public static function findByIdWithDetails(string $bookingId, string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT b.*,
                    s.`name` AS `service_name`,
                    st.`name` AS `staff_name`,
                    r.`name` AS `resource_name`,
                    e.`name` AS `event_name`,
                    e.`location` AS `event_location`,
                    c.`name` AS `customer_name`,
                    c.`email` AS `customer_email`
             FROM `bookings` b
             LEFT JOIN `services` s ON s.`id` = b.`service_id`
             LEFT JOIN `staff` st ON st.`id` = b.`staff_id`
             LEFT JOIN `resources` r ON r.`id` = b.`resource_id`
             LEFT JOIN `events` e ON e.`id` = b.`event_id`
             LEFT JOIN `customers` c ON c.`id` = b.`customer_id`
             WHERE b.`id` = ? AND b.`tenant_id` = ?
             LIMIT 1',
            [$bookingId, $tenantId]
        );

        return $rows[0] ?? null;
    }

    /**
     * Check whether a booking can be cancelled (time-gate check).
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public static function canCancel(array $booking, array $tenant): array
    {
        if ($booking['status'] !== 'confirmed') {
            return ['allowed' => false, 'reason' => 'not_confirmed'];
        }

        if (!(bool) ($tenant['allow_cancellation'] ?? true)) {
            return ['allowed' => false, 'reason' => 'cancellation_disabled'];
        }

        $hoursBeforeLimit = (int) ($tenant['cancellation_hours_before'] ?? 24);

        $tz = new \DateTimeZone($tenant['timezone'] ?? 'UTC');
        $startDt = new \DateTimeImmutable($booking['start_datetime'], $tz);
        $now = new \DateTimeImmutable('now', $tz);

        $diffSeconds = $startDt->getTimestamp() - $now->getTimestamp();
        $diffHours = $diffSeconds / 3600;

        if ($diffHours < $hoursBeforeLimit) {
            return ['allowed' => false, 'reason' => 'too_late'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Cancel a booking.
     *
     * Sets status to 'cancelled', records the timestamp and optional reason,
     * decrements the customer's booking count, and logs an audit event.
     *
     * @throws \RuntimeException If cancellation is not allowed
     */
    public static function cancelBooking(string $bookingId, array $tenant, string $reason = ''): array
    {
        $booking = self::findById($bookingId);
        if (!$booking || $booking['tenant_id'] !== $tenant['id']) {
            throw new \RuntimeException('Booking not found');
        }

        $check = self::canCancel($booking, $tenant);
        if (!$check['allowed']) {
            throw new \RuntimeException('Cannot cancel: ' . $check['reason']);
        }

        // Update booking status
        Database::execute(
            'UPDATE `bookings` SET
                `status` = ?,
                `cancelled_at` = NOW(),
                `cancellation_reason` = ?,
                `updated_at` = NOW()
             WHERE `id` = ?',
            ['cancelled', $reason ?: null, $bookingId]
        );

        // Decrement customer booking count
        Database::execute(
            'UPDATE `customers` SET
                `booking_count` = GREATEST(0, `booking_count` - 1)
             WHERE `id` = ?',
            [$booking['customer_id']]
        );

        // Audit log
        AuditLog::log(
            'booking.cancelled',
            'booking',
            $bookingId,
            [
                'cancelled_by'  => 'customer',
                'reason'        => $reason ?: null,
            ],
            $tenant['id'],
        );

        // Return the updated booking
        $booking['status'] = 'cancelled';
        $booking['cancelled_at'] = date('Y-m-d H:i:s');
        $booking['cancellation_reason'] = $reason ?: null;

        return $booking;
    }

    /**
     * Check whether a booking can be rescheduled (time-gate check).
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public static function canReschedule(array $booking, array $tenant): array
    {
        if ($booking['status'] !== 'confirmed') {
            return ['allowed' => false, 'reason' => 'not_confirmed'];
        }

        if (!(bool) ($tenant['allow_rescheduling'] ?? true)) {
            return ['allowed' => false, 'reason' => 'rescheduling_disabled'];
        }

        $hoursBeforeLimit = (int) ($tenant['rescheduling_hours_before'] ?? 24);

        $tz = new \DateTimeZone($tenant['timezone'] ?? 'UTC');
        $startDt = new \DateTimeImmutable($booking['start_datetime'], $tz);
        $now = new \DateTimeImmutable('now', $tz);

        $diffSeconds = $startDt->getTimestamp() - $now->getTimestamp();
        $diffHours = $diffSeconds / 3600;

        if ($diffHours < $hoursBeforeLimit) {
            return ['allowed' => false, 'reason' => 'too_late'];
        }

        return ['allowed' => true, 'reason' => null];
    }
}
