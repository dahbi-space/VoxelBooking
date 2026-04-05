<?php
/**
 * Calendar month view — premium 7-column grid with booking pills.
 *
 * State precedence: Bookings ALWAYS render. Availability state is a
 * secondary visual cue, never a gate that hides appointments.
 *
 * Variables: $tenant, $date, $dateStr, $year, $month, $cells,
 *            $prevMonth, $nextMonth, $today, $tenantId, $csrfToken
 */
$tenant     = $tenant ?? [];
$cells      = $cells ?? [];
$tenantId   = $tenantId ?? '';
$dateStr    = $dateStr ?? date('Y-m-d');
$prevMonth  = $prevMonth ?? '';
$nextMonth  = $nextMonth ?? '';
$today      = $today ?? date('Y-m-d');
$year       = $year ?? (int) date('Y');
$month      = $month ?? (int) date('n');
$brandColor = $tenant['brand_color'] ?? '#6366f1';

$baseUrl = "/admin/tenants/" . htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8');
$monthLabel = \App\Engine\Locale::monthName($month) . ' ' . $year;

// Day names (starting from locale week start)
$weekStart = \App\Engine\Locale::weekStart();
$dayNames = [];
for ($i = 0; $i < 7; $i++) {
    $dayNames[] = \App\Engine\Locale::dayName(($weekStart + $i) % 7);
}

$maxPillsPerCell = 3;

ob_start();
?>

<!-- Calendar header -->
<div class="vb-calendar-header">
    <div class="vb-calendar-header-left">
        <a href="<?= $baseUrl ?>/calendar?date=<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"
           class="vb-btn vb-btn-ghost vb-btn-sm">
            <?= __('admin.calendar.today') ?>
        </a>
        <div class="vb-calendar-nav-arrows">
            <a href="<?= $baseUrl ?>/calendar?date=<?= htmlspecialchars($prevMonth, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-btn vb-btn-ghost vb-btn-icon" aria-label="<?= __('admin.calendar.prev_month') ?>">
                <i data-lucide="chevron-left"></i>
            </a>
            <a href="<?= $baseUrl ?>/calendar?date=<?= htmlspecialchars($nextMonth, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-btn vb-btn-ghost vb-btn-icon" aria-label="<?= __('admin.calendar.next_month') ?>">
                <i data-lucide="chevron-right"></i>
            </a>
        </div>
        <h2 class="vb-calendar-date-title"><?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?></h2>
    </div>
    <div class="vb-calendar-header-right">
        <nav class="vb-segmented" aria-label="<?= __('admin.calendar.page_title') ?>">
            <a href="<?= $baseUrl ?>/calendar?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-segmented-item active"><?= __('admin.calendar.view_month') ?></a>
            <a href="<?= $baseUrl ?>/calendar/week?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-segmented-item"><?= __('admin.calendar.view_week') ?></a>
            <a href="<?= $baseUrl ?>/calendar/day?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-segmented-item"><?= __('admin.calendar.view_day') ?></a>
        </nav>
    </div>
</div>

<!-- Month grid -->
<div class="vb-calendar-month">
    <!-- Day name headers -->
    <div class="vb-calendar-month-header">
        <?php foreach ($dayNames as $dn): ?>
        <div class="vb-calendar-month-dayname"><?= htmlspecialchars($dn, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
    </div>

    <!-- 6-row grid -->
    <div class="vb-calendar-month-grid">
        <?php foreach ($cells as $i => $cell):
            $isBlocked = $cell['isBlocked'] ?? false;
            $hasAvail  = $cell['hasAvailability'] ?? true;
            $cellBookings = $cell['bookings'];
            $bookingCount = count($cellBookings);
            $hasBookings = $bookingCount > 0;

            // State classes — availability is visual-only, never hides bookings
            $cellClasses = ['vb-calendar-month-cell', 'vb-fade-in-up'];
            // Stagger by row (week)
            $cellClasses[] = 'stagger-' . min((int)floor($i / 7) + 1, 6);
            if ($cell['isToday']) $cellClasses[] = 'is-today';
            if (!$cell['isCurrentMonth']) $cellClasses[] = 'is-outside';
            if ($isBlocked) $cellClasses[] = 'is-blocked';
            if (!$hasAvail && !$isBlocked && !$hasBookings) $cellClasses[] = 'is-unavailable';
        ?>
        <a href="<?= $baseUrl ?>/calendar/day?date=<?= htmlspecialchars($cell['dateStr'], ENT_QUOTES, 'UTF-8') ?>"
           class="<?= implode(' ', $cellClasses) ?>">
            <div class="vb-calendar-month-cell-header">
                <?php
                // Badge precedence: booking count always wins over state labels
                if ($hasBookings && $cell['isCurrentMonth']): ?>
                <span class="vb-calendar-month-count"><?= $bookingCount ?></span>
                <?php elseif ($isBlocked): ?>
                <span class="vb-calendar-month-count is-blocked"><?= __('admin.calendar.blocked') ?></span>
                <?php elseif (!$hasAvail && !$isBlocked): ?>
                <span class="vb-calendar-month-count is-unavailable"><?= __('admin.calendar.closed') ?></span>
                <?php else: ?>
                <span></span> <!-- Spacer to push day to right -->
                <?php endif; ?>
                <span class="vb-calendar-month-day <?= $cell['isToday'] ? 'is-today' : '' ?>"><?= $cell['day'] ?></span>
            </div>
            <?php
            // Bookings ALWAYS render, regardless of availability state
            if ($cell['isCurrentMonth'] && $hasBookings):
                $visibleBookings = array_slice($cellBookings, 0, $maxPillsPerCell);
                $overflow = $bookingCount - $maxPillsPerCell;
            ?>
            <div class="vb-calendar-month-pills">
                <?php foreach ($visibleBookings as $b):
                    $color = $b['service_color'] ?: $brandColor;
                    $startDt = new DateTimeImmutable($b['start_datetime']);
                    $time = \App\Engine\Locale::time($startDt);
                    $name = $b['customer_name'] ?? __('admin.calendar.no_customer');
                    $tooltipText = htmlspecialchars($name . ' · ' . $time, ENT_QUOTES, 'UTF-8');
                ?>
                <div class="vb-calendar-month-pill" style="--pill-color: <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>;" title="<?= $tooltipText ?>">
                    <span class="vb-calendar-month-pill-dot"></span>
                    <span class="vb-calendar-month-pill-text">
                        <span class="vb-calendar-month-pill-time"><?= $time ?></span>
                        <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php if ($overflow > 0): ?>
                <div class="vb-calendar-month-more">
                    <?= __('admin.calendar.more_bookings', ['count' => $overflow]) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
