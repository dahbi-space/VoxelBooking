<?php
/**
 * Admin shell layout — Visual Design §16: Admin Shell Composition.
 *
 * Sidebar (240px) + glass topbar (56px) + content area.
 * All admin pages extend this layout via ob_start() + $content.
 *
 * Icons: Lucide via data-lucide (rendered by admin/app.js createIcons).
 * State: Alpine.js (CSP build) — x-data="adminShell" registered in app.js.
 *        All @click handlers reference method names (no inline JS expressions).
 *        Sidebar class toggling done via $refs in JS (CSP-safe).
 * Styles: admin.css (Tailwind 4) + admin-head.php (design tokens).
 *
 * Variables: $user, $version, $pageTitle, $content (HTML), $activePage, $csrfToken
 */
$user = $user ?? [];
$version = $version ?? '0.0.0';
$pageTitle = $pageTitle ?? __('admin.layout.default_title');
$activePage = $activePage ?? 'dashboard';
$csrfToken = $csrfToken ?? '';
?>
<!DOCTYPE html>
<html lang="<?= \App\Engine\Locale::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(app_name(), ENT_QUOTES, 'UTF-8') ?></title>
    <?php include dirname(__DIR__) . '/partials/admin-head.php'; ?>
    <link rel="stylesheet" href="/assets/css/admin-css.css">
</head>
<body x-data="adminShell">

    <!-- Mobile overlay: x-show="sidebarOpen" is a CSP-safe property reference -->
    <div class="vb-sidebar-overlay"
         x-show="sidebarOpen"
         @click="closeSidebar"></div>

    <!-- Sidebar: class toggling done via $refs in app.js (CSP-safe) -->
    <aside class="vb-sidebar" x-ref="sidebar">
        <div class="vb-sidebar-brand">
            <svg class="vb-sidebar-logo" width="24" height="26" viewBox="0 0 48 52" xmlns="http://www.w3.org/2000/svg">
                <polygon points="24,2 46,14 24,26 2,14" fill="currentColor" opacity="1.0"/>
                <polygon points="2,14 24,26 24,50 2,38" fill="currentColor" opacity="0.7"/>
                <polygon points="46,14 24,26 24,50 46,38" fill="currentColor" opacity="0.4"/>
            </svg>
            <span class="vb-sidebar-name"><?= htmlspecialchars(app_name(), ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <nav class="vb-sidebar-nav">
            <div class="vb-sidebar-section">
                <?php if (\App\Engine\Auth::isOperator()): ?>
                <a href="/admin" class="vb-sidebar-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <i data-lucide="layout-dashboard"></i>
                    <?= __('admin.nav.dashboard') ?>
                </a>
                <a href="/admin" class="vb-sidebar-link <?= $activePage === 'tenants' ? 'active' : '' ?>">
                    <i data-lucide="building-2"></i>
                    <?= __('admin.nav.tenants') ?>
                </a>
                <?php endif; ?>
                <a href="/admin" class="vb-sidebar-link <?= $activePage === 'bookings' ? 'active' : '' ?>">
                    <i data-lucide="calendar"></i>
                    <?= __('admin.nav.all_bookings') ?>
                </a>
            </div>
            <?php if (\App\Engine\Auth::isOperator()): ?>
            <div class="vb-sidebar-section">
                <div class="vb-sidebar-section-label"><?= __('admin.nav.system') ?></div>
                <a href="/admin/settings" class="vb-sidebar-link <?= str_starts_with($activePage, 'settings') ? 'active' : '' ?>">
                    <i data-lucide="settings"></i>
                    <?= __('admin.nav.settings') ?>
                </a>
                <a href="/admin/deletion-queue" class="vb-sidebar-link <?= $activePage === 'deletion-queue' ? 'active' : '' ?>">
                    <i data-lucide="shield"></i>
                    <?= __('admin.nav.deletion_queue') ?>
                </a>
            </div>
            <?php endif; ?>
        </nav>

        <div class="vb-sidebar-footer">
            <?= htmlspecialchars(app_name(), ENT_QUOTES, 'UTF-8') ?> v<?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?>
        </div>
    </aside>

    <!-- Main -->
    <div class="vb-main">
        <header class="vb-topbar">
            <div class="vb-topbar-left">
                <button type="button"
                        class="vb-hamburger"
                        @click="toggleSidebar"
                        aria-label="<?= __('admin.nav.toggle_sidebar') ?>">
                    <i data-lucide="menu"></i>
                </button>
                <h1 class="vb-topbar-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <div class="vb-topbar-right">
                <span class="vb-topbar-user">
                    <i data-lucide="user"></i>
                    <?= htmlspecialchars($user['name'] ?? __('admin.layout.operator'), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <button type="button"
                        class="vb-topbar-btn"
                        @click="toggleTheme"
                        aria-label="<?= __('admin.nav.toggle_theme') ?>">
                    <i data-lucide="sun" class="icon-sun"></i>
                    <i data-lucide="moon" class="icon-moon"></i>
                </button>
                <form method="POST" action="/auth/logout" class="m-0">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="vb-logout-btn">
                        <i data-lucide="log-out"></i>
                        <?= __('admin.nav.sign_out') ?>
                    </button>
                </form>
            </div>
        </header>

        <?php if (\App\Engine\DemoMode::isActive()): ?>
        <div class="vb-demo-banner">
            <i data-lucide="alert-circle" class="vb-demo-banner-icon"></i>
            <div>
                <span class="vb-demo-banner-title"><?= __('admin.demo.banner_title') ?></span>
                <span class="vb-demo-banner-desc"><?= __('admin.demo.banner_desc') ?></span>
            </div>
        </div>
        <?php endif; ?>

        <main class="vb-content">
            <?= $content ?? '' ?>
        </main>
    </div>

    <?php if (\App\Engine\DemoMode::isActive()): ?>
    <script>window.VB_DEMO = true;</script>
    <?php endif; ?>
    <script src="/assets/js/admin.js" type="module"></script>
</body>
</html>
