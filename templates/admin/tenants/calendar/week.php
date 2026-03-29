<?php
/**
 * Calendar week view — 7-column grid with booking pills.
 *
 * Variables: $tenant, $date, $dateStr, $weekBegin, $prevWeek, $nextWeek,
 *            $today, $days, $tenantId, $csrfToken
 */
$tenant    = $tenant ?? [];
$days      = $days ?? [];
$tenantId  = $tenantId ?? '';
$dateStr   = $dateStr ?? date('Y-m-d');
$prevWeek  = $prevWeek ?? '';
$nextWeek  = $nextWeek ?? '';
$today     = $today ?? date('Y-m-d');
$brandColor = $tenant['brand_color'] ?? '#6366f1';

$baseUrl = "/admin/tenants/" . htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8');

ob_start();
?>

<!-- Calendar navigation -->
<div class="vb-calendar-nav">
    <div class="vb-calendar-nav-left">
        <a href="<?= $baseUrl ?>/calendar/week?date=<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"
           class="vb-btn vb-btn-ghost vb-btn-sm">
            <?= __('admin.calendar.this_week') ?>
        </a>
        <div class="vb-calendar-nav-arrows">
            <a href="<?= $baseUrl ?>/calendar/week?date=<?= htmlspecialchars($prevWeek, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-btn vb-btn-ghost vb-btn-icon" aria-label="<?= __('admin.calendar.prev_week') ?>">
                <i data-lucide="chevron-left" style="width: 16px; height: 16px;"></i>
            </a>
            <a href="<?= $baseUrl ?>/calendar/week?date=<?= htmlspecialchars($nextWeek, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-btn vb-btn-ghost vb-btn-icon" aria-label="<?= __('admin.calendar.next_week') ?>">
                <i data-lucide="chevron-right" style="width: 16px; height: 16px;"></i>
            </a>
        </div>
        <h2 class="vb-calendar-date-title">
            <?php if (!empty($days)): ?>
                <?= htmlspecialchars(\App\Engine\Locale::dateLong($days[0]['date']), ENT_QUOTES, 'UTF-8') ?>
                – <?= htmlspecialchars(\App\Engine\Locale::dateLong($days[6]['date']), ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </h2>
    </div>
    <div class="vb-calendar-nav-right">
        <div class="vb-segmented">
            <a href="<?= $baseUrl ?>/calendar?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-segmented-btn"><?= __('admin.calendar.view_day') ?></a>
            <a href="<?= $baseUrl ?>/calendar/week?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-segmented-btn is-active"><?= __('admin.calendar.view_week') ?></a>
        </div>
    </div>
</div>

<!-- Week grid -->
<div class="vb-calendar-week-grid">
    <?php foreach ($days as $i => $day): ?>
    <div class="vb-calendar-week-day <?= $day['isToday'] ? 'is-today' : '' ?> vb-fade-in-up stagger-<?= min($i + 1, 7) ?>">
        <a href="<?= $baseUrl ?>/calendar?date=<?= htmlspecialchars($day['dateStr'], ENT_QUOTES, 'UTF-8') ?>"
           class="vb-calendar-week-day-header">
            <span class="vb-calendar-week-day-name">
                <?= htmlspecialchars(\App\Engine\Locale::dayName((int) $day['date']->format('w')), ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="vb-calendar-week-day-number <?= $day['isToday'] ? 'is-today' : '' ?>">
                <?= (int) $day['date']->format('j') ?>
            </span>
            <?php if (count($day['bookings']) > 0): ?>
                <span class="vb-badge vb-badge-neutral vb-calendar-week-day-count">
                    <?= count($day['bookings']) ?>
                </span>
            <?php endif; ?>
        </a>
        <div class="vb-calendar-week-day-body">
            <?php if (empty($day['bookings'])): ?>
                <div class="vb-calendar-week-empty">
                    <span class="vb-text-tertiary"><?= __('admin.calendar.no_bookings') ?></span>
                </div>
            <?php else: ?>
                <?php foreach ($day['bookings'] as $b):
                    $start = new DateTimeImmutable($b['start_datetime']);
                    $end   = new DateTimeImmutable($b['end_datetime']);
                    $color = $b['service_color'] ?: $brandColor;
                ?>
                <a href="<?= $baseUrl ?>/calendar?date=<?= htmlspecialchars($day['dateStr'], ENT_QUOTES, 'UTF-8') ?>"
                   class="vb-calendar-week-pill" style="--pill-color: <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>;">
                    <span class="vb-calendar-week-pill-time">
                        <?= htmlspecialchars(\App\Engine\Locale::time($start), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="vb-calendar-week-pill-name">
                        <?= htmlspecialchars($b['customer_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
