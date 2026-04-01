<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Engine\Database;
use App\Engine\Logger;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\RetentionJob;

/**
 * Cron endpoint controller.
 *
 * All endpoints require the cron_token query parameter.
 * Not listed in any navigation or sitemap.
 *
 * GET /cron/retention?token={cron_token}
 */
final class CronController
{
    /**
     * Run retention processing.
     *
     * Authenticates via cron_token, then delegates to RetentionJob.
     */
    public function retention(Request $request): Response
    {
        $token = $request->query('token');

        if ($token === '' || !$this->validateCronToken($token)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        try {
            $result = RetentionJob::run();

            // Update last run timestamp
            try {
                Database::upsertSetting('cron_last_run', date('Y-m-d H:i:s'));
            } catch (\Throwable) {
                // Non-critical
            }

            $statusCode = empty($result['errors']) ? 200 : 207; // 207 Multi-Status if partial failure

            return Response::json([
                'status'  => empty($result['errors']) ? 'ok' : 'partial',
                'results' => $result,
            ], $statusCode);
        } catch (\Throwable $e) {
            Logger::error('Retention cron failed', ['error' => $e->getMessage()]);

            return Response::json([
                'status' => 'error',
                'error'  => 'Retention processing failed',
            ], 500);
        }
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
