<?php
/**
 * Admin shell layout — Visual Design §16: Admin Shell Composition.
 *
 * Sidebar (240px) + glass topbar (56px) + content area.
 * All admin pages extend this layout via ob_start() + $content.
 *
 * Variables: $user, $version, $pageTitle, $content (HTML), $activePage, $csrfToken
 */
$user = $user ?? [];
$version = $version ?? '0.0.0';
$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';
$csrfToken = $csrfToken ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — VoxelBooking</title>
    <?php include dirname(__DIR__) . '/partials/admin-head.php'; ?>
    <style>
        body { background: var(--vb-admin-bg-base); min-height: 100vh; display: flex; }

        /* ══ Sidebar — §16 Sidebar Rhythm ══ */
        .vb-sidebar {
            width: var(--vb-sidebar-width); min-height: 100vh;
            background: var(--vb-admin-bg-surface);
            border-right: 1px solid var(--vb-admin-border-subtle);
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; bottom: 0; z-index: 40;
            transition: transform var(--vb-duration-normal) var(--vb-ease-out);
        }

        /* Brand zone */
        .vb-sidebar-brand {
            height: var(--vb-topbar-height);
            display: flex; align-items: center; gap: 0.625rem;
            padding: 0 1.125rem;
        }
        .vb-sidebar-logo {
            color: var(--vb-admin-accent); flex-shrink: 0;
            filter: drop-shadow(0 0 8px var(--vb-admin-accent-glow));
        }
        .vb-sidebar-name {
            font-size: 1rem; font-weight: 600;
            letter-spacing: -0.025em; white-space: nowrap;
        }

        /* Navigation zone */
        .vb-sidebar-nav { flex: 1; padding: 0.75rem 0.625rem; overflow-y: auto; }
        .vb-sidebar-section { margin-bottom: 1.25rem; }
        .vb-sidebar-section-label {
            font-size: var(--vb-text-xs); font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--vb-admin-text-ghost);
            padding: 0 0.75rem 0.375rem; user-select: none;
        }
        .vb-sidebar-link {
            display: flex; align-items: center; gap: 0.625rem;
            padding: 0.5rem 0.75rem; margin-bottom: 1px;
            font-size: var(--vb-text-sm); font-weight: 450;
            color: var(--vb-admin-text-secondary); text-decoration: none;
            border-radius: var(--vb-radius-md);
            transition: background var(--vb-duration-fast), color var(--vb-duration-fast);
        }
        .vb-sidebar-link:hover {
            background: var(--vb-admin-bg-hover); color: var(--vb-admin-text-primary);
        }
        .vb-sidebar-link.active {
            background: var(--vb-admin-accent-dim); color: var(--vb-admin-accent); font-weight: 500;
        }
        .vb-sidebar-link svg {
            width: 18px; height: 18px; flex-shrink: 0;
            color: var(--vb-admin-text-tertiary);
            transition: color var(--vb-duration-fast);
        }
        .vb-sidebar-link:hover svg { color: var(--vb-admin-text-secondary); }
        .vb-sidebar-link.active svg { color: var(--vb-admin-accent); }

        /* Footer zone */
        .vb-sidebar-footer {
            padding: 0.75rem 1.125rem;
            border-top: 1px solid var(--vb-admin-border-subtle);
            font-size: var(--vb-text-xs); color: var(--vb-admin-text-ghost);
        }

        /* ══ Main wrapper ══ */
        .vb-main {
            margin-left: var(--vb-sidebar-width); flex: 1;
            min-height: 100vh; display: flex; flex-direction: column;
        }

        /* ══ Topbar — §16 glass effect ══ */
        .vb-topbar {
            height: var(--vb-topbar-height);
            background: rgba(var(--vb-admin-bg-surface-rgb), 0.8);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--vb-admin-border-subtle);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky; top: 0; z-index: 30;
        }
        .vb-topbar-left { display: flex; align-items: center; gap: 1rem; }
        .vb-topbar-title {
            font-size: var(--vb-text-md); font-weight: 600;
            letter-spacing: var(--vb-tracking-tight);
        }
        .vb-topbar-right { display: flex; align-items: center; gap: 0.75rem; }
        .vb-topbar-user {
            font-size: var(--vb-text-sm); color: var(--vb-admin-text-secondary);
            display: flex; align-items: center; gap: 0.375rem;
        }
        .vb-topbar-user svg { width: 16px; height: 16px; }

        /* Topbar icon button */
        .vb-topbar-btn {
            width: 36px; height: 36px; border-radius: var(--vb-radius-full);
            display: flex; align-items: center; justify-content: center;
            color: var(--vb-admin-text-secondary); background: transparent;
            border: 1px solid var(--vb-admin-border-subtle);
            cursor: pointer; transition: background var(--vb-duration-fast);
            position: relative;
        }
        .vb-topbar-btn:hover { background: var(--vb-admin-bg-hover); }
        .vb-topbar-btn svg { width: 16px; height: 16px; }

        /* Theme toggle crossfade */
        .vb-topbar-btn .icon-sun,
        .vb-topbar-btn .icon-moon {
            position: absolute;
            transition: opacity var(--vb-duration-fast), transform var(--vb-duration-fast);
        }
        .vb-topbar-btn .icon-sun { opacity: 1; transform: rotate(0deg); }
        .vb-topbar-btn .icon-moon { opacity: 0; transform: rotate(-90deg); }
        [data-theme="dark"] .vb-topbar-btn .icon-sun { opacity: 0; transform: rotate(90deg); }
        [data-theme="dark"] .vb-topbar-btn .icon-moon { opacity: 1; transform: rotate(0deg); }

        /* Logout */
        .vb-logout-btn {
            font-size: var(--vb-text-sm); font-weight: 500;
            color: var(--vb-admin-text-secondary); background: transparent;
            border: 1px solid var(--vb-admin-border-subtle);
            padding: 0.375rem 0.75rem; border-radius: var(--vb-radius-md);
            cursor: pointer; display: flex; align-items: center; gap: 0.375rem;
            font-family: var(--vb-font-sans);
            transition: background var(--vb-duration-fast), color var(--vb-duration-fast), border-color var(--vb-duration-fast);
        }
        .vb-logout-btn:hover { background: var(--vb-admin-error-bg); color: var(--vb-admin-error); border-color: var(--vb-admin-error); }
        .vb-logout-btn svg { width: 16px; height: 16px; }

        /* ══ Content — §16 content rhythm ══ */
        .vb-content {
            flex: 1; padding: 1.5rem;
            animation: vb-fade-in-up var(--vb-duration-slow) var(--vb-ease-out) both;
            max-width: 1200px;
        }
        @media (min-width: 1440px) { .vb-content { margin: 0 auto; width: 100%; } }

        /* ══ Mobile ══ */
        .vb-hamburger { display: none; }
        .vb-sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 35; backdrop-filter: blur(2px); }
        .vb-sidebar-overlay.active { display: block; }

        @media (max-width: 768px) {
            .vb-sidebar { transform: translateX(-100%); width: 280px; box-shadow: var(--vb-admin-shadow-xl); }
            .vb-sidebar.open { transform: translateX(0); }
            .vb-main { margin-left: 0; }
            .vb-hamburger {
                display: flex; width: 36px; height: 36px;
                align-items: center; justify-content: center;
                background: transparent; border: 1px solid var(--vb-admin-border-subtle);
                border-radius: var(--vb-radius-md); cursor: pointer;
                color: var(--vb-admin-text-secondary);
            }
            .vb-hamburger svg { width: 20px; height: 20px; }
            .vb-content { padding: 1rem; }
        }
    </style>
</head>
<body>
    <!-- Mobile overlay -->
    <div class="vb-sidebar-overlay" id="sidebar-overlay"></div>

    <!-- Sidebar -->
    <aside class="vb-sidebar" id="sidebar">
        <div class="vb-sidebar-brand">
            <svg class="vb-sidebar-logo" width="24" height="26" viewBox="0 0 48 52" xmlns="http://www.w3.org/2000/svg">
                <polygon points="24,2 46,14 24,26 2,14" fill="currentColor" opacity="1.0"/>
                <polygon points="2,14 24,26 24,50 2,38" fill="currentColor" opacity="0.7"/>
                <polygon points="46,14 24,26 24,50 46,38" fill="currentColor" opacity="0.4"/>
            </svg>
            <span class="vb-sidebar-name">VoxelBooking</span>
        </div>

        <nav class="vb-sidebar-nav">
            <div class="vb-sidebar-section">
                <a href="/admin" class="vb-sidebar-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    Dashboard
                </a>
                <a href="#" class="vb-sidebar-link <?= $activePage === 'tenants' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
                    Tenants
                </a>
                <a href="#" class="vb-sidebar-link <?= $activePage === 'bookings' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    All Bookings
                </a>
            </div>
            <div class="vb-sidebar-section">
                <div class="vb-sidebar-section-label">System</div>
                <a href="/admin/settings" class="vb-sidebar-link <?= str_starts_with($activePage, 'settings') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                    Settings
                </a>
            </div>
        </nav>

        <div class="vb-sidebar-footer">
            VoxelBooking v<?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?>
        </div>
    </aside>

    <!-- Main -->
    <div class="vb-main">
        <header class="vb-topbar">
            <div class="vb-topbar-left">
                <button type="button" class="vb-hamburger" id="hamburger" aria-label="Toggle sidebar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                </button>
                <h1 class="vb-topbar-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <div class="vb-topbar-right">
                <span class="vb-topbar-user">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <?= htmlspecialchars($user['name'] ?? 'Operator', ENT_QUOTES, 'UTF-8') ?>
                </span>
                <button type="button" class="vb-topbar-btn" id="theme-toggle" aria-label="Toggle theme">
                    <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
                <form method="POST" action="/auth/logout" style="margin:0;">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="vb-logout-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        Sign out
                    </button>
                </form>
            </div>
        </header>

        <main class="vb-content">
            <?= $content ?? '' ?>
        </main>
    </div>

    <script>
    (function() {
        var toggle = document.getElementById('theme-toggle');
        if (toggle) {
            toggle.addEventListener('click', function() {
                var html = document.documentElement;
                var current = html.getAttribute('data-theme');
                var next = current === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-theme', next);
                localStorage.setItem('vb-theme', next);
            });
        }
        var hamburger = document.getElementById('hamburger');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        if (hamburger && sidebar) {
            hamburger.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                if (overlay) overlay.classList.toggle('active');
            });
            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                });
            }
        }
    })();
    </script>
</body>
</html>
