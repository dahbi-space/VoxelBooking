<?php
$activePage = 'settings';
$activeTab = 'account';
ob_start();
include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
?>
<div class="settings-card">
    <h3>Account</h3>
    <p>Manage your operator name, email address, and password.</p>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
