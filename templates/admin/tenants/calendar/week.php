<?php
/**
 * Calendar week view — premium 7-column schedule grid.
 *
 * State precedence: Bookings ALWAYS render, regardless of availability state.
 * Availability/blocked state is a visual cue (dimmed, subtle label),
 * never a gate that hides real appointments.
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

<!-- Calendar header -->
<div class="vb-calendar-header">
    <div class="vb-calendar-header-left">
        <a href="<?= $baseUrl ?>/calendar/week?date=<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"
           class="vb-btn vb-btn-ghost vb-btn-sm">
            <?= __('admin.calendar.this_week') ?>
        </a>
        <div class="vb-calendar-nav-arrows">
            <a href="<?= $baseUrl ?>/calendar/week?date=<?= htmlspecialchars($prevWeek, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-btn vb-btn-ghost vb-btn-icon" aria-label="<?= __('admin.calendar.prev_week') ?>">
                <i data-lucide="chevron-left"></i>
            </a>
            <a href="<?= $baseUrl ?>/calendar/week?date=<?= htmlspecialchars($nextWeek, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-btn vb-btn-ghost vb-btn-icon" aria-label="<?= __('admin.calendar.next_week') ?>">
                <i data-lucide="chevron-right"></i>
            </a>
        </div>
        <h2 class="vb-calendar-date-title">
            <?php if (!empty($days)): ?>
                <?= htmlspecialchars(\App\Engine\Locale::dateLong($days[0]['date']), ENT_QUOTES, 'UTF-8') ?>
                – <?= htmlspecialchars(\App\Engine\Locale::dateLong($days[6]['date']), ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </h2>
    </div>
    <div class="vb-calendar-header-right">
        <nav class="vb-tabs vb-tabs-compact" aria-label="<?= __('admin.calendar.page_title') ?>">
            <a href="<?= $baseUrl ?>/calendar?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-tab"><?= __('admin.calendar.view_month') ?></a>
            <a href="<?= $baseUrl ?>/calendar/week?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-tab active"><?= __('admin.calendar.view_week') ?></a>
            <a href="<?= $baseUrl ?>/calendar/day?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-tab"><?= __('admin.calendar.view_day') ?></a>
        </nav>
    </div>
</div>

<!-- Week schedule -->
<div class="vb-week">
    <!-- Column headers -->
    <div class="vb-week-headers">
        <?php foreach ($days as $day):
            $isBlocked = $day['isBlocked'] ?? false;
            $hasAvail  = $day['hasAvailability'] ?? true;
        ?>
        <a href="<?= $baseUrl ?>/calendar/day?date=<?= htmlspecialchars($day['dateStr'], ENT_QUOTES, 'UTF-8') ?>"
           class="vb-week-col-header <?= $day['isToday'] ? 'is-today' : '' ?> <?= $isBlocked ? 'is-blocked' : '' ?> <?= (!$hasAvail && !$isBlocked) ? 'is-unavailable' : '' ?>">
            <span class="vb-week-col-dayname">
                <?= htmlspecialchars(\App\Engine\Locale::dayName((int) $day['date']->format('w')), ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="vb-week-col-number <?= $day['isToday'] ? 'is-today' : '' ?>">
                <?= (int) $day['date']->format('j') ?>
            </span>
            <?php if (count($day['bookings']) > 0): ?>
                <span class="vb-week-col-badge"><?= count($day['bookings']) ?></span>
            <?php elseif ($isBlocked): ?>
                <span class="vb-week-col-state is-blocked"><?= __('admin.calendar.blocked') ?></span>
            <?php elseif (!$hasAvail): ?>
                <span class="vb-week-col-state is-unavailable"><?= __('admin.calendar.closed') ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Day columns with booking slots -->
    <div class="vb-week-body">
        <?php foreach ($days as $i => $day):
            $isBlocked = $day['isBlocked'] ?? false;
            $hasAvail  = $day['hasAvailability'] ?? true;
            $dayBookings = $day['bookings'];
            $hasBookings = !empty($dayBookings);

            $colClasses = ['vb-week-col', 'vb-fade-in-up', 'stagger-' . min($i + 1, 6)];
            if ($day['isToday']) $colClasses[] = 'is-today';
            if ($isBlocked) $colClasses[] = 'is-blocked';
            if (!$hasAvail && !$isBlocked) $colClasses[] = 'is-unavailable';
        ?>
        <div class="<?= implode(' ', $colClasses) ?>">
            <?php if ($hasBookings):
                // Bookings ALWAYS render, regardless of availability state
                foreach ($dayBookings as $b):
                    $start = new DateTimeImmutable($b['start_datetime']);
                    $end   = new DateTimeImmutable($b['end_datetime']);
                    $color = $b['service_color'] ?: $brandColor;
                    $customerName = $b['customer_name'] ?? '—';
                    $blockLabel = booking_display_label($b);
                    $tooltipText = htmlspecialchars($customerName . ' · ' . \App\Engine\Locale::time($start) . '–' . \App\Engine\Locale::time($end) . ($blockLabel !== '—' ? ' · ' . $blockLabel : ''), ENT_QUOTES, 'UTF-8');
            ?>
            <a href="<?= $baseUrl ?>/calendar/day?date=<?= htmlspecialchars($day['dateStr'], ENT_QUOTES, 'UTF-8') ?>"
               class="vb-week-booking"
               style="--pill-color: <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>;"
               title="<?= $tooltipText ?>">
                <span class="vb-week-booking-time">
                    <?= htmlspecialchars(\App\Engine\Locale::time($start), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="vb-week-booking-name">
                    <?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </a>
            <?php endforeach;
            else: ?>
            <div class="vb-week-col-empty">
                <?php if ($isBlocked): ?>
                    <span><?= __('admin.calendar.day_blocked') ?></span>
                <?php elseif (!$hasAvail): ?>
                    <span><?= __('admin.calendar.closed') ?></span>
                <?php else: ?>
                    <span><?= __('admin.calendar.no_bookings') ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
