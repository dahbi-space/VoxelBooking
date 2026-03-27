<?php
/**
 * Shared admin head partial: font-face, design tokens, reset, component system.
 *
 * Included in ALL admin surfaces: layout, login, install, error pages.
 * Outputs font-face, tokens, component classes, animation system.
 * Templates consume these classes — they never redefine them.
 *
 * Visual Design §3, §14 (anti-patterns), §15-17 (auth, shell, motion).
 */
?>
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
    /* ══════════════════════════════════════════════════════════════
       DESIGN TOKENS — Visual Design §3
       ══════════════════════════════════════════════════════════════ */
    :root {
        /* ── Surfaces ── */
        --vb-admin-bg-base: #F8FAFC;
        --vb-admin-bg-surface: #FFFFFF;
        --vb-admin-bg-surface-rgb: 255, 255, 255;
        --vb-admin-bg-raised: #FFFFFF;
        --vb-admin-bg-input: #FFFFFF;
        --vb-admin-bg-well: #F1F5F9;
        --vb-admin-bg-hover: #F1F5F9;
        /* ── Borders ── */
        --vb-admin-border-subtle: #E2E8F0;
        --vb-admin-border-medium: #CBD5E1;
        --vb-admin-border-strong: #94A3B8;
        /* ── Text ── */
        --vb-admin-text-primary: #0F172A;
        --vb-admin-text-secondary: #475569;
        --vb-admin-text-tertiary: #94A3B8;
        --vb-admin-text-ghost: #CBD5E1;
        /* ── Accent ── */
        --vb-admin-accent: #4F46E5;
        --vb-admin-accent-hover: #4338CA;
        --vb-admin-accent-dim: rgba(79, 70, 229, 0.08);
        --vb-admin-accent-glow: rgba(79, 70, 229, 0.15);
        /* ── Shadows ── */
        --vb-admin-shadow-sm: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.03);
        --vb-admin-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.03);
        --vb-admin-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.06), 0 4px 6px -4px rgba(0,0,0,0.04);
        --vb-admin-shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.07), 0 8px 10px -6px rgba(0,0,0,0.04);
        --vb-admin-highlight: none;
        /* ── Semantic ── */
        --vb-admin-success: #059669;
        --vb-admin-success-bg: #ECFDF5;
        --vb-admin-warning: #D97706;
        --vb-admin-warning-bg: #FFFBEB;
        --vb-admin-error: #DC2626;
        --vb-admin-error-bg: #FEF2F2;
        --vb-admin-info: #2563EB;
        --vb-admin-info-bg: #EFF6FF;
        /* ── Typography ── */
        --vb-font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        --vb-font-mono: ui-monospace, 'SF Mono', 'Cascadia Mono', 'Segoe UI Mono', monospace;
        --vb-text-2xs: 0.625rem;
        --vb-text-xs: 0.6875rem;
        --vb-text-sm: 0.8125rem;
        --vb-text-base: 0.875rem;
        --vb-text-md: 0.9375rem;
        --vb-text-lg: 1.125rem;
        --vb-text-xl: 1.375rem;
        --vb-text-2xl: 1.5rem;
        --vb-text-3xl: 1.75rem;
        --vb-leading-tight: 1.25;
        --vb-leading-normal: 1.5;
        --vb-leading-relaxed: 1.625;
        --vb-tracking-tight: -0.025em;
        --vb-tracking-normal: -0.011em;
        --vb-tracking-wide: 0.04em;
        /* ── Radii ── */
        --vb-radius-xs: 4px;
        --vb-radius-sm: 6px;
        --vb-radius-md: 8px;
        --vb-radius-lg: 10px;
        --vb-radius: 12px;
        --vb-radius-xl: 14px;
        --vb-radius-2xl: 16px;
        --vb-radius-full: 9999px;
        /* ── Layout ── */
        --vb-sidebar-width: 240px;
        --vb-sidebar-collapsed: 56px;
        --vb-topbar-height: 56px;
        /* ── Timing ── */
        --vb-ease-out: cubic-bezier(0.16, 1, 0.3, 1);
        --vb-ease-in: cubic-bezier(0.4, 0, 1, 1);
        --vb-ease-standard: cubic-bezier(0.4, 0, 0.2, 1);
        --vb-duration-fast: 150ms;
        --vb-duration-normal: 200ms;
        --vb-duration-slow: 300ms;
    }

    [data-theme="dark"] {
        --vb-admin-bg-base: #0F172A;
        --vb-admin-bg-surface: #1E293B;
        --vb-admin-bg-surface-rgb: 30, 41, 59;
        --vb-admin-bg-raised: #334155;
        --vb-admin-bg-input: rgba(30, 41, 59, 0.6);
        --vb-admin-bg-well: #0F172A;
        --vb-admin-bg-hover: rgba(51, 65, 85, 0.6);
        --vb-admin-border-subtle: rgba(51, 65, 85, 0.8);
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
        --vb-admin-shadow-lg: none;
        --vb-admin-shadow-xl: none;
        --vb-admin-highlight: inset 0 1px 0 rgba(255,255,255,0.04);
        --vb-admin-success: #34D399;
        --vb-admin-success-bg: rgba(52, 211, 153, 0.1);
        --vb-admin-warning: #FBBF24;
        --vb-admin-warning-bg: rgba(251, 191, 36, 0.1);
        --vb-admin-error: #F87171;
        --vb-admin-error-bg: rgba(248, 113, 113, 0.1);
        --vb-admin-info: #60A5FA;
        --vb-admin-info-bg: rgba(96, 165, 250, 0.1);
    }

    /* ══════════════════════════════════════════════════════════════
       RESET & BASE
       ══════════════════════════════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: var(--vb-font-sans);
        font-feature-settings: 'cv02' 1, 'cv03' 1, 'cv04' 1, 'cv11' 1;
        color: var(--vb-admin-text-primary);
        line-height: var(--vb-leading-normal);
        letter-spacing: var(--vb-tracking-normal);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    [data-theme="dark"] body { font-weight: 350; }

    /* ══════════════════════════════════════════════════════════════
       ANIMATION SYSTEM — Visual Design §17
       ══════════════════════════════════════════════════════════════ */
    @keyframes vb-fade-in {
        from { opacity: 0; }
        to   { opacity: 1; }
    }
    @keyframes vb-fade-in-up {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes vb-fade-in-down {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes vb-scale-in {
        from { opacity: 0; transform: scale(0.95); }
        to   { opacity: 1; transform: scale(1); }
    }
    @keyframes vb-shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }
    @keyframes vb-shake {
        0%, 100% { transform: translateX(0); }
        20% { transform: translateX(-4px); }
        40% { transform: translateX(4px); }
        60% { transform: translateX(-3px); }
        80% { transform: translateX(3px); }
    }
    @keyframes vb-float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }
    @keyframes vb-spin {
        to { transform: rotate(360deg); }
    }
    @keyframes vb-pulse-glow {
        0%, 100% { box-shadow: 0 0 0 0 var(--vb-admin-accent-glow); }
        50% { box-shadow: 0 0 0 8px transparent; }
    }
    @keyframes vb-gradient-shift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    .vb-fade-in { animation: vb-fade-in var(--vb-duration-normal) var(--vb-ease-out) both; }
    .vb-fade-in-up { animation: vb-fade-in-up var(--vb-duration-slow) var(--vb-ease-out) both; }
    .vb-scale-in { animation: vb-scale-in 250ms var(--vb-ease-out) both; }
    .vb-shake { animation: vb-shake 300ms var(--vb-ease-out); }

    .stagger-1 { animation-delay: 0ms; }
    .stagger-2 { animation-delay: 60ms; }
    .stagger-3 { animation-delay: 120ms; }
    .stagger-4 { animation-delay: 180ms; }
    .stagger-5 { animation-delay: 240ms; }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }

    /* ══════════════════════════════════════════════════════════════
       BUTTON SYSTEM — Visual Design §7 / §6.1
       ══════════════════════════════════════════════════════════════ */
    .vb-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
        height: 36px; padding: 0 0.875rem;
        font-family: var(--vb-font-sans); font-size: var(--vb-text-base); font-weight: 500;
        border: none; border-radius: var(--vb-radius-md); cursor: pointer;
        transition: all var(--vb-duration-fast) var(--vb-ease-out);
        text-decoration: none; white-space: nowrap;
    }
    .vb-btn svg { width: 16px; height: 16px; flex-shrink: 0; }
    .vb-btn:active { transform: scale(0.98); }
    .vb-btn:disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }

    .vb-btn-primary {
        color: #fff; background: var(--vb-admin-accent);
        box-shadow: var(--vb-admin-highlight);
    }
    .vb-btn-primary:hover { background: var(--vb-admin-accent-hover); }

    .vb-btn-lg {
        height: 44px; padding: 0 1.25rem; font-size: var(--vb-text-md); font-weight: 600;
        border-radius: var(--vb-radius-lg);
    }
    .vb-btn-xl {
        height: 48px; padding: 0 1.5rem; font-size: var(--vb-text-md); font-weight: 600;
        border-radius: var(--vb-radius-lg);
    }
    .vb-btn-xl:hover { transform: translateY(-1px); box-shadow: var(--vb-admin-shadow-md); }
    .vb-btn-xl:active { transform: scale(0.98) translateY(0); box-shadow: none; }

    .vb-btn-secondary {
        color: var(--vb-admin-text-secondary); background: transparent;
        border: 1px solid var(--vb-admin-border-subtle);
    }
    .vb-btn-secondary:hover { background: var(--vb-admin-bg-hover); color: var(--vb-admin-text-primary); }

    .vb-btn-ghost {
        color: var(--vb-admin-text-secondary); background: transparent; border: none;
    }
    .vb-btn-ghost:hover { background: var(--vb-admin-bg-hover); color: var(--vb-admin-text-primary); }

    .vb-btn-danger {
        color: var(--vb-admin-error); background: transparent;
        border: 1px solid var(--vb-admin-error);
    }
    .vb-btn-danger:hover { background: var(--vb-admin-error-bg); }

    /* ══════════════════════════════════════════════════════════════
       INPUT SYSTEM — Visual Design §6.2
       ══════════════════════════════════════════════════════════════ */
    .vb-label {
        display: block; font-size: var(--vb-text-sm); font-weight: 500;
        color: var(--vb-admin-text-primary); margin-bottom: 0.375rem;
    }
    .vb-hint {
        font-size: 0.75rem; color: var(--vb-admin-text-tertiary); margin-top: 0.25rem;
    }
    .vb-input, .vb-select {
        display: block; width: 100%; height: 36px; padding: 0 0.75rem;
        font-family: var(--vb-font-sans); font-size: var(--vb-text-base);
        color: var(--vb-admin-text-primary); background: var(--vb-admin-bg-input);
        border: 1px solid var(--vb-admin-border-subtle); border-radius: var(--vb-radius-md);
        outline: none; transition: border-color var(--vb-duration-fast), box-shadow var(--vb-duration-fast);
    }
    .vb-input:hover, .vb-select:hover { border-color: var(--vb-admin-border-medium); }
    .vb-input:focus, .vb-select:focus {
        border-color: var(--vb-admin-accent);
        box-shadow: 0 0 0 3px var(--vb-admin-accent-dim);
    }
    .vb-input::placeholder { color: var(--vb-admin-text-ghost); }
    .vb-input[readonly] { background: var(--vb-admin-bg-well); color: var(--vb-admin-text-tertiary); cursor: default; }

    .vb-input-lg { height: 44px; padding: 0 1rem; font-size: var(--vb-text-md); border-radius: var(--vb-radius-lg); }
    .vb-input-icon { padding-left: 2.75rem; }
    .vb-input-mono { font-family: var(--vb-font-mono); font-size: var(--vb-text-sm); letter-spacing: 0.02em; }

    /* Input wrapper for icon */
    .vb-input-wrap { position: relative; }
    .vb-input-wrap .vb-icon-left {
        position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%);
        width: 18px; height: 18px; color: var(--vb-admin-text-tertiary); pointer-events: none;
    }

    /* ══════════════════════════════════════════════════════════════
       FORM STRUCTURE — Visual Design §6.3
       ══════════════════════════════════════════════════════════════ */
    .vb-form-group { margin-bottom: 1rem; }
    .vb-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 640px) { .vb-form-row { grid-template-columns: 1fr; } }
    .vb-form-actions { margin-top: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }

    /* ══════════════════════════════════════════════════════════════
       CARD SYSTEM — Visual Design §14 (no bordered bootstrap cards)
       ══════════════════════════════════════════════════════════════ */
    .vb-card {
        background: var(--vb-admin-bg-surface);
        border-radius: var(--vb-radius-xl);
        padding: 1.5rem;
        box-shadow: var(--vb-admin-shadow-sm);
        box-decoration-break: clone;
    }
    [data-theme="dark"] .vb-card {
        border: 1px solid var(--vb-admin-border-subtle);
        box-shadow: var(--vb-admin-highlight);
    }
    .vb-card-header { margin-bottom: 1.25rem; }
    .vb-card-title {
        font-size: var(--vb-text-md); font-weight: 600;
        letter-spacing: var(--vb-tracking-tight);
    }
    .vb-card-desc {
        font-size: var(--vb-text-sm); color: var(--vb-admin-text-secondary);
        margin-top: 0.25rem;
    }

    /* ══════════════════════════════════════════════════════════════
       METRIC CARD — Visual Design §6.5
       ══════════════════════════════════════════════════════════════ */
    .vb-metric {
        background: var(--vb-admin-bg-surface);
        border-radius: var(--vb-radius-xl); padding: 1.25rem 1.5rem;
        box-shadow: var(--vb-admin-shadow-sm);
        position: relative; overflow: hidden;
    }
    [data-theme="dark"] .vb-metric {
        border: 1px solid var(--vb-admin-border-subtle);
        box-shadow: var(--vb-admin-highlight);
    }
    .vb-metric-label {
        font-size: var(--vb-text-xs); font-weight: 600; text-transform: uppercase;
        letter-spacing: var(--vb-tracking-wide); color: var(--vb-admin-text-tertiary);
    }
    .vb-metric-value {
        font-size: 1.75rem; font-weight: 600; letter-spacing: var(--vb-tracking-tight);
        font-variant-numeric: tabular-nums; margin-top: 0.25rem;
        color: var(--vb-admin-text-primary);
    }
    .vb-metric-trend {
        display: inline-flex; align-items: center; gap: 0.25rem;
        font-size: var(--vb-text-xs); font-weight: 500; margin-top: 0.375rem;
    }
    .vb-metric-trend svg { width: 14px; height: 14px; }
    .vb-metric-trend.is-up { color: var(--vb-admin-success); }
    .vb-metric-trend.is-down { color: var(--vb-admin-error); }
    .vb-metric-trend.is-flat { color: var(--vb-admin-text-tertiary); }

    /* ══════════════════════════════════════════════════════════════
       EMPTY STATE — Visual Design §6.6
       ══════════════════════════════════════════════════════════════ */
    .vb-empty {
        background: var(--vb-admin-bg-surface);
        border-radius: var(--vb-radius-xl); padding: 2.5rem;
        box-shadow: var(--vb-admin-shadow-sm);
        display: flex; align-items: flex-start; gap: 1.5rem;
    }
    [data-theme="dark"] .vb-empty {
        border: 1px solid var(--vb-admin-border-subtle);
        box-shadow: var(--vb-admin-highlight);
    }
    .vb-empty-icon {
        width: 48px; height: 48px; flex-shrink: 0;
        color: var(--vb-admin-text-ghost); opacity: 0.6;
    }
    .vb-empty-title {
        font-size: var(--vb-text-lg); font-weight: 600;
        letter-spacing: var(--vb-tracking-tight); margin-bottom: 0.375rem;
    }
    .vb-empty-desc {
        font-size: var(--vb-text-sm); color: var(--vb-admin-text-secondary);
        max-width: 400px; margin-bottom: 1.25rem;
        line-height: var(--vb-leading-relaxed);
    }
    .vb-empty-steps {
        display: flex; gap: 1.5rem; margin-top: 0.75rem;
    }
    .vb-empty-step {
        display: flex; align-items: center; gap: 0.5rem;
        font-size: var(--vb-text-xs); color: var(--vb-admin-text-tertiary);
    }
    .vb-empty-step-num {
        width: 20px; height: 20px; border-radius: var(--vb-radius-full);
        background: var(--vb-admin-accent-dim); color: var(--vb-admin-accent);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.625rem; font-weight: 700; flex-shrink: 0;
    }
    .vb-empty-step-arrow {
        color: var(--vb-admin-text-ghost); width: 14px; height: 14px;
    }
    @media (max-width: 640px) {
        .vb-empty { flex-direction: column; text-align: center; align-items: center; }
        .vb-empty-steps { flex-direction: column; gap: 0.75rem; }
    }

    /* ══════════════════════════════════════════════════════════════
       ALERT / FLASH — Visual Design §6.6
       ══════════════════════════════════════════════════════════════ */
    .vb-alert {
        display: flex; align-items: center; gap: 0.625rem;
        padding: 0.75rem 1rem; border-radius: var(--vb-radius-md);
        font-size: var(--vb-text-sm); font-weight: 450;
        border-left: 3px solid transparent;
        animation: vb-fade-in-down var(--vb-duration-normal) var(--vb-ease-out) both;
    }
    .vb-alert svg { width: 16px; height: 16px; flex-shrink: 0; }
    .vb-alert-success {
        background: var(--vb-admin-success-bg); color: var(--vb-admin-success);
        border-left-color: var(--vb-admin-success);
    }
    .vb-alert-error {
        background: var(--vb-admin-error-bg); color: var(--vb-admin-error);
        border-left-color: var(--vb-admin-error);
    }
    .vb-alert-warning {
        background: var(--vb-admin-warning-bg); color: var(--vb-admin-warning);
        border-left-color: var(--vb-admin-warning);
    }
    .vb-alert-info {
        background: var(--vb-admin-info-bg); color: var(--vb-admin-info);
        border-left-color: var(--vb-admin-info);
    }

    /* ══════════════════════════════════════════════════════════════
       SETTINGS SYSTEM — Visual Design §16
       ══════════════════════════════════════════════════════════════ */
    .vb-tabs {
        display: flex; gap: 0.25rem;
        border-bottom: 1px solid var(--vb-admin-border-subtle);
        padding-bottom: 0; overflow-x: auto; margin-bottom: 1.5rem;
    }
    .vb-tab {
        display: flex; align-items: center; gap: 0.375rem;
        padding: 0.5rem 0.75rem;
        font-size: var(--vb-text-sm); font-weight: 450;
        color: var(--vb-admin-text-secondary); text-decoration: none;
        border-bottom: 2px solid transparent;
        transition: color var(--vb-duration-fast), border-color var(--vb-duration-fast);
        white-space: nowrap;
    }
    .vb-tab:hover { color: var(--vb-admin-text-primary); }
    .vb-tab.active {
        color: var(--vb-admin-accent); border-bottom-color: var(--vb-admin-accent);
        font-weight: 500;
    }
    .vb-tab svg { width: 16px; height: 16px; flex-shrink: 0; }

    /* Info rows (system info, account details) */
    .vb-info-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--vb-admin-border-subtle);
    }
    .vb-info-row:last-child { border-bottom: none; }
    .vb-info-label { font-size: var(--vb-text-sm); font-weight: 500; color: var(--vb-admin-text-secondary); }
    .vb-info-value { font-size: var(--vb-text-sm); color: var(--vb-admin-text-primary); }
    .vb-info-value code {
        font-family: var(--vb-font-mono); font-size: var(--vb-text-sm);
        padding: 0.125rem 0.5rem; background: var(--vb-admin-bg-well);
        border-radius: var(--vb-radius-xs);
    }

    /* Log viewer */
    .vb-log-viewer {
        font-family: var(--vb-font-mono); font-size: 0.75rem;
        line-height: 1.6; background: var(--vb-admin-bg-base);
        color: var(--vb-admin-text-secondary);
        padding: 1rem; border-radius: var(--vb-radius-md);
        max-height: 400px; overflow-y: auto;
        white-space: pre-wrap; word-break: break-all;
        border: 1px solid var(--vb-admin-border-subtle);
    }

    /* Password strength bar */
    .vb-pw-track {
        height: 4px; border-radius: 2px; margin-top: 0.375rem;
        background: var(--vb-admin-border-subtle); overflow: hidden;
    }
    .vb-pw-fill {
        height: 100%; width: 0; border-radius: 2px;
        transition: width var(--vb-duration-normal), background var(--vb-duration-normal);
    }

    /* ══════════════════════════════════════════════════════════════
       UTILITY CLASSES
       ══════════════════════════════════════════════════════════════ */
    .vb-nums { font-variant-numeric: tabular-nums; }
    .vb-mono { font-family: var(--vb-font-mono); }
    .vb-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
    .vb-grid { display: grid; gap: 1rem; }
    .vb-grid-2 { grid-template-columns: repeat(2, 1fr); }
    .vb-grid-3 { grid-template-columns: repeat(3, 1fr); }
    .vb-grid-4 { grid-template-columns: repeat(4, 1fr); }
    .vb-grid-auto { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
    @media (max-width: 768px) {
        .vb-grid-2, .vb-grid-3, .vb-grid-4 { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 480px) {
        .vb-grid-2, .vb-grid-3, .vb-grid-4 { grid-template-columns: 1fr; }
    }

    /* Focus visible */
    :focus-visible { outline: 2px solid var(--vb-admin-accent); outline-offset: 2px; }
    :focus:not(:focus-visible) { outline: none; }
    .vb-input:focus-visible, .vb-select:focus-visible { outline: none; }
</style>
<script>
(function(){
    var s=localStorage.getItem('vb-theme');
    if(s==='dark'||(s!=='light'&&window.matchMedia('(prefers-color-scheme:dark)').matches)){
        document.documentElement.setAttribute('data-theme','dark');
    }
    window.matchMedia('(prefers-color-scheme:dark)').addEventListener('change',function(e){
        if(!localStorage.getItem('vb-theme')){
            document.documentElement.setAttribute('data-theme',e.matches?'dark':'light');
        }
    });
})();
</script>
