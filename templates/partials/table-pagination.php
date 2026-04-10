<?php
/**
 * Shared table pagination controls.
 *
 * Renders prev/info/next navigation when total pages > 1.
 * Self-gating: renders nothing when $paginationTotalPages <= 1.
 *
 * Variables:
 *   $paginationPage       — current page (1-indexed, required)
 *   $paginationTotalPages — total number of pages (required)
 *   $paginationBaseUrl    — base URL path (required, e.g. '/admin/tenants')
 *   $paginationParams     — query string to append (default '', should include leading '&')
 *   $paginationI18nPrefix — i18n key prefix (default 'admin.common', expects .page_of, .previous, .next)
 */
$paginationPage = (int) ($paginationPage ?? 1);
$paginationTotalPages = (int) ($paginationTotalPages ?? 1);
$paginationParams = $paginationParams ?? '';
$paginationI18nPrefix = $paginationI18nPrefix ?? 'admin.common';

if ($paginationTotalPages <= 1) {
    return;
}
?>
<div class="vb-pagination">
    <?php if ($paginationPage > 1): ?>
        <a href="<?= htmlspecialchars($paginationBaseUrl, ENT_QUOTES, 'UTF-8') ?>?page=<?= $paginationPage - 1 ?><?= $paginationParams ?>"
           class="vb-btn vb-btn-ghost vb-btn-sm">
            <i data-lucide="chevron-left"></i>
            <?= __($paginationI18nPrefix . '.previous') ?>
        </a>
    <?php else: ?>
        <span></span>
    <?php endif; ?>
    <span class="vb-pagination-info">
        <?= str_replace(
            [':page', ':total'],
            [(string) $paginationPage, (string) $paginationTotalPages],
            __($paginationI18nPrefix . '.page_of') ?? __('admin.common.page_of')
        ) ?>
    </span>
    <?php if ($paginationPage < $paginationTotalPages): ?>
        <a href="<?= htmlspecialchars($paginationBaseUrl, ENT_QUOTES, 'UTF-8') ?>?page=<?= $paginationPage + 1 ?><?= $paginationParams ?>"
           class="vb-btn vb-btn-ghost vb-btn-sm">
            <?= __($paginationI18nPrefix . '.next') ?>
            <i data-lucide="chevron-right"></i>
        </a>
    <?php else: ?>
        <span></span>
    <?php endif; ?>
</div>
