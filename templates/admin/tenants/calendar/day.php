<?php
/**
 * Calendar day view — vertical timeline with booking blocks.
 *
 * Variables: $tenant, $date, $dateStr, $prevDate, $nextDate, $isToday, $today,
 *            $bookings, $hours, $hourStart, $hourEnd, $tenantId, $csrfToken
 */
$tenant    = $tenant ?? [];
/** @var \DateTimeImmutable $date */
$date      = $date ?? new \DateTimeImmutable();
$bookings  = $bookings ?? [];
$hours     = $hours ?? [];
$tenantId  = $tenantId ?? '';
$dateStr   = $dateStr ?? date('Y-m-d');
$prevDate  = $prevDate ?? '';
$nextDate  = $nextDate ?? '';
$isToday   = $isToday ?? false;
$hourStart = $hourStart ?? 7;
$hourEnd   = $hourEnd ?? 21;
$brandColor = $tenant['brand_color'] ?? '#6366f1';

$baseUrl = "/admin/tenants/" . htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8');

ob_start();
?>

<!-- Calendar navigation -->
<div class="vb-calendar-nav">
    <div class="vb-calendar-nav-left">
        <a href="<?= $baseUrl ?>/calendar?date=<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"
           class="vb-btn vb-btn-ghost vb-btn-sm <?= $isToday ? 'is-active' : '' ?>">
            <?= __('admin.calendar.today') ?>
        </a>
        <div class="vb-calendar-nav-arrows">
            <a href="<?= $baseUrl ?>/calendar?date=<?= htmlspecialchars($prevDate, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-btn vb-btn-ghost vb-btn-icon" aria-label="<?= __('admin.calendar.prev_day') ?>">
                <i data-lucide="chevron-left" style="width: 16px; height: 16px;"></i>
            </a>
            <a href="<?= $baseUrl ?>/calendar?date=<?= htmlspecialchars($nextDate, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-btn vb-btn-ghost vb-btn-icon" aria-label="<?= __('admin.calendar.next_day') ?>">
                <i data-lucide="chevron-right" style="width: 16px; height: 16px;"></i>
            </a>
        </div>
        <h2 class="vb-calendar-date-title">
            <?= htmlspecialchars(\App\Engine\Locale::dateLong($date), ENT_QUOTES, 'UTF-8') ?>
            <?php if ($isToday): ?>
                <span class="vb-badge vb-badge-primary"><?= __('admin.calendar.today') ?></span>
            <?php endif; ?>
        </h2>
    </div>
    <div class="vb-calendar-nav-right">
        <a href="<?= $baseUrl ?>/bookings/create?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>"
           class="vb-btn vb-btn-primary vb-btn-sm" id="btn-calendar-new-booking">
            <i data-lucide="plus" style="width: 14px; height: 14px;"></i>
            <?= __('admin.calendar.new_booking') ?>
        </a>
        <div class="vb-segmented">
            <a href="<?= $baseUrl ?>/calendar?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-segmented-btn is-active"><?= __('admin.calendar.view_day') ?></a>
            <a href="<?= $baseUrl ?>/calendar/week?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-segmented-btn"><?= __('admin.calendar.view_week') ?></a>
        </div>
    </div>
</div>

<?php if (empty($bookings)): ?>
    <!-- Empty day -->
    <div class="vb-card vb-calendar-day-container">
        <div class="vb-calendar-empty vb-animate-in">
            <i data-lucide="calendar-x" style="width: 32px; height: 32px; opacity: 0.3; color: var(--vb-accent);"></i>
            <h3><?= __('admin.calendar.empty_day_title') ?></h3>
            <a href="<?= $baseUrl ?>/bookings/create?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>"
               class="vb-btn vb-btn-primary vb-btn-sm" style="margin-top: 12px;">
                <i data-lucide="plus" style="width: 14px; height: 14px;"></i>
                <?= __('admin.calendar.new_booking') ?>
            </a>
        </div>
    </div>
<?php else: ?>
    <!-- Day timeline -->
    <div class="vb-card vb-calendar-day-container">
        <div class="vb-calendar-timeline">
            <?php
            $totalMinutes = ($hourEnd - $hourStart + 1) * 60;
            foreach ($hours as $h):
                $timeObj = new DateTimeImmutable(sprintf('%02d:00', $h));
                $hourTime = sprintf('%02d:00', $h);
            ?>
            <div class="vb-calendar-hour-row">
                <div class="vb-calendar-hour-label">
                    <?= htmlspecialchars(\App\Engine\Locale::time($timeObj), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <a href="<?= $baseUrl ?>/bookings/create?date=<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?>&time=<?= $hourTime ?>"
                   class="vb-calendar-hour-line vb-calendar-hour-link"
                   title="<?= __('admin.calendar.new_booking') ?>"></a>
            </div>
            <?php endforeach; ?>

            <!-- Booking blocks positioned absolutely -->
            <?php foreach ($bookings as $i => $b):
                $start     = new DateTimeImmutable($b['start_datetime']);
                $end       = new DateTimeImmutable($b['end_datetime']);
                $startMins = ((int) $start->format('H') - $hourStart) * 60 + (int) $start->format('i');
                $endMins   = ((int) $end->format('H') - $hourStart) * 60 + (int) $end->format('i');
                $duration  = max($endMins - $startMins, 15);

                // Position as percentage of total timeline
                $topPct    = ($startMins / $totalMinutes) * 100;
                $heightPct = ($duration / $totalMinutes) * 100;

                // Use service color if available, else tenant brand color
                $color = $b['service_color'] ?: $brandColor;

                $statusClass = match ($b['status']) {
                    'confirmed' => 'is-confirmed',
                    'completed' => 'is-completed',
                    'pending'   => 'is-pending',
                    'no_show'   => 'is-noshow',
                    default     => '',
                };
            ?>
            <div class="vb-booking-block <?= $statusClass ?> vb-fade-in-up stagger-<?= min($i + 1, 6) ?>"
                 style="top: <?= round($topPct, 2) ?>%; height: <?= round($heightPct, 2) ?>%; --block-color: <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>;">
                <div class="vb-booking-block-content">
                    <span class="vb-booking-block-name">
                        <?= htmlspecialchars($b['customer_name'] ?? __('admin.calendar.no_customer'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="vb-booking-block-time">
                        <?= htmlspecialchars(\App\Engine\Locale::time($start), ENT_QUOTES, 'UTF-8') ?>
                        – <?= htmlspecialchars(\App\Engine\Locale::time($end), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php $blockLabel = booking_display_label($b); ?>
                    <?php if ($blockLabel !== '—'): ?>
                    <span class="vb-booking-block-service">
                        <?= htmlspecialchars($blockLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
