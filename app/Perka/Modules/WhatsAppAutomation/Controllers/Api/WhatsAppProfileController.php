<?php

declare(strict_types=1);

namespace App\Perka\Modules\WhatsAppAutomation\Controllers\Api;

use App\Engine\Request;
use App\Engine\Response;
use App\Perka\Modules\WhatsAppAutomation\Services\WhatsAppAutomationService;

/**
 * Machine-to-machine WhatsApp profile endpoint.
 *
 * GET /api/whatsapp/profile?instance={instance}
 *
 * Called by an external n8n workflow (guarded by N8nApiKeyMiddleware, NOT
 * tenant-session auth). Looks up the profile by its Evolution API instance
 * name, joined with tenants.name.
 *
 *   - Unknown instance OR inactive profile → uniform 404 (no existence leak).
 *   - Found and active → 200 with:
 *       { "business_name": "...", "business_knowledge": "..."|null, "active": true }
 */
final class WhatsAppProfileController
{
    public function show(Request $request): Response
    {
        $instance = $request->string('instance');

        $data = (new WhatsAppAutomationService())->getByInstance($instance);

        // Uniform 404: unknown instance and inactive profile are indistinguishable.
        if ($data === null) {
            return Response::json([
                'error'   => 'not_found',
                'message' => 'No active WhatsApp profile for that instance.',
            ], 404);
        }

        return Response::json([
            'business_name'      => $data['business_name'],
            'business_knowledge' => $data['business_knowledge'],
            'active'             => $data['active'],
        ], 200);
    }
}
