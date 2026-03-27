<?php
/**
 * Installation Wizard Template — VoxelBooking
 *
 * Uses the official admin design tokens and VoxelBooking logo system.
 * Dark mode via [data-theme]. Lucide icons only. No emoji.
 *
 * Variables: $step (int|'complete'), $checks (array), $errors (array),
 *            $flash (array), $session (array), $csrfToken (string)
 */

$allChecksPassed = empty(array_filter($checks, fn($c) => $c['required'] && !$c['passed']));
$stepTitles = [1 => 'System Check', 2 => 'Database', 3 => 'Email', 4 => 'Account', 5 => 'First Business'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="VoxelBooking Installation Wizard">
    <title>Install — VoxelBooking</title>
    <style>
        /* ── Self-hosted Inter (PRD §II: WOFF2, self-hosted) ── */
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url('/fonts/inter-latin-ext.woff2') format('woff2');
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
        }
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url('/fonts/inter-latin.woff2') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
    </style>
    <style>
        /* ── Admin Design Tokens (PRD §3 / Visual Design §3) ── */
        :root {
            /* Light mode tokens */
            --vb-admin-bg-base: #F8FAFC;
            --vb-admin-bg-surface: #FFFFFF;
            --vb-admin-bg-raised: #FFFFFF;
            --vb-admin-bg-input: #FFFFFF;
            --vb-admin-bg-well: #F1F5F9;
            --vb-admin-bg-hover: #F1F5F9;
            --vb-admin-border-subtle: #E2E8F0;
            --vb-admin-border-medium: #CBD5E1;
            --vb-admin-border-strong: #94A3B8;
            --vb-admin-text-primary: #0F172A;
            --vb-admin-text-secondary: #475569;
            --vb-admin-text-tertiary: #94A3B8;
            --vb-admin-text-ghost: #CBD5E1;
            --vb-admin-accent: #4F46E5;
            --vb-admin-accent-hover: #4338CA;
            --vb-admin-accent-dim: rgba(79, 70, 229, 0.08);
            --vb-admin-accent-glow: rgba(79, 70, 229, 0.15);
            --vb-admin-shadow-sm: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.03);
            --vb-admin-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.03);

            /* Semantic */
            --vb-admin-success: #059669;
            --vb-admin-success-bg: #ECFDF5;
            --vb-admin-warning: #D97706;
            --vb-admin-warning-bg: #FFFBEB;
            --vb-admin-error: #DC2626;
            --vb-admin-error-bg: #FEF2F2;
            --vb-admin-info: #2563EB;
            --vb-admin-info-bg: #EFF6FF;

            /* Typography */
            --vb-font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            --vb-text-sm: 0.8125rem;
            --vb-text-base: 0.875rem;
            --vb-text-md: 0.9375rem;
            --vb-text-lg: 1.125rem;
            --vb-text-xl: 1.375rem;
            --vb-leading-normal: 1.5;
            --vb-tracking-tight: -0.025em;
            --vb-tracking-normal: -0.011em;

            /* Layout */
            --vb-radius: 12px;
            --vb-radius-sm: 8px;
        }

        [data-theme="dark"] {
            --vb-admin-bg-base: #0F172A;
            --vb-admin-bg-surface: #1E293B;
            --vb-admin-bg-raised: #334155;
            --vb-admin-bg-input: rgba(30, 41, 59, 0.6);
            --vb-admin-bg-well: #0F172A;
            --vb-admin-bg-hover: #334155;
            --vb-admin-border-subtle: #334155;
            --vb-admin-border-medium: #475569;
            --vb-admin-border-strong: #64748B;
            --vb-admin-text-primary: #F8FAFC;
            --vb-admin-text-secondary: #94A3B8;
            --vb-admin-text-tertiary: #64748B;
            --vb-admin-text-ghost: #475569;
            --vb-admin-accent: #818CF8;
            --vb-admin-accent-hover: #6366F1;
            --vb-admin-accent-dim: rgba(129, 140, 248, 0.12);
            --vb-admin-accent-glow: rgba(129, 140, 248, 0.3);
            --vb-admin-shadow-sm: none;
            --vb-admin-shadow-md: none;
            --vb-admin-success: #34D399;
            --vb-admin-success-bg: rgba(52, 211, 153, 0.1);
            --vb-admin-warning: #FBBF24;
            --vb-admin-warning-bg: rgba(251, 191, 36, 0.1);
            --vb-admin-error: #F87171;
            --vb-admin-error-bg: rgba(248, 113, 113, 0.1);
            --vb-admin-info: #60A5FA;
            --vb-admin-info-bg: rgba(96, 165, 250, 0.1);
        }

        /* ── Reset ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

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

        [data-theme="dark"] body {
            font-weight: 350;
        }

        /* ── Reduced motion ── */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* ── Layout ── */
        .wizard { width: 100%; max-width: 560px; }

        /* ── Hero Logo (Visual Design §2 — hero variant) ── */
        .vb-hero-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            margin-bottom: 0.5rem;
        }

        .vb-hero-icon {
            width: 56px;
            height: 56px;
            color: var(--vb-admin-accent);
            filter: drop-shadow(0 12px 24px var(--vb-admin-accent-glow));
            animation: voxel-float 4s ease-in-out infinite;
        }

        .voxel-top   { fill: currentColor; opacity: 1; }
        .voxel-left  { fill: currentColor; opacity: 0.7; }
        .voxel-right { fill: currentColor; opacity: 0.4; }

        .vb-hero-text {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        @keyframes voxel-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .wizard-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .wizard-subtitle {
            color: var(--vb-admin-text-secondary);
            font-size: var(--vb-text-md);
            margin-top: 0.25rem;
        }

        /* ── Step Indicator ── */
        .steps {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--vb-admin-border-subtle);
            transition: all 0.3s ease;
        }

        .step-dot.active {
            background: var(--vb-admin-accent);
            transform: scale(1.2);
        }

        .step-dot.done { background: var(--vb-admin-success); }

        /* ── Card ── */
        .card {
            background: var(--vb-admin-bg-surface);
            border: 1px solid var(--vb-admin-border-subtle);
            border-radius: var(--vb-radius);
            padding: 2rem;
            box-shadow: var(--vb-admin-shadow-md);
        }

        [data-theme="dark"] .card {
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }

        .card-title {
            font-size: var(--vb-text-lg);
            font-weight: 600;
            letter-spacing: var(--vb-tracking-tight);
            margin-bottom: 1.5rem;
        }

        /* ── Forms ── */
        .form-group { margin-bottom: 1.25rem; }

        .form-label {
            display: block;
            font-size: var(--vb-text-sm);
            font-weight: 500;
            margin-bottom: 0.375rem;
            color: var(--vb-admin-text-primary);
        }

        .form-input {
            width: 100%;
            padding: 0.5625rem 0.75rem;
            font-size: var(--vb-text-base);
            font-family: var(--vb-font-sans);
            background: var(--vb-admin-bg-input);
            border: 1px solid var(--vb-admin-border-subtle);
            border-radius: var(--vb-radius-sm);
            color: var(--vb-admin-text-primary);
            transition: border-color 0.15s, box-shadow 0.15s;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--vb-admin-accent);
            box-shadow: 0 0 0 3px var(--vb-admin-accent-dim);
        }

        .form-input.error { border-color: var(--vb-admin-error); }

        .form-error {
            font-size: var(--vb-text-sm);
            color: var(--vb-admin-error);
            margin-top: 0.25rem;
        }

        .form-hint {
            font-size: var(--vb-text-sm);
            color: var(--vb-admin-text-tertiary);
            margin-top: 0.25rem;
        }

        select.form-input {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='m6 9 6 6 6-6'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.25rem;
            padding-right: 2.5rem;
        }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5625rem 1.5rem;
            font-size: var(--vb-text-base);
            font-weight: 500;
            font-family: var(--vb-font-sans);
            border: none;
            border-radius: var(--vb-radius-sm);
            cursor: pointer;
            transition: background-color 0.15s, opacity 0.15s;
            text-decoration: none;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--vb-admin-accent);
            color: #FFFFFF;
        }

        .btn-primary:hover { background: var(--vb-admin-accent-hover); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-ghost {
            background: transparent;
            color: var(--vb-admin-text-secondary);
            padding: 0.5rem 1rem;
        }

        .btn-ghost:hover {
            color: var(--vb-admin-text-primary);
            background: var(--vb-admin-bg-well);
        }

        .btn-block { width: 100%; }

        /* ── System Checks ── */
        .check-list { list-style: none; }

        .check-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 0;
            border-bottom: 1px solid var(--vb-admin-border-subtle);
            font-size: var(--vb-text-base);
            opacity: 0;
            animation: fadeIn 0.3s ease forwards;
        }

        .check-item:last-child { border-bottom: none; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .check-icon { width: 20px; height: 20px; flex-shrink: 0; }
        .check-icon.pass { color: var(--vb-admin-success); }
        .check-icon.fail { color: var(--vb-admin-error); }

        .check-name { flex: 1; font-weight: 400; }
        .check-status { font-size: var(--vb-text-sm); color: var(--vb-admin-text-tertiary); }

        /* ── Flash Messages ── */
        .flash {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.75rem 1rem;
            border-radius: var(--vb-radius-sm);
            margin-bottom: 1rem;
            font-size: var(--vb-text-sm);
            font-weight: 500;
        }

        .flash-success { background: var(--vb-admin-success-bg); color: var(--vb-admin-success); }
        .flash-error { background: var(--vb-admin-error-bg); color: var(--vb-admin-error); }
        .flash-info { background: var(--vb-admin-info-bg); color: var(--vb-admin-info); }
        .flash-warning { background: var(--vb-admin-warning-bg); color: var(--vb-admin-warning); }

        .flash-icon { width: 18px; height: 18px; flex-shrink: 0; }

        /* ── Connection Error ── */
        .connection-error {
            background: var(--vb-admin-error-bg);
            border: 1px solid var(--vb-admin-error);
            border-radius: var(--vb-radius-sm);
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            color: var(--vb-admin-error);
            font-size: var(--vb-text-sm);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ── Password Strength ── */
        .password-strength {
            height: 4px;
            border-radius: 2px;
            background: var(--vb-admin-border-subtle);
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease, background-color 0.3s ease;
            width: 0;
        }

        /* ── Theme Toggle (PRD: sun/moon cross-fade 150ms) ── */
        .theme-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: var(--vb-admin-bg-surface);
            border: 1px solid var(--vb-admin-border-subtle);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--vb-admin-shadow-sm);
            transition: box-shadow 0.15s, border-color 0.15s;
            z-index: 10;
            padding: 0;
            color: var(--vb-admin-text-secondary);
        }

        .theme-toggle:hover {
            border-color: var(--vb-admin-border-medium);
            color: var(--vb-admin-text-primary);
        }

        .theme-toggle svg {
            width: 20px;
            height: 20px;
            position: absolute;
            transition: opacity 150ms ease, transform 150ms ease;
        }

        .theme-toggle .icon-sun { opacity: 0; transform: rotate(-90deg); }
        .theme-toggle .icon-moon { opacity: 1; transform: rotate(0deg); }

        [data-theme="dark"] .theme-toggle .icon-sun { opacity: 1; transform: rotate(0deg); }
        [data-theme="dark"] .theme-toggle .icon-moon { opacity: 0; transform: rotate(90deg); }

        /* ── Booking Pattern Cards (PRD §1362) ── */
        .pattern-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .pattern-card {
            border: 2px solid var(--vb-admin-border-subtle);
            border-radius: var(--vb-radius-sm);
            padding: 1rem;
            cursor: pointer;
            transition: border-color 0.15s, background-color 0.15s;
            text-align: center;
        }

        .pattern-card:hover {
            border-color: var(--vb-admin-border-medium);
            background: var(--vb-admin-bg-well);
        }

        .pattern-card.selected {
            border-color: var(--vb-admin-accent);
            background: var(--vb-admin-accent-dim);
        }

        .pattern-card input { display: none; }

        .pattern-card-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
            color: var(--vb-admin-accent);
        }

        .pattern-card-icon svg { width: 28px; height: 28px; }

        .pattern-card-name {
            font-weight: 600;
            font-size: var(--vb-text-sm);
            display: block;
            margin-bottom: 0.125rem;
        }

        .pattern-card-desc {
            font-size: 0.6875rem;
            color: var(--vb-admin-text-tertiary);
            display: block;
            line-height: 1.3;
        }

        /* ── Color Input ── */
        .color-input-group {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .color-swatch {
            width: 40px;
            height: 40px;
            border-radius: var(--vb-radius-sm);
            border: 2px solid var(--vb-admin-border-subtle);
            cursor: pointer;
            padding: 0;
        }

        /* ── Completion Screen ── */
        .completion {
            text-align: center;
            padding: 2rem 1rem;
        }

        .completion-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.5rem;
            color: var(--vb-admin-success);
        }

        .completion h2 {
            font-size: var(--vb-text-xl);
            font-weight: 700;
            letter-spacing: var(--vb-tracking-tight);
            margin-bottom: 0.5rem;
        }

        .completion p {
            color: var(--vb-admin-text-secondary);
            margin-bottom: 1.5rem;
            font-size: var(--vb-text-md);
        }

        .booking-url {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--vb-admin-bg-well);
            padding: 0.75rem 1rem;
            border-radius: var(--vb-radius-sm);
            margin-bottom: 1.5rem;
            font-size: var(--vb-text-sm);
            word-break: break-all;
        }

        .booking-url a {
            color: var(--vb-admin-accent);
            text-decoration: none;
            flex: 1;
        }

        .booking-url a:hover { text-decoration: underline; }

        .copy-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--vb-admin-text-tertiary);
            padding: 0.25rem;
            display: flex;
        }

        .copy-btn:hover { color: var(--vb-admin-text-primary); }
        .copy-btn svg { width: 16px; height: 16px; }

        /* ── Form Row ── */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 480px) {
            .form-row { grid-template-columns: 1fr; }
            .pattern-cards { grid-template-columns: 1fr; }
        }

        .skip-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--vb-admin-text-tertiary);
            font-size: var(--vb-text-sm);
            text-decoration: none;
            cursor: pointer;
            background: none;
            border: none;
            font-family: var(--vb-font-sans);
            width: 100%;
            transition: color 0.15s;
        }

        .skip-link:hover { color: var(--vb-admin-text-primary); }

        .actions { margin-top: 1.5rem; }
    </style>
</head>
<body>
    <!-- Theme Toggle (PRD: sun/moon cross-fade 150ms, Lucide icons) -->
    <button class="theme-toggle" id="vb-theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">
        <!-- Lucide Sun: viewBox 0 0 24 24, 1.5px stroke, round caps -->
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
        </svg>
        <!-- Lucide Moon -->
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
        </svg>
    </button>

    <div class="wizard">
        <div class="wizard-header">
            <!-- Official VoxelBooking Hero Logo (Visual Design §2 — hero variant) -->
            <div class="vb-hero-logo">
                <svg class="vb-hero-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path class="voxel-top"   d="M12 3L20 7.5L12 12L4 7.5Z" />
                    <path class="voxel-left"  d="M4 7.5L12 12L12 21L4 16.5Z" />
                    <path class="voxel-right" d="M20 7.5L12 12L12 21L20 16.5Z" />
                </svg>
                <div class="vb-hero-text">VoxelBooking</div>
            </div>

            <?php if ($step === 'complete'): ?>
                <p class="wizard-subtitle">Installation complete.</p>
            <?php else: ?>
                <p class="wizard-subtitle"><?= htmlspecialchars($stepTitles[$step] ?? '', ENT_QUOTES, 'UTF-8') ?> — Step <?= $step ?> of 5</p>
            <?php endif; ?>
        </div>

        <?php if ($step !== 'complete'): ?>
        <div class="steps">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <div class="step-dot <?= $i === $step ? 'active' : ($i < $step ? 'done' : '') ?>"></div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <!-- Flash messages -->
        <?php foreach ($flash as $msg): ?>
            <div class="flash flash-<?= htmlspecialchars($msg['type'], ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($msg['type'] === 'success'): ?>
                    <svg class="flash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                <?php elseif ($msg['type'] === 'error'): ?>
                    <svg class="flash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                <?php elseif ($msg['type'] === 'info'): ?>
                    <svg class="flash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                <?php else: ?>
                    <svg class="flash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                <?php endif; ?>
                <?= htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endforeach; ?>

        <div class="card">

            <?php if ($step === 1): ?>
            <!-- ═══ Step 1: System Requirements ═══ -->
            <h2 class="card-title">System Requirements</h2>

            <ul class="check-list">
                <?php foreach ($checks as $index => $check): ?>
                <li class="check-item" style="animation-delay: <?= $index * 150 ?>ms">
                    <!-- Lucide check-circle / x-circle -->
                    <svg class="check-icon <?= $check['passed'] ? 'pass' : 'fail' ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <?php if ($check['passed']): ?>
                            <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
                        <?php else: ?>
                            <circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>
                        <?php endif; ?>
                    </svg>
                    <span class="check-name"><?= htmlspecialchars($check['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="check-status"><?= htmlspecialchars($check['message'], ENT_QUOTES, 'UTF-8') ?></span>
                </li>
                <?php endforeach; ?>
            </ul>

            <div class="actions">
                <a href="/install?step=2" class="btn btn-primary btn-block"
                   <?= !$allChecksPassed ? 'style="pointer-events:none;opacity:0.5"' : '' ?>>
                    Continue
                </a>
            </div>

            <?php elseif ($step === 2): ?>
            <!-- ═══ Step 2: Database Configuration ═══ -->
            <h2 class="card-title">Database Configuration</h2>

            <?php if (!empty($errors['db_connection'])): ?>
                <div class="connection-error">
                    <svg class="flash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                    <?= htmlspecialchars($errors['db_connection'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['db_migration'])): ?>
                <div class="connection-error">
                    <svg class="flash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                    <?= htmlspecialchars($errors['db_migration'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/install/step/2">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="db_host">MySQL Host</label>
                        <input type="text" id="db_host" name="db_host" class="form-input <?= isset($errors['db_host']) ? 'error' : '' ?>" value="localhost" required>
                        <?php if (isset($errors['db_host'])): ?>
                            <div class="form-error"><?= htmlspecialchars($errors['db_host'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="db_port">Port</label>
                        <input type="text" id="db_port" name="db_port" class="form-input <?= isset($errors['db_port']) ? 'error' : '' ?>" value="3306" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="db_database">Database Name</label>
                    <input type="text" id="db_database" name="db_database" class="form-input <?= isset($errors['db_database']) ? 'error' : '' ?>" value="voxelbooking" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="db_username">Username</label>
                        <input type="text" id="db_username" name="db_username" class="form-input" value="root" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="db_password">Password</label>
                        <input type="password" id="db_password" name="db_password" class="form-input" value="">
                        <div class="form-hint">Leave empty if none required.</div>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary btn-block">Test Connection &amp; Continue</button>
                </div>
            </form>

            <?php elseif ($step === 3): ?>
            <!-- ═══ Step 3: Email Configuration ═══ -->
            <h2 class="card-title">Email Configuration</h2>

            <form method="POST" action="/install/step/3">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="mail_host">SMTP Host</label>
                        <input type="text" id="mail_host" name="mail_host" class="form-input <?= isset($errors['mail_host']) ? 'error' : '' ?>" placeholder="smtp.example.com">
                        <?php if (isset($errors['mail_host'])): ?>
                            <div class="form-error"><?= htmlspecialchars($errors['mail_host'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="mail_port">Port</label>
                        <input type="text" id="mail_port" name="mail_port" class="form-input" value="587">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="mail_username">Username</label>
                        <input type="text" id="mail_username" name="mail_username" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="mail_password">Password</label>
                        <input type="password" id="mail_password" name="mail_password" class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="mail_encryption">Encryption</label>
                    <select id="mail_encryption" name="mail_encryption" class="form-input">
                        <option value="tls" selected>TLS</option>
                        <option value="ssl">SSL</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="mail_from_address">From Address</label>
                        <input type="email" id="mail_from_address" name="mail_from_address" class="form-input" placeholder="bookings@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="mail_from_name">From Name</label>
                        <input type="text" id="mail_from_name" name="mail_from_name" class="form-input" value="VoxelBooking">
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary btn-block">Save &amp; Continue</button>
                </div>
            </form>

            <form method="POST" action="/install/step/3">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="skip" value="1">
                <button type="submit" class="skip-link">I'll configure this later</button>
            </form>

            <?php elseif ($step === 4): ?>
            <!-- ═══ Step 4: Operator Account ═══ -->
            <h2 class="card-title">Create Your Account</h2>

            <form method="POST" action="/install/step/4">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-input <?= isset($errors['name']) ? 'error' : '' ?>" required autocomplete="name">
                    <?php if (isset($errors['name'])): ?><div class="form-error"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-input <?= isset($errors['email']) ? 'error' : '' ?>" required autocomplete="email">
                    <?php if (isset($errors['email'])): ?><div class="form-error"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input <?= isset($errors['password']) ? 'error' : '' ?>" required minlength="8" autocomplete="new-password">
                    <?php if (isset($errors['password'])): ?><div class="form-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    <div class="password-strength"><div class="password-strength-bar" id="strength-bar"></div></div>
                    <div class="form-hint">Minimum 8 characters.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-input <?= isset($errors['password_confirmation']) ? 'error' : '' ?>" required autocomplete="new-password">
                    <?php if (isset($errors['password_confirmation'])): ?><div class="form-error"><?= htmlspecialchars($errors['password_confirmation'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary btn-block">Create Account &amp; Continue</button>
                </div>
            </form>

            <?php elseif ($step === 5): ?>
            <!-- ═══ Step 5: First Tenant ═══ -->
            <h2 class="card-title">Create Your First Business</h2>

            <form method="POST" action="/install/step/5">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label class="form-label" for="tenant_name">Business Name</label>
                    <input type="text" id="tenant_name" name="name" class="form-input <?= isset($errors['name']) ? 'error' : '' ?>" required placeholder="e.g. Salon Bella">
                    <?php if (isset($errors['name'])): ?><div class="form-error"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Booking Pattern</label>
                    <div class="pattern-cards">
                        <!-- Clock icon: Time Slots (PRD §1362) -->
                        <label class="pattern-card selected">
                            <input type="radio" name="booking_pattern" value="timeslot" checked>
                            <span class="pattern-card-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </span>
                            <span class="pattern-card-name">Time Slots</span>
                            <span class="pattern-card-desc">Salon, therapist, tutor</span>
                        </label>
                        <!-- Bed-double icon: Resources -->
                        <label class="pattern-card">
                            <input type="radio" name="booking_pattern" value="resource">
                            <span class="pattern-card-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8"/><path d="M4 10V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/><path d="M12 4v6"/><path d="M2 18h20"/></svg>
                            </span>
                            <span class="pattern-card-name">Resources</span>
                            <span class="pattern-card-desc">B&amp;B, hotel, meeting room</span>
                        </label>
                        <!-- Utensils icon: Capacity -->
                        <label class="pattern-card">
                            <input type="radio" name="booking_pattern" value="capacity">
                            <span class="pattern-card-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>
                            </span>
                            <span class="pattern-card-name">Capacity</span>
                            <span class="pattern-card-desc">Restaurant, escape room</span>
                        </label>
                        <!-- Ticket icon: Events -->
                        <label class="pattern-card">
                            <input type="radio" name="booking_pattern" value="event">
                            <span class="pattern-card-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>
                            </span>
                            <span class="pattern-card-name">Events</span>
                            <span class="pattern-card-desc">Yoga, cooking class</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="tenant_email">Business Email</label>
                    <input type="email" id="tenant_email" name="email" class="form-input" required value="<?= htmlspecialchars($session['operator_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="brand_color">Brand Color</label>
                    <div class="color-input-group">
                        <input type="color" id="brand_color_picker" class="color-swatch" value="#2563EB">
                        <input type="text" id="brand_color" name="brand_color" class="form-input" value="#2563EB" maxlength="7">
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary btn-block">Create Business &amp; Finish</button>
                </div>
            </form>

            <form method="POST" action="/install/step/5">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="skip" value="1">
                <button type="submit" class="skip-link">I'll do this from the dashboard</button>
            </form>

            <?php elseif ($step === 'complete'): ?>
            <!-- ═══ Completion ═══ -->
            <div class="completion">
                <!-- Lucide check-circle -->
                <svg class="completion-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
                </svg>

                <h2>Installation Complete</h2>
                <p>VoxelBooking is ready to accept bookings.</p>

                <?php if (!empty($session['tenant_slug'])): ?>
                <div class="booking-url">
                    <?php $bookingUrl = ($_ENV['APP_URL'] ?? '') . '/book/' . ($session['tenant_slug'] ?? ''); ?>
                    <a href="<?= htmlspecialchars($bookingUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                        <?= htmlspecialchars($bookingUrl, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <button class="copy-btn" id="vb-copy-url" title="Copy URL">
                        <!-- Lucide copy -->
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                    </button>
                </div>
                <?php endif; ?>

                <a href="/admin" class="btn btn-primary btn-block">Go to Dashboard</a>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
    (function() {
        'use strict';

        // ── Theme Resolution (PRD §2236) ──
        // 1. localStorage `vb-theme`
        // 2. prefers-color-scheme
        // 3. fallback: light

        var STORAGE_KEY = 'vb-theme';

        function getResolvedTheme() {
            var stored = localStorage.getItem(STORAGE_KEY);
            if (stored === 'light' || stored === 'dark') return stored;
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) return 'dark';
            return 'light';
        }

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
        }

        // Apply immediately (before paint)
        applyTheme(getResolvedTheme());

        // OS-level theme change listener — updates live when user hasn't manually overridden
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        mq.addEventListener('change', function(e) {
            // Only follow OS if user hasn't manually set a preference
            if (!localStorage.getItem(STORAGE_KEY)) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });

        // Theme toggle button (no inline onclick — CSP compliant)
        var toggleBtn = document.getElementById('vb-theme-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                var current = document.documentElement.getAttribute('data-theme');
                var next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem(STORAGE_KEY, next);
            });
        }

        // ── Password Strength (PRD: 4px bar, animated width, red→amber→green) ──
        var passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                var bar = document.getElementById('strength-bar');
                if (!bar) return;
                var password = this.value;
                var score = 0;
                if (password.length >= 8) score++;
                if (password.length >= 12) score++;
                if (/[A-Z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;

                var width = Math.min(100, score * 20);
                var colors = ['#DC2626', '#D97706', '#D97706', '#059669', '#059669'];
                bar.style.width = width + '%';
                bar.style.backgroundColor = colors[Math.min(score, colors.length) - 1] || '#DC2626';
            });
        }

        // ── Pattern Card Selection (no inline onclick) ──
        document.querySelectorAll('.pattern-card').forEach(function(card) {
            card.addEventListener('click', function() {
                document.querySelectorAll('.pattern-card').forEach(function(c) { c.classList.remove('selected'); });
                this.classList.add('selected');
            });
        });

        // ── Color Picker Sync ──
        var colorPicker = document.getElementById('brand_color_picker');
        var colorText = document.getElementById('brand_color');
        if (colorPicker && colorText) {
            colorPicker.addEventListener('change', function() { colorText.value = this.value; });
            colorText.addEventListener('change', function() { colorPicker.value = this.value; });
        }

        // ── Copy URL ──
        var copyBtn = document.getElementById('vb-copy-url');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                var link = document.querySelector('.booking-url a');
                if (link) navigator.clipboard.writeText(link.href);
            });
        }
    })();
    </script>
</body>
</html>
