<?php

declare(strict_types=1);

/**
 * Perka admin sidebar items — injected through the frozen Phase 0 seam near the
 * end of <nav> in templates/admin/layout.php, so it runs in the layout's scope.
 *
 * Two independent items:
 *   1. "Business Profile" — tenant context, operator/owner only (managers get
 *      403, so no dead link). Reuses the layout's already-computed sidebar
 *      locals; no query.
 *   2. "N migrations pending — Run" — operator-wide, shown only when there are
 *      pending Perka migrations. The pendingCount() query is gated behind the
 *      operator check so business users never trigger it, and is wrapped in
 *      try/catch so the migrator can never break the admin chrome.
 *
 * Sidebar locals may be unset depending on context, so every one is
 * null-coalesced (not merely truthiness-tested) and this file renders without
 * notices in tenant and non-tenant contexts alike. If app/Perka is deleted this
 * file vanishes and the seam's file_exists guard skips it.
 *
 * @var string|null $sidebarTenantId
 * @var bool        $inTenantContext
 * @var bool        $canManage
 * @var string      $activePage
 */

$sidebarTenantId = $sidebarTenantId ?? null;
$inTenantContext = $inTenantContext ?? false;
$canManage       = $canManage ?? false;
$activePage      = $activePage ?? '';

// ── 1. Business Profile (tenant context, operator/owner) ──
if ($inTenantContext && $canManage && $sidebarTenantId !== null && $sidebarTenantId !== ''):
?>
<div class="vb-sidebar-section">
    <div class="vb-sidebar-section-label">Perka</div>
    <a href="/admin/tenants/<?= htmlspecialchars($sidebarTenantId, ENT_QUOTES, 'UTF-8') ?>/profile"
       class="vb-sidebar-link <?= $activePage === 'perka-profile' ? 'active' : '' ?>">
        <i data-lucide="globe"></i>
        Business Profile
    </a>
</div>
<?php
endif;

// ── 2. Pending-migrations indicator (operator-wide) ──
if (\App\Engine\Auth::isOperator()):
    $perkaPending = 0;
    try {
        $perkaPending = (new \App\Perka\Shared\PerkaMigrator(__DIR__ . '/Modules'))->pendingCount();
    } catch (\Throwable) {
        $perkaPending = 0; // never let the migrator break admin chrome
    }

    if ($perkaPending > 0):
?>
<div class="vb-sidebar-section">
    <a href="/admin/perka/updates"
       class="vb-sidebar-link <?= $activePage === 'perka-updates' ? 'active' : '' ?>">
        <i data-lucide="alert-triangle"></i>
        <?= (int) $perkaPending ?> migration<?= $perkaPending === 1 ? '' : 's' ?> pending — Run
    </a>
</div>
<?php
    endif;
endif;
