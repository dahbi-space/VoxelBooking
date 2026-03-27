<?php
/**
 * Login page — standalone template.
 *
 * Same brand system as the install wizard: hero logo, Inter font,
 * admin tokens, theme toggle. PRD §XV: unified login at /admin/login.
 *
 * Variables: $csrfToken, $error
 */
$error = $error ?? null;
$csrfToken = $csrfToken ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="VoxelBooking Admin Login">
    <title>Login — VoxelBooking</title>
    <?php include dirname(__DIR__) . '/partials/admin-head.php'; ?>
    <style>
        body {
            font-family: var(--vb-font-sans);
            font-feature-settings: 'cv02' 1, 'cv03' 1, 'cv04' 1, 'cv11' 1;
            background: var(--vb-admin-bg-base);
            color: var(--vb-admin-text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            line-height: var(--vb-leading-normal);
            letter-spacing: var(--vb-tracking-normal);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .login-container { width: 100%; max-width: 400px; }

        /* ── Hero Logo ── */
        .vb-hero-logo {
            display: flex; flex-direction: column; align-items: center;
            gap: 0.75rem; margin-bottom: 2rem;
        }
        .vb-logo-cube { filter: drop-shadow(0 0 12px var(--vb-admin-accent-glow)); animation: voxel-float 4s ease-in-out infinite; }
        @keyframes voxel-float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
        .vb-logo-text { font-size: 1.5rem; font-weight: 600; letter-spacing: var(--vb-tracking-tight); color: var(--vb-admin-text-primary); }
        .vb-logo-subtitle { font-size: var(--vb-text-sm); color: var(--vb-admin-text-tertiary); }

        /* ── Card ── */
        .login-card {
            background: var(--vb-admin-bg-surface);
            border: 1px solid var(--vb-admin-border-subtle);
            border-radius: var(--vb-radius);
            padding: 2rem;
            box-shadow: var(--vb-admin-shadow-md);
        }
        .login-card h2 {
            font-size: var(--vb-text-lg); font-weight: 600; margin-bottom: 1.5rem;
            letter-spacing: var(--vb-tracking-tight);
        }

        /* ── Form ── */
        .form-group { margin-bottom: 1.25rem; }
        .form-label {
            display: block; font-size: var(--vb-text-sm); font-weight: 500;
            color: var(--vb-admin-text-secondary); margin-bottom: 0.375rem;
        }
        .form-input-wrapper {
            position: relative; display: flex; align-items: center;
        }
        .form-input-icon {
            position: absolute; left: 0.75rem; color: var(--vb-admin-text-tertiary);
            pointer-events: none; width: 18px; height: 18px;
        }
        .form-input {
            width: 100%; padding: 0.625rem 0.75rem 0.625rem 2.5rem;
            font-family: var(--vb-font-sans); font-size: var(--vb-text-base);
            color: var(--vb-admin-text-primary);
            background: var(--vb-admin-bg-input);
            border: 1px solid var(--vb-admin-border-medium);
            border-radius: var(--vb-radius-sm);
            outline: none; transition: border-color 150ms, box-shadow 150ms;
        }
        .form-input:focus {
            border-color: var(--vb-admin-accent);
            box-shadow: 0 0 0 3px var(--vb-admin-accent-dim);
        }
        .form-input::placeholder { color: var(--vb-admin-text-ghost); }

        /* ── Alert ── */
        .login-error {
            display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem;
            background: var(--vb-admin-error-bg); border: 1px solid var(--vb-admin-error);
            border-radius: var(--vb-radius-sm); margin-bottom: 1.25rem;
            font-size: var(--vb-text-sm); color: var(--vb-admin-error);
        }
        .login-error svg { flex-shrink: 0; width: 16px; height: 16px; }

        /* ── Button ── */
        .btn-primary {
            width: 100%; padding: 0.75rem; font-family: var(--vb-font-sans);
            font-size: var(--vb-text-base); font-weight: 600;
            color: #FFFFFF; background: var(--vb-admin-accent);
            border: none; border-radius: var(--vb-radius-sm);
            cursor: pointer; transition: background 150ms, transform 100ms;
        }
        .btn-primary:hover { background: var(--vb-admin-accent-hover); }
        .btn-primary:active { transform: scale(0.98); }

        /* ── Theme toggle ── */
        .theme-toggle {
            position: fixed; top: 1rem; right: 1rem; width: 40px; height: 40px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            background: var(--vb-admin-bg-surface); border: 1px solid var(--vb-admin-border-subtle);
            cursor: pointer; transition: background 150ms;
        }
        .theme-toggle:hover { background: var(--vb-admin-bg-hover); }
        .theme-toggle .icon-sun,
        .theme-toggle .icon-moon { position: absolute; transition: opacity 150ms, transform 150ms; color: var(--vb-admin-text-secondary); }
        .theme-toggle .icon-sun { opacity: 1; transform: rotate(0deg); }
        .theme-toggle .icon-moon { opacity: 0; transform: rotate(-90deg); }
        [data-theme="dark"] .theme-toggle .icon-sun { opacity: 0; transform: rotate(90deg); }
        [data-theme="dark"] .theme-toggle .icon-moon { opacity: 1; transform: rotate(0deg); }
    </style>
</head>
<body>
    <!-- Theme Toggle -->
    <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Toggle theme">
        <!-- Lucide sun -->
        <svg class="icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        <!-- Lucide moon -->
        <svg class="icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>

    <div class="login-container">
        <!-- Hero Logo -->
        <div class="vb-hero-logo">
            <svg class="vb-logo-cube" width="48" height="52" viewBox="0 0 48 52" xmlns="http://www.w3.org/2000/svg">
                <polygon points="24,2 46,14 46,38 24,50 2,38 2,14" fill="none" stroke="var(--vb-admin-accent)" stroke-width="1"/>
                <polygon points="24,2 46,14 24,26 2,14" fill="var(--vb-admin-accent)" opacity="1.0"/>
                <polygon points="2,14 24,26 24,50 2,38" fill="var(--vb-admin-accent)" opacity="0.7"/>
                <polygon points="46,14 24,26 24,50 46,38" fill="var(--vb-admin-accent)" opacity="0.4"/>
            </svg>
            <span class="vb-logo-text">VoxelBooking</span>
        </div>

        <!-- Login Card -->
        <div class="login-card">
            <h2>Sign in</h2>

            <?php if ($error): ?>
                <div class="login-error">
                    <!-- Lucide x-circle -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/admin/login" id="login-form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <div class="form-input-wrapper">
                        <!-- Lucide mail -->
                        <svg class="form-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <input type="email" id="email" name="email" class="form-input" placeholder="operator@example.com" required autocomplete="email" autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="form-input-wrapper">
                        <!-- Lucide lock -->
                        <svg class="form-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required autocomplete="current-password" minlength="8">
                    </div>
                </div>

                <button type="submit" class="btn-primary">Sign in</button>
            </form>
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
