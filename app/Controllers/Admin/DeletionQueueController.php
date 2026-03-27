<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\AuditLog;
use App\Engine\Auth;
use App\Engine\CustomerAnonymizer;
use App\Engine\Database;
use App\Engine\Flash;
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
            'pageTitle'         => 'Deletion Queue — VoxelBooking',
        ]);
    }

    /**
     * Confirm a deletion request — anonymize the customer.
     */
    public function confirm(Request $request): Response
    {
        $customerId = $request->string('customer_id');

        if (empty($customerId)) {
            Flash::set('error','Missing customer ID.');
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
            Flash::set('error','Customer not found or no pending deletion request.');
            return Response::redirect('/admin/deletion-queue');
        }

        $customer = $rows[0];

        try {
            // Perform the anonymization
            $result = CustomerAnonymizer::anonymize($customerId);

            if (!$result['anonymized']) {
                Flash::set('error','Anonymization failed: ' . ($result['reason'] ?? 'unknown'));
                return Response::redirect('/admin/deletion-queue');
            }

            // Audit log the operator decision
            AuditLog::log(
                'privacy.deletion_confirmed',
                'customer',
                $customerId,
                [
                    'email_hash'   => AuditLog::hashEmail($customer['email']),
                    'operator'     => Auth::user()['email'] ?? 'unknown',
                    'requested_at' => $customer['deletion_requested_at'],
                ],
                $customer['tenant_id'],
            );

            Flash::set('success','Customer data has been anonymized. Deletion request processed.');
        } catch (\Throwable $e) {
            Logger::error('Deletion confirmation failed', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
            Flash::set('error','Failed to process deletion: ' . $e->getMessage());
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
            Flash::set('error','Missing customer ID.');
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
            Flash::set('error','Customer not found or no pending deletion request.');
            return Response::redirect('/admin/deletion-queue');
        }

        $customer = $rows[0];

        try {
            // Clear the deletion request (keep the customer data)
            Database::execute(
                'UPDATE `customers` SET `deletion_requested_at` = NULL, `updated_at` = NOW() WHERE `id` = ?',
                [$customerId]
            );

            AuditLog::log(
                'privacy.deletion_dismissed',
                'customer',
                $customerId,
                [
                    'email_hash'   => AuditLog::hashEmail($customer['email']),
                    'operator'     => Auth::user()['email'] ?? 'unknown',
                    'reason'       => $reason ?: 'No reason provided',
                    'requested_at' => $customer['deletion_requested_at'],
                ],
                $customer['tenant_id'],
            );

            Flash::set('success','Deletion request dismissed. Customer data retained.');
        } catch (\Throwable $e) {
            Logger::error('Deletion dismissal failed', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
            Flash::set('error','Failed to dismiss deletion request.');
        }

        return Response::redirect('/admin/deletion-queue');
    }
}
