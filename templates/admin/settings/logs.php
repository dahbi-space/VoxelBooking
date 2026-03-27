<?php
$activePage = 'settings';
$activeTab = 'logs';
ob_start();
include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
?>
<div class="settings-card">
    <h3>Application Logs</h3>
    <p>View recent application errors and activity. Log files are stored in <code>storage/logs/</code>.</p>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
