<?php
$activePage = 'settings';
$activeTab = 'general';
ob_start();
include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
?>
<div class="settings-card">
    <h3>General Settings</h3>
    <p>Application name, URL, default timezone, and locale. These settings affect the entire installation.</p>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
