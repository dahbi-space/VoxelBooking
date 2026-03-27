<?php
$activePage = 'settings';
$activeTab = 'general';
ob_start();
include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
$flash = $flash ?? null;
$settings = $settings ?? [];
?>
<?php if ($flash): ?>
    <div class="flash-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<form method="POST" action="/admin/settings">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="settings-card">
        <h3>General Settings</h3>
        <p class="card-desc">Core application settings that affect the entire installation.</p>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="app_name">Application Name</label>
                <input type="text" id="app_name" name="app_name" class="form-input" value="<?= htmlspecialchars($settings['app_name'] ?? 'VoxelBooking', ENT_QUOTES, 'UTF-8') ?>" placeholder="VoxelBooking" required>
                <p class="form-hint">Displayed in the browser title bar, emails, and the admin sidebar.</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="app_url">Application URL</label>
                <input type="url" id="app_url" name="app_url" class="form-input" value="<?= htmlspecialchars($settings['app_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://booking.example.com">
                <p class="form-hint">The public URL of your VoxelBooking installation.</p>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="timezone">Timezone</label>
                <select id="timezone" name="timezone" class="form-select">
                    <?php
                    $currentTz = $settings['timezone'] ?? 'UTC';
                    $timezones = ['UTC', 'Europe/Amsterdam', 'Europe/Berlin', 'Europe/London', 'Europe/Paris', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Asia/Tokyo', 'Asia/Shanghai', 'Australia/Sydney'];
                    foreach ($timezones as $tz):
                    ?>
                        <option value="<?= $tz ?>" <?= $currentTz === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint">Used for booking time calculations and display.</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="date_format">Date Format</label>
                <select id="date_format" name="date_format" class="form-select">
                    <?php
                    $currentFmt = $settings['date_format'] ?? 'Y-m-d';
                    $formats = ['Y-m-d' => '2026-03-27', 'd/m/Y' => '27/03/2026', 'm/d/Y' => '03/27/2026', 'd.m.Y' => '27.03.2026', 'j M Y' => '27 Mar 2026'];
                    foreach ($formats as $fmt => $example):
                    ?>
                        <option value="<?= $fmt ?>" <?= $currentFmt === $fmt ? 'selected' : '' ?>><?= $example ?> (<?= $fmt ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="settings-card">
        <h3>System Information</h3>
        <div class="info-row"><span class="info-label">Version</span><span class="info-value"><code>v<?= htmlspecialchars($version ?? '', ENT_QUOTES, 'UTF-8') ?></code></span></div>
        <div class="info-row"><span class="info-label">PHP</span><span class="info-value"><code><?= PHP_VERSION ?></code></span></div>
        <div class="info-row"><span class="info-label">Database</span><span class="info-value"><code>MySQL</code></span></div>
    </div>

    <button type="submit" class="btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save general settings
    </button>
</form>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
