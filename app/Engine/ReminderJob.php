<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Reminder processing cron job.
 *
 * Per PRD §VII — sends customer reminder emails for upcoming bookings.
 *
 * Processing rules:
 * - Queries reminders where sent_at IS NULL and scheduled_at <= NOW()
 * - Batches up to 20 per run to avoid long-running cron
 * - Increments attempts on failure, caps at 3 attempts
 * - Sends via Mailer::sendReminder() using the confirmation email layout
 * - Skips cancelled/completed bookings
 */
final class ReminderJob
{
    private const BATCH_SIZE   = 20;
    private const MAX_ATTEMPTS = 3;

    /**
     * Run reminder processing.
     *
     * @return array{sent: int, skipped: int, failed: int, errors: string[]}
     */
    public static function run(): array
    {
        $result = [
            'sent'    => 0,
            'skipped' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];

        try {
            $pending = Database::query(
                "SELECT r.`id`, r.`booking_id`, r.`tenant_id`, r.`attempts`
                 FROM `reminders` r
                 WHERE r.`sent_at` IS NULL
                   AND r.`scheduled_at` <= NOW()
                   AND r.`attempts` < ?
                 ORDER BY r.`scheduled_at` ASC
                 LIMIT ?",
                [self::MAX_ATTEMPTS, self::BATCH_SIZE]
            );
        } catch (\Throwable $e) {
            $result['errors'][] = 'Query failed: ' . $e->getMessage();
            Logger::error('ReminderJob: query failed', ['error' => $e->getMessage()]);
            return $result;
        }

        foreach ($pending as $reminder) {
            try {
                self::processReminder($reminder, $result);
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = "Reminder {$reminder['id']}: " . $e->getMessage();
                Logger::error('ReminderJob: unhandled error', [
                    'reminder_id' => $reminder['id'],
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Process a single reminder row.
     */
    private static function processReminder(array $reminder, array &$result): void
    {
        // Load booking with joined data
        $rows = Database::query(
            "SELECT b.`id`, b.`status`, b.`start_datetime`, b.`end_datetime`,
                    b.`service_id`, b.`staff_id`, b.`customer_id`,
                    b.`booking_pattern`,
                    c.`name` AS customer_name, c.`email` AS customer_email,
                    t.`name` AS tenant_name, t.`brand_color`,
                    t.`send_reminders`, t.`slug` AS tenant_slug
             FROM `bookings` b
             JOIN `customers` c ON c.`id` = b.`customer_id`
             JOIN `tenants` t ON t.`id` = b.`tenant_id`
             WHERE b.`id` = ?
             LIMIT 1",
            [$reminder['booking_id']]
        );

        if (empty($rows)) {
            // Booking deleted — mark as sent to skip permanently
            self::markSent($reminder['id']);
            $result['skipped']++;
            return;
        }

        $booking = $rows[0];

        // Skip if booking is cancelled, completed, rescheduled, no_show, or still pending approval
        if (in_array($booking['status'], ['cancelled', 'completed', 'rescheduled', 'no_show', 'pending'], true)) {
            self::markSent($reminder['id']);
            $result['skipped']++;
            return;
        }

        // Skip if tenant disabled reminders since the booking was created
        if ((int) ($booking['send_reminders'] ?? 0) !== 1) {
            self::markSent($reminder['id']);
            $result['skipped']++;
            return;
        }

        // Skip if customer has no email
        $email = trim($booking['customer_email'] ?? '');
        if ($email === '' || str_starts_with($email, 'anon_')) {
            self::markSent($reminder['id']);
            $result['skipped']++;
            return;
        }

        // Resolve service and staff names
        $serviceName = null;
        if ($booking['service_id']) {
            $svc = Database::query('SELECT `name` FROM `services` WHERE `id` = ? LIMIT 1', [$booking['service_id']]);
            $serviceName = $svc[0]['name'] ?? null;
        }

        $staffName = null;
        if ($booking['staff_id']) {
            $stf = Database::query('SELECT `name` FROM `staff` WHERE `id` = ? LIMIT 1', [$booking['staff_id']]);
            $staffName = $stf[0]['name'] ?? null;
        }

        // Build booking data for the email
        $startDt = new \DateTimeImmutable($booking['start_datetime']);
        $endDt   = new \DateTimeImmutable($booking['end_datetime']);

        $emailResult = Mailer::sendReminder(
            $email,
            $booking['customer_name'],
            [
                'date'           => $startDt->format('Y-m-d'),
                'formatted_date' => Locale::dateLong($startDt),
                'time'           => $startDt->format('H:i'),
                'end_time'       => $endDt->format('H:i'),
            ],
            $serviceName,
            $staffName,
            $booking['tenant_name'],
            $reminder['tenant_id'],
            $reminder['booking_id'],
            $booking['brand_color'] ?? '#2563EB',
        );

        // Handle disabled-by-tenant (skipped emails return ['skipped' => true])
        if ($emailResult['skipped'] ?? false) {
            self::markSent($reminder['id']);
            $result['skipped']++;
            return;
        }

        if ($emailResult['sent'] ?? false) {
            self::markSent($reminder['id']);
            $result['sent']++;
        } else {
            // Increment attempt counter
            Database::execute(
                "UPDATE `reminders` SET `attempts` = `attempts` + 1, `last_error` = ? WHERE `id` = ?",
                [mb_substr($emailResult['error'] ?? 'Unknown', 0, 500), $reminder['id']]
            );
            $result['failed']++;
            $result['errors'][] = "Reminder {$reminder['id']}: " . ($emailResult['error'] ?? 'send failed');
        }
    }

    /**
     * Mark a reminder as sent.
     */
    private static function markSent(string $reminderId): void
    {
        Database::execute(
            "UPDATE `reminders` SET `sent_at` = NOW() WHERE `id` = ?",
            [$reminderId]
        );
    }
}
