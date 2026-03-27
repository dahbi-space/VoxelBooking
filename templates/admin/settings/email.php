<?php
$activePage = 'settings';
$activeTab = 'email';
ob_start();
include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
?>
<div class="settings-card">
    <h3>Email Configuration</h3>
    <p>SMTP server settings for sending booking confirmations, reminders, and notifications.</p>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
