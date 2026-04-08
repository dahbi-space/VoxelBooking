<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\AuditLog;
use App\Engine\Auth;
use App\Engine\CustomerAnonymizer;
use App\Engine\Database;
use App\Engine\FormState;
use App\Engine\Logger;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\View;

/**
 * Operator deletion queue — admin UI for reviewing GDPR deletion requests.
 *
 * Per .ai/23-VoxelBooking-Legal-Logging.md §7 (Right to Erasure):
 * - Customer requests deletion via the privacy endpoint
 * - Request is logged with deletion_requested_at timestamp
 * - Operator reviews pending requests in this queue
 * - Operator can confirm (anonymize) or dismiss each request
 * - All decisions are audit-logged
 */
final class DeletionQueueController
{
    /**
     * Display the deletion request queue.
     */
    public function index(Request $request): Response
    {
        $pendingRequests = Database::query(
            'SELECT c.*, t.`name` AS `tenant_name`, t.`slug` AS `tenant_slug`,
                    (SELECT COUNT(*) FROM `bookings` WHERE `customer_id` = c.`id`) AS `booking_count`
             FROM `customers` c
             JOIN `tenants` t ON t.`id` = c.`tenant_id`
             WHERE c.`deletion_requested_at` IS NOT NULL
               AND c.`is_anonymized` = 0
             ORDER BY c.`deletion_requested_at` ASC'
        );

        $processedRequests = Database::query(
            'SELECT c.*, t.`name` AS `tenant_name`, t.`slug` AS `tenant_slug`
             FROM `customers` c
             JOIN `tenants` t ON t.`id` = c.`tenant_id`
             WHERE c.`deletion_requested_at` IS NOT NULL
               AND c.`is_anonymized` = 1
             ORDER BY c.`anonymized_at` DESC
             LIMIT 25'
        );

        return View::response('admin.deletion-queue', [
            'pendingRequests'   => $pendingRequests,
            'processedRequests' => $processedRequests,
            'pageTitle'         => __('admin.deletion.page_title'),
            'flash'             => FormState::getToast(),
        ]);
    }

    /**
     * Confirm a deletion request — anonymize the customer.
     */
    public function confirm(Request $request): Response
    {
        $customerId = $request->string('customer_id');

        if (empty($customerId)) {
            FormState::toast('error', __('admin.deletion.missing_customer_id'));
            return Response::redirect('/admin/deletion-queue');
        }

        // Verify the customer exists and has a pending deletion request
        $rows = Database::query(
            'SELECT c.*, t.`id` AS `tid`
             FROM `customers` c
             JOIN `tenants` t ON t.`id` = c.`tenant_id`
             WHERE c.`id` = ?
               AND c.`deletion_requested_at` IS NOT NULL
               AND c.`is_anonymized` = 0
             LIMIT 1',
            [$customerId]
        );

        if (empty($rows)) {
            FormState::toast('error', __('admin.deletion.not_found'));
            return Response::redirect('/admin/deletion-queue');
        }

        $customer = $rows[0];

        try {
            // Perform the anonymization
            $result = CustomerAnonymizer::anonymize($customerId);

            if (!$result['anonymized']) {
                FormState::toast('error', str_replace(':reason', $result['reason'] ?? 'unknown', __('admin.deletion.anonymize_failed')));
                return Response::redirect('/admin/deletion-queue');
            }

            // Audit log the operator decision
            AuditLog::log(
                'privacy.deletion_confirmed',
                'customer',
                $customerId,
                [
                    'email_hash'   => AuditLog::hashEmail($customer['email']),
                    'operator_email' => Auth::user()['email'] ?? 'unknown',
                    'requested_at' => $customer['deletion_requested_at'],
                ],
                $customer['tenant_id'],
            );

            FormState::toast('success', __('admin.deletion.anonymized_success'));
        } catch (\Throwable $e) {
            Logger::error('Deletion confirmation failed', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
            FormState::toast('error', str_replace(':error', $e->getMessage(), __('admin.deletion.confirm_failed')));
        }

        return Response::redirect('/admin/deletion-queue');
    }

    /**
     * Dismiss a deletion request — clear the request without anonymizing.
     */
    public function dismiss(Request $request): Response
    {
        $customerId = $request->string('customer_id');
        $reason = $request->string('reason');

        if (empty($customerId)) {
            FormState::toast('error', __('admin.deletion.missing_customer_id'));
            return Response::redirect('/admin/deletion-queue');
        }

        // Verify the customer exists and has a pending deletion request
        $rows = Database::query(
            'SELECT c.*, t.`id` AS `tid`
             FROM `customers` c
             JOIN `tenants` t ON t.`id` = c.`tenant_id`
             WHERE c.`id` = ?
               AND c.`deletion_requested_at` IS NOT NULL
               AND c.`is_anonymized` = 0
             LIMIT 1',
            [$customerId]
        );

        if (empty($rows)) {
            FormState::toast('error', __('admin.deletion.not_found'));
            return Response::redirect('/admin/deletion-queue');
        }

        $customer = $rows[0];

        try {
            // Clear the deletion request (keep the customer data)
            // WHERE guards: only clear if request is still pending and customer is not anonymized
            $affectedRows = Database::execute(
                'UPDATE `customers` SET `deletion_requested_at` = NULL, `updated_at` = NOW()
                 WHERE `id` = ? AND `deletion_requested_at` IS NOT NULL AND `is_anonymized` = 0',
                [$customerId]
            );

            // Only audit-log if a row was actually changed
            if ($affectedRows > 0) {
                AuditLog::log(
                    'privacy.deletion_dismissed',
                    'customer',
                    $customerId,
                    [
                        'email_hash'   => AuditLog::hashEmail($customer['email']),
                        'operator_email' => Auth::user()['email'] ?? 'unknown',
                        'reason'       => $reason ?: __('admin.deletion.no_reason'),
                        'requested_at' => $customer['deletion_requested_at'],
                    ],
                    $customer['tenant_id'],
                );

                FormState::toast('success', __('admin.deletion.dismissed_success'));
            } else {
                FormState::toast('error', __('admin.deletion.dismiss_race'));
            }
        } catch (\Throwable $e) {
            Logger::error('Deletion dismissal failed', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
            FormState::toast('error', __('admin.deletion.dismiss_failed'));
        }

        return Response::redirect('/admin/deletion-queue');
    }
}
