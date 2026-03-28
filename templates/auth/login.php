<?php
/**
 * Login page — Visual Design §15: Authentication Surface.
 *
 * Split layout: environmental panel (left) + form (right).
 * Gradient mesh background. Staggered entrance animation.
 * Shake on error. No visible card border in light mode.
 *
 * Variables: $csrfToken, $error
 */
$error = $error ?? null;
$csrfToken = $csrfToken ?? '';
?>
<!DOCTYPE html>
<html lang="<?= \App\Engine\Locale::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="<?= __('auth.meta_description', ['app_name' => app_name()]) ?>">
    <title><?= __('auth.page_title', ['app_name' => app_name()]) ?></title>
    <?php include dirname(__DIR__) . '/partials/admin-head.php'; ?>
    <style>
        /* ── Login Layout — §15 ── */
        body { background: var(--vb-admin-bg-base); min-height: 100vh; display: flex; overflow: hidden; }

        /* Environmental panel */
        .login-env {
            flex: 1; display: flex; align-items: center; justify-content: center;
            position: relative; overflow: hidden;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(79, 70, 229, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(129, 140, 248, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 60% 80%, rgba(99, 102, 241, 0.04) 0%, transparent 50%),
                var(--vb-admin-bg-base);
        }
        [data-theme="dark"] .login-env {
            background:
                radial-gradient(ellipse at 20% 50%, rgba(129, 140, 248, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(99, 102, 241, 0.12) 0%, transparent 50%),
                radial-gradient(ellipse at 60% 80%, rgba(79, 70, 229, 0.06) 0%, transparent 50%),
                var(--vb-admin-bg-base);
        }

        /* Geometric grid (isometric voxel lines at 3% opacity) */
        .login-env::before {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(30deg, var(--vb-admin-accent) 1px, transparent 1px),
                linear-gradient(150deg, var(--vb-admin-accent) 1px, transparent 1px);
            background-size: 60px 104px;
            opacity: 0.025;
        }
        [data-theme="dark"] .login-env::before { opacity: 0.04; }

        /* Floating voxel (environmental decoration) */
        .login-env-voxel {
            color: var(--vb-admin-accent); opacity: 0.08;
            animation: vb-float 8s ease-in-out infinite;
        }
        [data-theme="dark"] .login-env-voxel { opacity: 0.12; }

        /* Form panel */
        .login-panel {
            width: 480px; min-height: 100vh;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 3rem 3rem;
            background: var(--vb-admin-bg-surface);
            border-left: 1px solid var(--vb-admin-border-subtle);
            position: relative;
        }

        .login-inner { width: 100%; max-width: 340px; }

        /* Hero logo */
        .login-hero {
            display: flex; flex-direction: column; align-items: center;
            gap: 0.75rem; margin-bottom: 2.5rem;
            animation: vb-fade-in var(--vb-duration-normal) var(--vb-ease-out) 100ms both;
        }
        .login-hero-cube {
            filter: drop-shadow(0 8px 24px var(--vb-admin-accent-glow));
            animation: vb-float 4s ease-in-out infinite;
        }
        .login-hero-name {
            font-size: var(--vb-text-3xl); font-weight: 700;
            letter-spacing: -0.03em; color: var(--vb-admin-text-primary);
        }
        .login-hero-sub {
            font-size: var(--vb-text-sm); color: var(--vb-admin-text-tertiary);
            font-weight: 400; margin-top: -0.25rem;
        }

        /* Form card — no border in light mode per §14 */
        .login-form-card {
            animation: vb-fade-in-up 250ms var(--vb-ease-out) 200ms both;
        }

        .login-form-card h2 {
            font-size: var(--vb-text-xl); font-weight: 600;
            letter-spacing: var(--vb-tracking-tight); margin-bottom: 1.75rem;
            color: var(--vb-admin-text-primary);
        }

        /* Input overrides for login (larger per §15) */
        .login-form-card .vb-input { height: 44px; font-size: var(--vb-text-md); }
        .login-form-card .vb-input-icon { padding-left: 2.75rem; }
        .login-form-card .vb-form-group { margin-bottom: 1.25rem; }

        /* Submit button (full width, xl size) */
        .login-submit {
            width: 100%; height: 48px; margin-top: 0.75rem;
            font-size: var(--vb-text-md); font-weight: 600;
            animation: vb-fade-in-up var(--vb-duration-slow) var(--vb-ease-out) 440ms both;
        }
        .login-submit:hover { transform: translateY(-1px); box-shadow: var(--vb-admin-shadow-md); }
        .login-submit:active { transform: scale(0.98); box-shadow: none; }

        /* Stagger form fields */
        .login-field-1 { animation: vb-fade-in-up var(--vb-duration-slow) var(--vb-ease-out) 320ms both; }
        .login-field-2 { animation: vb-fade-in-up var(--vb-duration-slow) var(--vb-ease-out) 380ms both; }

        /* Error alert */
        .login-error {
            margin-bottom: 1.25rem;
            animation: vb-fade-in-down var(--vb-duration-normal) var(--vb-ease-out) both;
        }

        /* Theme toggle */
        .login-theme-toggle {
            position: absolute; top: 1.5rem; right: 1.5rem;
            width: 40px; height: 40px; border-radius: var(--vb-radius-full);
            display: flex; align-items: center; justify-content: center;
            background: transparent; border: 1px solid var(--vb-admin-border-subtle);
            cursor: pointer; transition: background var(--vb-duration-fast);
            color: var(--vb-admin-text-secondary); z-index: 10;
        }
        .login-theme-toggle:hover { background: var(--vb-admin-bg-hover); }
        .login-theme-toggle .icon-sun,
        .login-theme-toggle .icon-moon { position: absolute; transition: opacity var(--vb-duration-fast), transform var(--vb-duration-fast); }
        .login-theme-toggle .icon-sun { opacity: 1; transform: rotate(0deg); }
        .login-theme-toggle .icon-moon { opacity: 0; transform: rotate(-90deg); }
        [data-theme="dark"] .login-theme-toggle .icon-sun { opacity: 0; transform: rotate(90deg); }
        [data-theme="dark"] .login-theme-toggle .icon-moon { opacity: 1; transform: rotate(0deg); }

        /* Footer */
        .login-footer {
            margin-top: 2rem; text-align: center;
            font-size: var(--vb-text-xs); color: var(--vb-admin-text-ghost);
            animation: vb-fade-in var(--vb-duration-normal) var(--vb-ease-out) 600ms both;
        }

        /* ── Mobile ── */
        @media (max-width: 768px) {
            .login-env { display: none; }
            .login-panel {
                width: 100%; border-left: none;
                background:
                    radial-gradient(ellipse at 50% 0%, rgba(79, 70, 229, 0.04) 0%, transparent 50%),
                    var(--vb-admin-bg-surface);
            }
            [data-theme="dark"] .login-panel {
                background:
                    radial-gradient(ellipse at 50% 0%, rgba(129, 140, 248, 0.06) 0%, transparent 50%),
                    var(--vb-admin-bg-surface);
            }
        }
        @media (max-width: 400px) {
            .login-panel { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Environmental Panel -->
    <div class="login-env">
        <svg class="login-env-voxel" width="240" height="260" viewBox="0 0 48 52" xmlns="http://www.w3.org/2000/svg">
            <polygon points="24,2 46,14 24,26 2,14" fill="currentColor" opacity="1.0"/>
            <polygon points="2,14 24,26 24,50 2,38" fill="currentColor" opacity="0.7"/>
            <polygon points="46,14 24,26 24,50 46,38" fill="currentColor" opacity="0.4"/>
        </svg>
    </div>

    <!-- Form Panel -->
    <div class="login-panel">
        <button type="button" class="login-theme-toggle" id="theme-toggle" aria-label="<?= __('auth.toggle_theme') ?>">
            <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>

        <div class="login-inner">
            <!-- Hero Logo -->
            <div class="login-hero">
                <svg class="login-hero-cube" width="56" height="60" viewBox="0 0 48 52" xmlns="http://www.w3.org/2000/svg">
                    <polygon points="24,2 46,14 24,26 2,14" fill="var(--vb-admin-accent)" opacity="1.0"/>
                    <polygon points="2,14 24,26 24,50 2,38" fill="var(--vb-admin-accent)" opacity="0.7"/>
                    <polygon points="46,14 24,26 24,50 46,38" fill="var(--vb-admin-accent)" opacity="0.4"/>
                </svg>
                <span class="login-hero-name"><?= app_name() ?></span>
                <span class="login-hero-sub"><?= __('auth.hero_sub') ?></span>
            </div>

            <!-- Login Form -->
            <div class="login-form-card<?= $error ? ' vb-shake' : '' ?>">
                <h2><?= __('auth.login_heading') ?></h2>

                <?php if ($error): ?>
                    <div class="vb-alert vb-alert-error login-error">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/admin/login" id="login-form">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="vb-form-group login-field-1">
                        <label for="email" class="vb-label"><?= __('auth.email_label') ?></label>
                        <div class="vb-input-wrap">
                            <svg class="vb-icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            <input type="email" id="email" name="email" class="vb-input vb-input-icon" placeholder="<?= __('auth.email_placeholder') ?>" required autocomplete="email" autofocus value="<?= htmlspecialchars($lastEmail ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="vb-form-group login-field-2">
                        <label for="password" class="vb-label"><?= __('auth.password_label') ?></label>
                        <div class="vb-input-wrap">
                            <svg class="vb-icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" id="password" name="password" class="vb-input vb-input-icon" placeholder="<?= __('auth.password_placeholder') ?>" required autocomplete="current-password" minlength="8">
                        </div>
                    </div>

                    <button type="submit" class="vb-btn vb-btn-primary login-submit"><?= __('auth.login_button') ?></button>
                </form>
            </div>

            <div class="login-footer">
                <?= __('auth.footer', ['app_name' => app_name()]) ?>
            </div>
        </div>
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
    })();
    </script>
</body>
</html>
