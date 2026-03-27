<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Retention cron job engine.
 *
 * Per PRD §VII (Reminder Processing / Data retention processing)
 * and .ai/23-VoxelBooking-Legal-Logging.md §7 (Retention Matrix).
 *
 * Called via GET /cron/retention?token={cron_token}.
 *
 * Processing steps per cron run:
 * 1. Customer anonymization: per-tenant, based on data_retention_months
 * 2. Audit log cleanup: entries older than audit_retention_days (default 365)
 * 3. Email log cleanup: entries older than data_retention_months + 90 days
 * 4. Rate limit cleanup: entries older than 1 hour
 *
 * Each step is independent — failure in one does not block others.
 * All actions are audit-logged.
 */
final class RetentionJob
{
    /**
     * Run all retention processing.
     *
     * @return array{
     *     anonymization: array<string, array{processed: int, skipped: int, errors: int}>,
     *     audit_cleanup: int,
     *     email_cleanup: int,
     *     rate_limit_cleanup: int,
     *     errors: string[]
     * }
     */
    public static function run(): array
    {
        $result = [
            'anonymization'      => [],
            'audit_cleanup'      => 0,
            'email_cleanup'      => 0,
            'rate_limit_cleanup' => 0,
            'errors'             => [],
        ];

        // 1. Per-tenant customer anonymization
        try {
            $result['anonymization'] = self::processCustomerRetention();
        } catch (\Throwable $e) {
            $result['errors'][] = 'Customer retention failed: ' . $e->getMessage();
            Logger::error('Retention: customer anonymization failed', ['error' => $e->getMessage()]);
        }

        // 2. Audit log cleanup
        try {
            $result['audit_cleanup'] = self::processAuditCleanup();
        } catch (\Throwable $e) {
            $result['errors'][] = 'Audit cleanup failed: ' . $e->getMessage();
            Logger::error('Retention: audit cleanup failed', ['error' => $e->getMessage()]);
        }

        // 3. Email log cleanup
        try {
            $result['email_cleanup'] = self::processEmailLogCleanup();
        } catch (\Throwable $e) {
            $result['errors'][] = 'Email log cleanup failed: ' . $e->getMessage();
            Logger::error('Retention: email log cleanup failed', ['error' => $e->getMessage()]);
        }

        // 4. Rate limit cleanup
        try {
            $result['rate_limit_cleanup'] = self::processRateLimitCleanup();
        } catch (\Throwable $e) {
            $result['errors'][] = 'Rate limit cleanup failed: ' . $e->getMessage();
            Logger::error('Retention: rate limit cleanup failed', ['error' => $e->getMessage()]);
        }

        // Log the overall run
        AuditLog::log(
            'system.retention_run',
            'system',
            null,
            [
                'tenants_processed' => count($result['anonymization']),
                'audit_deleted'     => $result['audit_cleanup'],
                'emails_deleted'    => $result['email_cleanup'],
                'rate_limits_deleted' => $result['rate_limit_cleanup'],
                'error_count'       => count($result['errors']),
            ],
            actorType: 'system',
            actorId: null,
        );

        return $result;
    }

    /**
     * Process customer anonymization for all tenants.
     *
     * @return array<string, array{processed: int, skipped: int, errors: int}>
     */
    private static function processCustomerRetention(): array
    {
        // Load all active tenants with retention enabled
        $tenants = Database::query(
            'SELECT `id`, `name`, `data_retention_months`
             FROM `tenants`
             WHERE `status` = \'active\'
               AND `data_retention_months` > 0'
        );

        $results = [];

        foreach ($tenants as $tenant) {
            $results[$tenant['id']] = CustomerAnonymizer::processRetention(
                $tenant['id'],
                (int) $tenant['data_retention_months'],
                50, // batch size per PRD
            );
        }

        return $results;
    }

    /**
     * Clean up old audit log entries.
     *
     * Default: 365 days. Configurable via 'audit_retention_days' setting.
     *
     * @return int Number of entries deleted
     */
    private static function processAuditCleanup(): int
    {
        $retentionDays = 365;

        try {
            $rows = Database::query(
                "SELECT `value` FROM `settings` WHERE `key` = 'audit_retention_days' LIMIT 1"
            );
            if (!empty($rows[0]['value'])) {
                $retentionDays = max(30, (int) $rows[0]['value']); // minimum 30 days
            }
        } catch (\Throwable) {
            // Use default
        }

        return AuditLog::cleanup($retentionDays);
    }

    /**
     * Clean up old email log entries.
     *
     * Retention: data_retention_months + 90 days per tenant.
     * Entries without a tenant use system default (24 + 3 = 27 months).
     *
     * @return int Total entries deleted
     */
    private static function processEmailLogCleanup(): int
    {
        if (!Database::tableExists('email_log')) {
            return 0;
        }

        // System-level cleanup: entries older than 27 months without a tenant
        $count = Database::execute(
            'DELETE FROM `email_log`
             WHERE `tenant_id` IS NULL
               AND `sent_at` < DATE_SUB(NOW(), INTERVAL 27 MONTH)'
        );

        // Per-tenant cleanup: data_retention_months + 90 days
        $tenants = Database::query(
            'SELECT `id`, `data_retention_months` FROM `tenants` WHERE `data_retention_months` > 0'
        );

        foreach ($tenants as $tenant) {
            $retentionDays = ((int) $tenant['data_retention_months'] * 30) + 90;
            $count += Database::execute(
                'DELETE FROM `email_log`
                 WHERE `tenant_id` = ?
                   AND `sent_at` < DATE_SUB(NOW(), INTERVAL ? DAY)',
                [$tenant['id'], $retentionDays]
            );
        }

        return $count;
    }

    /**
     * Clean up expired rate limit entries.
     *
     * Entries older than 1 hour are stale and safe to remove.
     *
     * @return int Number of entries deleted
     */
    private static function processRateLimitCleanup(): int
    {
        if (!Database::tableExists('rate_limits')) {
            return 0;
        }

        return Database::execute(
            'DELETE FROM `rate_limits` WHERE `window_start` < DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
    }
}
