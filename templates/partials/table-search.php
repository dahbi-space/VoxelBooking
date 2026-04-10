<?php
/**
 * Shared table search input with inline clear button.
 *
 * CSP-safe: Uses the registered `tableSearch` Alpine component (app.js).
 * No inline JS expressions — all logic lives in Alpine.data('tableSearch').
 *
 * Variables:
 *   $searchAction  — form action URL (required)
 *   $searchValue   — current search string (default '')
 *   $searchPlaceholder — placeholder text (default 'Search…')
 *   $searchHiddenFields — array of hidden fields to preserve during search (default [])
 *
 * Alpine wiring:
 *   x-data="tableSearch"  — registered component (reads data-initial for initial state)
 *   x-ref="searchInput"   — input ref for clear() method
 *   @input="onInput"      — tracks whether input has value (shows/hides clear button)
 *   @click="clear"        — clears input and submits form
 *   x-show="hasValue"     — shows clear button only when input has value
 */
$searchValue = $searchValue ?? '';
$searchPlaceholder = $searchPlaceholder ?? 'Search…';
$searchHiddenFields = $searchHiddenFields ?? [];
?>
<form action="<?= htmlspecialchars($searchAction, ENT_QUOTES, 'UTF-8') ?>" method="GET"
      class="vb-table-search"
      x-data="tableSearch"
      data-initial="<?= htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8') ?>">
    <i data-lucide="search" class="vb-table-search-icon"></i>
    <?php foreach ($searchHiddenFields as $fieldName => $fieldValue): ?>
        <input type="hidden" name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>"
               value="<?= htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <input type="text"
           name="search"
           x-ref="searchInput"
           @input="onInput"
           value="<?= htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="<?= htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
           class="vb-table-search-input" />
    <button type="button" class="vb-table-search-clear"
            @click="clear"
            x-show="hasValue"
            tabindex="-1">
        <i data-lucide="x"></i>
    </button>
</form>
