<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Customer anonymization engine.
 *
 * Per .ai/23-VoxelBooking-Legal-Logging.md §7 (Anonymization Specification)
 * and PRD §XIX (Privacy Architecture).
 *
 * Anonymizes customer PII while preserving:
 * - Booking structure (dates, services, statuses) for operational history
 * - Consent records (consent_given_at, consent_text_shown) — GDPR Art. 7(1) evidence
 * - Internal notes (operator's operational data, not customer PII)
 *
 * Fields transformed:
 * - customers.name          → "Deleted"
 * - customers.email         → SHA-256(original_email) (for deduplication)
 * - customers.phone         → NULL
 * - customers.notes         → NULL
 * - bookings.notes          → NULL (customer-facing notes)
 * - bookings.custom_field_data → NULL
 * - email_log.to_email      → SHA-256(original_email)
 *
 * Fields preserved:
 * - bookings.internal_notes       (operator data)
 * - bookings.consent_given_at     (legal hold)
 * - bookings.consent_text_shown   (legal hold)
 * - All booking dates, times, services, statuses, sources
 */
final class CustomerAnonymizer
{
    /**
     * Anonymize a single customer and all related data.
     *
     * @param string $customerId The customer ULID
     * @param string $reason     Why the anonymization happened (for audit log)
     *
     * @return array{customer_id: string, bookings_affected: int, emails_affected: int}
     *
     * @throws \RuntimeException if the customer doesn't exist or is already anonymized
     */
    public static function anonymize(string $customerId, string $reason = 'manual'): array
    {
        // Load customer
        $customer = self::loadCustomer($customerId);

        if ($customer === null) {
            throw new \RuntimeException("Customer {$customerId} not found");
        }

        if ((int) ($customer['is_anonymized'] ?? 0) === 1) {
            throw new \RuntimeException("Customer {$customerId} is already anonymized");
        }

        $emailHash = hash('sha256', strtolower(trim($customer['email'])));

        // Run all anonymization in a transaction
        Database::execute('START TRANSACTION');

        try {
            // 1. Anonymize customer record
            Database::execute(
                'UPDATE `customers` SET
                    `name` = ?,
                    `email` = ?,
                    `phone` = NULL,
                    `notes` = NULL,
                    `is_anonymized` = 1,
                    `anonymized_at` = NOW(),
                    `updated_at` = NOW()
                WHERE `id` = ?',
                ['Deleted', $emailHash, $customerId]
            );

            // 2. Anonymize booking PII (preserve consent + internal notes + structure)
            $bookingsAffected = Database::execute(
                'UPDATE `bookings` SET
                    `notes` = NULL,
                    `custom_field_data` = NULL,
                    `updated_at` = NOW()
                WHERE `customer_id` = ?',
                [$customerId]
            );

            // 3. Anonymize email log (hash the to_email)
            $emailsAffected = Database::execute(
                'UPDATE `email_log` SET
                    `to_email` = ?
                WHERE `booking_id` IN (
                    SELECT `id` FROM `bookings` WHERE `customer_id` = ?
                )',
                [$emailHash, $customerId]
            );

            Database::execute('COMMIT');
        } catch (\Throwable $e) {
            Database::execute('ROLLBACK');
            throw new \RuntimeException('Anonymization failed: ' . $e->getMessage(), 0, $e);
        }

        $result = [
            'customer_id'       => $customerId,
            'bookings_affected' => $bookingsAffected,
            'emails_affected'   => $emailsAffected,
        ];

        // Audit log the anonymization
        AuditLog::log(
            'customer.anonymized',
            'customer',
            $customerId,
            [
                'reason'            => $reason,
                'tenant_id'         => $customer['tenant_id'],
                'bookings_affected' => $bookingsAffected,
                'emails_affected'   => $emailsAffected,
            ],
            $customer['tenant_id'],
        );

        return $result;
    }

    /**
     * Batch anonymize customers for a tenant based on retention policy.
     *
     * Selects customers whose most recent booking is older than retention period
     * and whose bookings are all in a terminal state (completed, cancelled, no_show).
     *
     * @param string $tenantId           Tenant ULID
     * @param int    $retentionMonths    Months after which data is anonymized
     * @param int    $batchSize          Max customers to process per run (prevents timeout)
     *
     * @return array{processed: int, skipped: int, errors: int}
     */
    public static function processRetention(
        string $tenantId,
        int $retentionMonths = 24,
        int $batchSize = 50,
    ): array {
        if ($retentionMonths <= 0) {
            return ['processed' => 0, 'skipped' => 0, 'errors' => 0];
        }

        // Find customers eligible for anonymization:
        // - Not already anonymized
        // - Last booking is older than retention period
        // - All bookings are in terminal state
        $candidates = Database::query(
            'SELECT c.`id`, c.`email`
             FROM `customers` c
             WHERE c.`tenant_id` = ?
               AND c.`is_anonymized` = 0
               AND c.`last_booking_at` IS NOT NULL
               AND c.`last_booking_at` < DATE_SUB(NOW(), INTERVAL ? MONTH)
               AND NOT EXISTS (
                   SELECT 1 FROM `bookings` b
                   WHERE b.`customer_id` = c.`id`
                     AND b.`status` NOT IN (\'completed\', \'cancelled\', \'no_show\')
               )
             ORDER BY c.`last_booking_at` ASC
             LIMIT ?',
            [$tenantId, $retentionMonths, $batchSize]
        );

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($candidates as $candidate) {
            try {
                self::anonymize($candidate['id'], 'retention_cron');
                $processed++;
            } catch (\RuntimeException $e) {
                if (str_contains($e->getMessage(), 'already anonymized')) {
                    $skipped++;
                } else {
                    $errors++;
                    Logger::warning('Retention anonymization failed', [
                        'customer_id' => $candidate['id'],
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        }

        return [
            'processed' => $processed,
            'skipped'   => $skipped,
            'errors'    => $errors,
        ];
    }

    /**
     * Load a customer record by ID.
     *
     * @return array<string, mixed>|null
     */
    private static function loadCustomer(string $customerId): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `customers` WHERE `id` = ? LIMIT 1',
            [$customerId]
        );

        return $rows[0] ?? null;
    }
}
