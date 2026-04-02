<?php
/**
 * Reusable alert/flash component.
 *
 * Usage:
 *   <?php include __DIR__ . '/../partials/alert.php'; ?>
 *
 * Expects $flash to be set in the including scope as:
 *   ['type' => 'success'|'error'|'info'|'warning', 'message' => '...']
 *
 * Or pass $alertType and $alertMessage directly for static alerts:
 *   <?php $alertType = 'info'; $alertMessage = 'Hello'; include ...; ?>
 *
 * Features:
 *   - Semantic icon per type (check, alert-circle, info, alert-triangle)
 *   - Dismissible close button
 *   - Bottom margin built into CSS (.vb-alert)
 *   - Fade-in animation
 */

// Resolve from $flash or direct variables
$_alertType = $alertType ?? ($flash['type'] ?? null);
$_alertMessage = $alertMessage ?? ($flash['message'] ?? null);

if (!$_alertType || !$_alertMessage) {
    return;
}

// Map type to CSS class
$_alertCssMap = [
    'success' => 'success',
    'error'   => 'error',
    'info'    => 'info',
    'warning' => 'warning',
];
$_alertCss = $_alertCssMap[$_alertType] ?? 'info';

// Map type to Lucide icon name
$_alertIconMap = [
    'success' => 'check-circle',
    'error'   => 'alert-circle',
    'info'    => 'info',
    'warning' => 'alert-triangle',
];
$_alertIcon = $_alertIconMap[$_alertType] ?? 'info';
?>
<div class="vb-alert vb-alert-<?= $_alertCss ?>" role="alert">
    <i data-lucide="<?= $_alertIcon ?>" class="vb-alert-icon"></i>
    <span class="vb-alert-text"><?= htmlspecialchars($_alertMessage, ENT_QUOTES, 'UTF-8') ?></span>
    <button type="button" class="vb-alert-close" onclick="this.parentElement.remove()" aria-label="<?= __('admin.common.dismiss') ?>">
        <i data-lucide="x"></i>
    </button>
</div>
