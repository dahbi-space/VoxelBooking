<?php
/**
 * Privacy deletion requested view — shown after customer submits a deletion request.
 *
 * Variables: $tenant, $customer, $pageTitle
 */
use App\Engine\View;

$tenant = $tenant ?? [];
$customer = $customer ?? [];
$pageTitle = $pageTitle ?? 'Deletion Requested';
$brandColor = $tenant['brand_color'] ?? '#4F46E5';
?>
<!DOCTYPE html>
<html lang="<?= View::e($tenant['locale'] ?? 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= View::e($pageTitle) ?></title>
    <?php include dirname(__DIR__) . '/partials/admin-head.php'; ?>
    <style>
        body {
            background: var(--vb-admin-bg-base);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .privacy-container {
            width: 100%;
            max-width: 480px;
            text-align: center;
            animation: vb-fade-in-up var(--vb-duration-slow) var(--vb-ease-out) both;
        }

        .privacy-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.5rem;
            color: var(--vb-admin-warning);
            opacity: 0.8;
        }

        .privacy-title {
            font-size: var(--vb-text-2xl);
            font-weight: 700;
            letter-spacing: var(--vb-tracking-tight);
            margin-bottom: 0.75rem;
        }

        .privacy-message {
            font-size: var(--vb-text-sm);
            color: var(--vb-admin-text-secondary);
            line-height: var(--vb-leading-relaxed);
            max-width: 380px;
            margin: 0 auto;
        }

        .privacy-note {
            margin-top: 1.5rem;
            padding: 1rem;
            background: var(--vb-admin-warning-bg);
            border-radius: var(--vb-radius-md);
            border-left: 3px solid var(--vb-admin-warning);
            font-size: var(--vb-text-xs);
            color: var(--vb-admin-warning);
            text-align: left;
        }

        .privacy-footer {
            margin-top: 2.5rem;
            font-size: var(--vb-text-xs);
            color: var(--vb-admin-text-tertiary);
        }
    </style>
</head>
<body>
    <div class="privacy-container">
        <svg class="privacy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>

        <h1 class="privacy-title">Deletion Requested</h1>

        <p class="privacy-message">
            Your data deletion request has been logged.
            The business operating this service has been notified
            and will process your request.
        </p>

        <div class="privacy-note">
            <strong>What happens next:</strong> The business will review your request
            and remove your personal data. Under GDPR, they must respond
            within 30 days. Booking records may be retained in anonymized form
            for operational history, but all personal identifiers will be removed.
        </div>

        <div class="privacy-footer">
            Powered by VoxelBooking · <?= View::e($tenant['name'] ?? '') ?>
        </div>
    </div>
</body>
</html>
