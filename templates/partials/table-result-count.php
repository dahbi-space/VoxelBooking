<?php
/**
 * Shared table result count row (filter-scoped).
 *
 * Only renders when $resultCountActive is true (i.e. a search or filter
 * scope is active). When no filters are active, the page subtitle already
 * shows the total count — rendering this row would be redundant.
 *
 * Variables:
 *   $resultCountKey    — i18n key with plural support (required, e.g. 'admin.tenants.showing_count')
 *   $resultCountValue  — integer count (required)
 *   $resultCountActive — boolean, true if filters are active (required)
 */
$resultCountActive = $resultCountActive ?? false;
if (!$resultCountActive) {
    return;
}
?>
<div class="vb-table-result-count">
    <?= __p($resultCountKey, (int) $resultCountValue) ?>
    <span class="vb-table-active-filter-dot"></span>
</div>
