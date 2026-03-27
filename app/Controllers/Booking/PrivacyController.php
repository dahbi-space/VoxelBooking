<?php

declare(strict_types=1);

namespace App\Controllers\Booking;

use App\Engine\AuditLog;
use App\Engine\CustomerAnonymizer;
use App\Engine\Database;
use App\Engine\DataExporter;
use App\Engine\Logger;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;

/**
 * Public privacy controller for data-subject rights.
 *
 * Per PRD §XIX and .ai/23-VoxelBooking-Legal-Logging.md §7.
 *
 * GET  /book/{slug}/privacy/{customer-id}    → View customer data
 * POST /book/{slug}/privacy/{customer-id}    → Request deletion or export
 *
 * No authentication required — the customer ID (ULID) acts as a
 * bearer token. The ULID's 128-bit entropy makes brute-force impractical.
 * The URL should only be shared via email to the customer.
 *
 * Action flow:
 * - View: shows what data is held (name, email, booking history, consent)
 * - Export: generates JSON download (GDPR Art. 20)
 * - Delete: logs a deletion request for operator review in admin queue
 */
final class PrivacyController
{
    /**
     * Display customer data for review (Right of Access, GDPR Art. 15).
     */
    public function show(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $customerId = $request->getAttribute('customer_id');

        $tenant = $this->loadTenant($slug);
        if ($tenant === null) {
            return Response::html('Page not found', 404);
        }

        $customer = $this->loadCustomer($customerId, $tenant['id']);
        if ($customer === null) {
            return Response::html('Page not found', 404);
        }

        if ((int) ($customer['is_anonymized'] ?? 0) === 1) {
            return View::response('booking.privacy-anonymized', [
                'tenant'   => $tenant,
                'pageTitle' => 'Data Removed — ' . $tenant['name'],
            ]);
        }

        $bookings = $this->loadBookings($customerId);
        $consentRecords = $this->extractConsentRecords($bookings);

        // Start public session and generate CSRF token for POST forms
        $csrfToken = CsrfMiddleware::generateToken();

        // Audit the access
        AuditLog::log(
            'privacy.data_viewed',
            'customer',
            $customerId,
            ['email_prefix' => AuditLog::hashEmail($customer['email'])],
            $tenant['id'],
            actorType: 'customer',
            actorId: $customerId,
        );

        return View::response('booking.privacy', [
            'tenant'         => $tenant,
            'customer'       => $customer,
            'bookings'       => $bookings,
            'consentRecords' => $consentRecords,
            'csrfToken'      => $csrfToken,
            'pageTitle'      => 'Your Data — ' . $tenant['name'],
        ]);
    }

    /**
     * Handle privacy actions (export or deletion request).
     */
    public function action(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $customerId = $request->getAttribute('customer_id');
        $actionType = $request->string('action');

        $tenant = $this->loadTenant($slug);
        if ($tenant === null) {
            return Response::html('Page not found', 404);
        }

        $customer = $this->loadCustomer($customerId, $tenant['id']);
        if ($customer === null) {
            return Response::html('Page not found', 404);
        }

        if ((int) ($customer['is_anonymized'] ?? 0) === 1) {
            return Response::redirect("/book/{$slug}/privacy/{$customerId}");
        }

        if ($actionType === 'export') {
            return $this->handleExport($customer, $tenant);
        }

        if ($actionType === 'delete') {
            return $this->handleDeletionRequest($customer, $tenant, $slug);
        }

        return Response::redirect("/book/{$slug}/privacy/{$customerId}");
    }

    /**
     * Generate and return a JSON export (Right to Data Portability, GDPR Art. 20).
     */
    private function handleExport(array $customer, array $tenant): Response
    {
        try {
            $json = DataExporter::exportJson($customer['id']);
        } catch (\Throwable $e) {
            Logger::error('Data export failed', [
                'customer_id' => $customer['id'],
                'error'       => $e->getMessage(),
            ]);
            return Response::html('Export failed. Please try again later.', 500);
        }

        AuditLog::log(
            'privacy.data_exported',
            'customer',
            $customer['id'],
            ['email_prefix' => AuditLog::hashEmail($customer['email'])],
            $tenant['id'],
            actorType: 'customer',
            actorId: $customer['id'],
        );

        $filename = 'voxelbooking-data-' . date('Y-m-d') . '.json';

        $response = Response::json(json_decode($json, true));
        $response->header('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }

    /**
     * Log a deletion request (Right to Erasure, GDPR Art. 17).
     *
     * Sets the deletion_requested_at timestamp on the customer record.
     * The operator reviews pending requests in the admin deletion queue
     * and confirms or rejects each request.
     */
    private function handleDeletionRequest(array $customer, array $tenant, string $slug): Response
    {
        try {
            // Mark customer as pending deletion with proper state column
            Database::execute(
                'UPDATE `customers` SET
                    `deletion_requested_at` = NOW(),
                    `updated_at` = NOW()
                WHERE `id` = ? AND `deletion_requested_at` IS NULL',
                [$customer['id']]
            );

            AuditLog::log(
                'privacy.deletion_requested',
                'customer',
                $customer['id'],
                ['email_prefix' => AuditLog::hashEmail($customer['email'])],
                $tenant['id'],
                actorType: 'customer',
                actorId: $customer['id'],
            );
        } catch (\Throwable $e) {
            Logger::error('Deletion request failed', [
                'customer_id' => $customer['id'],
                'error'       => $e->getMessage(),
            ]);
        }

        return View::response('booking.privacy-deletion-requested', [
            'tenant'    => $tenant,
            'customer'  => $customer,
            'pageTitle' => 'Deletion Requested — ' . $tenant['name'],
        ]);
    }

    // ── Helpers ──

    /**
     * @return array<string, mixed>|null
     */
    private function loadTenant(string $slug): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `tenants` WHERE `slug` = ? AND `status` = \'active\' LIMIT 1',
            [$slug]
        );

        return $rows[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadCustomer(string $customerId, string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `customers` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1',
            [$customerId, $tenantId]
        );

        return $rows[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadBookings(string $customerId): array
    {
        return Database::query(
            'SELECT * FROM `bookings` WHERE `customer_id` = ? ORDER BY `start_datetime` DESC',
            [$customerId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractConsentRecords(array $bookings): array
    {
        $records = [];

        foreach ($bookings as $b) {
            if ($b['consent_given_at'] !== null) {
                $records[] = [
                    'booking_date'       => $b['start_datetime'],
                    'consent_given_at'   => $b['consent_given_at'],
                    'consent_text_shown' => $b['consent_text_shown'],
                ];
            }
        }

        return $records;
    }
}
