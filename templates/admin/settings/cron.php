<?php
$activePage = 'settings';
$activeTab = 'cron';
ob_start();
include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
?>
<div class="settings-card">
    <h3>Cron Configuration</h3>
    <p>Configure your server's cron job to process booking reminders, data retention, and rate limit cleanup.</p>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
