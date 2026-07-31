<?php

declare(strict_types=1);

namespace App\Perka\Modules\WhatsAppAutomation\Controllers\Api;

use App\Engine\Request;
use App\Engine\Response;
use App\Perka\Modules\WhatsAppAutomation\Services\WhatsAppAutomationService;

/**
 * Machine-to-machine WhatsApp availability endpoint.
 *
 * GET /api/whatsapp/slots?instance={instance}&date=YYYY-MM-DD[&service_id=]
 *
 * Called by an external n8n workflow (guarded by N8nApiKeyMiddleware, NOT
 * tenant-session auth). Resolves instance → active profile → active tenant,
 * then computes bookable slots via the existing read-only TimeSlotCalculator.
 * No BookingService, no write path, no new tables.
 *
 *   - Missing/invalid `date` (not YYYY-MM-DD)          → 400.
 *   - Unknown instance / inactive profile / inactive tenant → uniform 404
 *     (no existence leak — identical body for all three).
 *   - timeslot tenant → 200 with a list of bookable "HH:MM" start times.
 *   - active tenant on another pattern → 200 with "slots": null.
 */
final class WhatsAppAvailabilityController
{
    public function show(Request $request): Response
    {
        $instance  = $request->string('instance');
        $date      = $request->string('date');
        $serviceId = $request->string('service_id');

        // Validate the date shape before any lookup (mirrors the public
        // booking availability endpoint's 400 contract).
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return Response::json([
                'error'   => 'invalid_date',
                'message' => 'A valid date (YYYY-MM-DD) is required.',
            ], 400);
        }

        $data = (new WhatsAppAutomationService())
            ->getAvailabilityByInstance($instance, $date, $serviceId ?: null);

        // Uniform 404: unknown instance, inactive profile, and inactive tenant
        // are indistinguishable.
        if ($data === null) {
            return Response::json([
                'error'   => 'not_found',
                'message' => 'No active WhatsApp profile for that instance.',
            ], 404);
        }

        return Response::json($data, 200);
    }
}
