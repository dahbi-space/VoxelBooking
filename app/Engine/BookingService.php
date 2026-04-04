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
        // Exceptions:
        //   1. Admin-created bookings bypass consent (consent is a customer action).
        //   2. Internal reschedule carry-forward bypasses when original had no consent
        //      (avoids manufacturing false consent evidence — §23-Legal-Logging).
        $requiresConsent = (int) ($tenant['requires_consent'] ?? 1) === 1;
        $isAdminSource = ($bookingData['source'] ?? 'web') === 'admin';
        $skipConsentEnforcement = !empty($bookingData['_skip_consent_enforcement']);

        if ($requiresConsent && !$consentGiven && !$isAdminSource && !$skipConsentEnforcement) {
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
            'party_size', 'notes', 'internal_notes', 'customer_timezone', 'source', 'status',
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

        // v1: only timeslot-pattern bookings are reschedulable.
        // Resource/capacity/event patterns have different availability models
        // and will be supported in future releases.
        if (($booking['booking_pattern'] ?? 'timeslot') !== 'timeslot') {
            return ['allowed' => false, 'reason' => 'pattern_not_supported'];
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

    /**
     * Reschedule a booking to a new date/time.
     *
     * Creates a replacement booking, marks the original as 'rescheduled',
     * links via rescheduled_to_id, and logs an audit event.
     *
     * Only timeslot-pattern bookings in 'confirmed' status are supported.
     * The caller is responsible for post-commit side effects (email, staff notification).
     *
     * @param string $bookingId  The original booking ID
     * @param array  $tenant     The tenant record
     * @param string $newDate    Target date (YYYY-MM-DD)
     * @param string $newTime    Target time (HH:MM)
     * @param string $actorType  'customer' for self-service, 'operator'/'business_user' for admin
     * @param string $source     'web' for self-service, 'admin' for admin-initiated
     *
     * @return array{
     *   new_booking_id: string,
     *   old_booking: array,
     *   new_start: string,
     *   new_end: string,
     *   new_date: string,
     *   new_time: string,
     * }
     *
     * @throws \RuntimeException With coded messages for the caller to map to HTTP responses
     */
    public static function rescheduleBooking(
        string $bookingId,
        array $tenant,
        string $newDate,
        string $newTime,
        string $actorType = 'customer',
        string $source = 'web',
    ): array {
        $booking = self::findById($bookingId);
        if (!$booking || $booking['tenant_id'] !== $tenant['id']) {
            throw new \RuntimeException('not_found');
        }

        // Only confirmed bookings can be rescheduled
        $check = self::canReschedule($booking, $tenant);
        if (!$check['allowed']) {
            throw new \RuntimeException($check['reason']);
        }

        // Only timeslot pattern supported in v1
        $pattern = $booking['booking_pattern'] ?? 'timeslot';
        if ($pattern !== 'timeslot') {
            throw new \RuntimeException('pattern_not_supported');
        }

        // Validate date format
        if ($newDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
            throw new \RuntimeException('invalid_date');
        }

        // Validate time format
        if ($newTime === '' || !preg_match('/^\d{2}:\d{2}$/', $newTime)) {
            throw new \RuntimeException('invalid_time');
        }

        $tenantId = $booking['tenant_id'];
        $tz = new \DateTimeZone($tenant['timezone'] ?? 'UTC');
        $newStartDt = new \DateTimeImmutable("{$newDate} {$newTime}", $tz);

        // Resolve duration from the original booking
        $origStart = new \DateTimeImmutable($booking['start_datetime'], $tz);
        $origEnd = new \DateTimeImmutable($booking['end_datetime'], $tz);
        $durationMinutes = (int) (($origEnd->getTimestamp() - $origStart->getTimestamp()) / 60);
        $newEndDt = $newStartDt->modify("+{$durationMinutes} minutes");

        // Same-slot check
        if ($newStartDt->format('Y-m-d H:i') === $origStart->format('Y-m-d H:i')) {
            throw new \RuntimeException('same_slot');
        }

        // Timeslot availability check + booking creation in transaction
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Lock + recheck availability
            $lockSql = "SELECT `id` FROM `bookings`
                        WHERE `tenant_id` = ? AND `status` IN ('confirmed', 'rescheduled')
                        AND DATE(`start_datetime`) = ?";
            $lockParams = [$tenantId, $newDate];
            if ($booking['staff_id']) {
                $lockSql .= ' AND `staff_id` = ?';
                $lockParams[] = $booking['staff_id'];
            }
            $lockSql .= ' FOR UPDATE';
            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute($lockParams);

            $availResult = TimeSlotCalculator::getAvailableSlots(
                $tenant, $newDate, $booking['service_id'], $booking['staff_id']
            );

            $stillAvailable = false;
            foreach ($availResult['slots'] as $slot) {
                if ($slot['time'] === $newTime) {
                    $stillAvailable = true;
                    break;
                }
            }

            if (!$stillAvailable) {
                $pdo->rollBack();
                throw new \RuntimeException('slot_unavailable');
            }

            // Create the replacement booking
            $newBookingData = [
                'tenant_id'       => $tenantId,
                'customer_id'     => $booking['customer_id'],
                'booking_pattern' => $pattern,
                'start_datetime'  => $newStartDt->format('Y-m-d H:i:s'),
                'end_datetime'    => $newEndDt->format('Y-m-d H:i:s'),
                'source'          => $source,
                'status'          => 'confirmed',
            ];

            // Carry forward optional fields from the original booking
            foreach (['service_id', 'staff_id', 'resource_id', 'event_id', 'party_size', 'notes', 'internal_notes', 'customer_timezone'] as $field) {
                if (!empty($booking[$field])) {
                    $newBookingData[$field] = $booking[$field];
                }
            }

            // Preserve custom field data (customer answers)
            if (!empty($booking['custom_field_data'])) {
                $newBookingData['custom_field_data'] = $booking['custom_field_data'];
            }

            // Consent handling: carry forward ONLY if original booking had consent.
            // Admin-created and legacy bookings without consent records must NOT
            // manufacture false consent evidence. §23-Legal-Logging requires consent
            // evidence to reflect a genuine customer action.
            $originalHadConsent = !empty($booking['consent_given_at']);

            // When original had no consent, set internal flag to bypass enforcement
            // without fabricating evidence (the new booking will have NULL consent fields)
            if (!$originalHadConsent) {
                $newBookingData['_skip_consent_enforcement'] = true;
            }

            $result = self::createBooking($newBookingData, $tenant, $originalHadConsent);

            // Overwrite with original consent evidence (preserves exact timestamp
            // and text from the initial booking, not a freshly generated one)
            if ($originalHadConsent) {
                Database::execute(
                    "UPDATE `bookings` SET `consent_given_at` = ?, `consent_text_shown` = ? WHERE `id` = ?",
                    [$booking['consent_given_at'], $booking['consent_text_shown'] ?? null, $result['id']]
                );
            }

            // Mark the original as rescheduled and link to the new booking
            Database::execute(
                "UPDATE `bookings` SET `status` = 'rescheduled', `rescheduled_to_id` = ?, `updated_at` = NOW() WHERE `id` = ?",
                [$result['id'], $bookingId]
            );

            AuditLog::log('booking.rescheduled', 'booking', $bookingId, [
                'old_start'      => $booking['start_datetime'],
                'old_end'        => $booking['end_datetime'],
                'new_booking_id' => $result['id'],
                'new_start'      => $newStartDt->format('Y-m-d H:i:s'),
                'new_end'        => $newEndDt->format('Y-m-d H:i:s'),
                'actor_type'     => $actorType,
            ], $tenantId);

            // Cancel unsent reminders for the original booking (belt-and-suspenders
            // with ReminderJob's own status check — ensures no stale reminder fires
            // between commit and next cron run)
            Database::execute(
                "UPDATE `reminders` SET `sent_at` = NOW() WHERE `booking_id` = ? AND `sent_at` IS NULL",
                [$bookingId]
            );

            $pdo->commit();

            // Post-commit: schedule reminder for the new booking (dedupe-safe)
            self::scheduleReminderIfNeeded($tenant, $result['id'], $newStartDt);

            return [
                'new_booking_id' => $result['id'],
                'old_booking'    => $booking,
                'new_start'      => $newStartDt->format('Y-m-d H:i:s'),
                'new_end'        => $newEndDt->format('Y-m-d H:i:s'),
                'new_date'       => $newDate,
                'new_time'       => $newTime,
            ];

        } catch (\RuntimeException $e) {
            // Re-throw RuntimeException as-is (these are our coded errors)
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error('Booking reschedule failed', [
                'booking' => $bookingId,
                'error'   => $e->getMessage(),
            ]);
            throw new \RuntimeException('reschedule_failed');
        }
    }

    /**
     * Schedule a reminder for a booking if the tenant has reminders enabled.
     */
    private static function scheduleReminderIfNeeded(array $tenant, string $bookingId, \DateTimeImmutable $startDt): void
    {
        if ((int) ($tenant['send_reminders'] ?? 0) !== 1) {
            return;
        }

        $reminderHours = max(1, (int) ($tenant['reminder_hours_before'] ?? 24));
        $tz = new \DateTimeZone($tenant['timezone'] ?? 'UTC');
        $reminderAt = $startDt->modify("-{$reminderHours} hours");

        if ($reminderAt <= new \DateTimeImmutable('now', $tz)) {
            return;
        }

        try {
            $reminderId = Ulid::generate();
            Database::execute(
                "INSERT INTO `reminders` (`id`, `booking_id`, `tenant_id`, `scheduled_at`) VALUES (?, ?, ?, ?)",
                [(string) $reminderId, $bookingId, $tenant['id'], $reminderAt->format('Y-m-d H:i:s')]
            );
        } catch (\Throwable $e) {
            Logger::error('Failed to schedule reschedule reminder', [
                'booking' => $bookingId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
