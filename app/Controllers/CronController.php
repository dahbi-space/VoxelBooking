<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Engine\Database;
use App\Engine\Logger;
use App\Engine\ReminderJob;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\RetentionJob;

/**
 * Cron endpoint controller.
 *
 * All endpoints require the cron_token query parameter.
 * Not listed in any navigation or sitemap.
 *
 * GET /cron/run?token={cron_token}          — unified cron entry point
 * GET /cron/retention?token={cron_token}    — backward-compatible alias
 */
final class CronController
{
    /**
     * Unified cron entry point.
     *
     * Runs all scheduled tasks: retention processing (and reminders, when implemented).
     * Authenticates via cron_token query parameter.
     */
    public function run(Request $request): Response
    {
        $token = $request->query('token');

        if ($token === '' || !$this->validateCronToken($token)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        $results = [];
        $errors  = [];

        // 1. Retention processing
        try {
            $results['retention'] = RetentionJob::run();
            if (!empty($results['retention']['errors'])) {
                $errors = array_merge($errors, $results['retention']['errors']);
            }
        } catch (\Throwable $e) {
            Logger::error('Cron: retention processing failed', ['error' => $e->getMessage()]);
            $errors[] = 'Retention processing failed: ' . $e->getMessage();
            $results['retention'] = ['error' => $e->getMessage()];
        }

        // 2. Reminder processing
        try {
            $results['reminders'] = ReminderJob::run();
            if (!empty($results['reminders']['errors'])) {
                $errors = array_merge($errors, $results['reminders']['errors']);
            }
        } catch (\Throwable $e) {
            Logger::error('Cron: reminder processing failed', ['error' => $e->getMessage()]);
            $errors[] = 'Reminder processing failed: ' . $e->getMessage();
            $results['reminders'] = ['error' => $e->getMessage()];
        }

        // Update last run timestamp
        try {
            Database::upsertSetting('cron_last_run', date('Y-m-d H:i:s'));
        } catch (\Throwable) {
            // Non-critical
        }

        $statusCode = empty($errors) ? 200 : 207; // 207 Multi-Status if partial failure

        return Response::json([
            'status'  => empty($errors) ? 'ok' : 'partial',
            'tasks'   => array_keys($results),
            'results' => $results,
        ], $statusCode);
    }

    /**
     * Validate the cron token against the stored value.
     */
    private function validateCronToken(string $token): bool
    {
        try {
            $rows = Database::query(
                "SELECT `value` FROM `settings` WHERE `key` = 'cron_token' LIMIT 1"
            );

            $storedToken = $rows[0]['value'] ?? '';

            return $storedToken !== '' && hash_equals($storedToken, $token);
        } catch (\Throwable) {
            return false;
        }
    }
}
