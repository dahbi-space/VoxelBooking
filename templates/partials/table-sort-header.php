<?php
/**
 * Shared sortable table header helper.
 *
 * Generates a clickable <a> inside a <th> with sort direction indicator.
 * Include once, then call tableSortHeader() in thead cells.
 *
 * CSS classes:
 *   .vb-th-sort          — base sortable header link
 *   .vb-th-sort.is-asc   — ascending sort active
 *   .vb-th-sort.is-desc  — descending sort active
 *
 * Usage:
 *   <?= tableSortHeader('name', __('Name'), $sort, $direction, $baseUrl, $filters) ?>
 */
if (!function_exists('tableSortHeader')) {
    function tableSortHeader(
        string $column,
        string $label,
        string $currentSort,
        string $currentDir,
        string $baseUrl,
        array  $filters
    ): string {
        $isActive = $currentSort === $column;
        $nextDir  = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';
        $class    = 'vb-th-sort' . ($isActive ? ($currentDir === 'asc' ? ' is-asc' : ' is-desc') : '');
        $params   = array_merge($filters, ['sort' => $column, 'direction' => $nextDir, 'page' => 1]);
        $qs       = http_build_query(array_filter($params, fn($v) => $v !== null && $v !== ''));
        $href     = htmlspecialchars($baseUrl . ($qs ? '?' . $qs : ''), ENT_QUOTES, 'UTF-8');
        return "<a href=\"{$href}\" class=\"{$class}\">{$label}</a>";
    }
}
