<?php
/**
 * Shared admin head partial: font-face, design tokens, reset, component system.
 *
 * Included in ALL admin surfaces: layout, login, install, error pages.
 * Outputs font-face, tokens, component classes, animation system.
 *
 * Design philosophy: Linear/Vercel/Stripe-grade.
 * Luminous surfaces. No harsh borders. Gradient depth. Spring motion.
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
       DESIGN TOKENS — Premium Design System
       Inspired by Linear, Vercel, Raycast, Stripe
       ══════════════════════════════════════════════════════════════ */
    :root {
        /* ── Surfaces: layered depth, not flat planes ── */
        --vb-bg-base: #f5f5f7;
        --vb-bg-surface: #ffffff;
        --vb-bg-surface-rgb: 255, 255, 255;
        --vb-bg-raised: #ffffff;
        --vb-bg-input: #ffffff;
        --vb-bg-well: #f0f0f2;
        --vb-bg-hover: rgba(0, 0, 0, 0.03);
        --vb-bg-active: rgba(0, 0, 0, 0.05);
        /* ── Borders: barely visible, luminous ── */
        --vb-border-subtle: rgba(0, 0, 0, 0.06);
        --vb-border-default: rgba(0, 0, 0, 0.1);
        --vb-border-strong: rgba(0, 0, 0, 0.16);
        --vb-border-card: rgba(0, 0, 0, 0.04);
        /* ── Text: optical weight hierarchy ── */
        --vb-text-primary: #1a1a2e;
        --vb-text-secondary: #6b6b80;
        --vb-text-tertiary: #9999ad;
        --vb-text-ghost: #c4c4d4;
        --vb-text-inverted: #ffffff;
        /* ── Accent: rich indigo with luminous glow ── */
        --vb-accent: #6366f1;
        --vb-accent-hover: #5457e5;
        --vb-accent-active: #4f46e5;
        --vb-accent-subtle: rgba(99, 102, 241, 0.08);
        --vb-accent-glow: rgba(99, 102, 241, 0.2);
        --vb-accent-gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
        /* ── Depth: multi-layer elevation system ── */
        --vb-shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.04);
        --vb-shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
        --vb-shadow-md: 0 4px 12px rgba(0, 0, 0, 0.06), 0 1px 3px rgba(0, 0, 0, 0.04);
        --vb-shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.04);
        --vb-shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.1), 0 4px 12px rgba(0, 0, 0, 0.05);
        --vb-shadow-glow: 0 0 0 1px rgba(99, 102, 241, 0.15), 0 0 20px rgba(99, 102, 241, 0.1);
        --vb-shadow-card: 0 0 0 1px var(--vb-border-card), var(--vb-shadow-xs);
        --vb-shadow-card-hover: 0 0 0 1px var(--vb-border-default), var(--vb-shadow-md);
        --vb-inner-glow: inset 0 1px 0 rgba(255, 255, 255, 0.5);
        /* ── Semantic ── */
        --vb-success: #10b981;
        --vb-success-bg: rgba(16, 185, 129, 0.08);
        --vb-warning: #f59e0b;
        --vb-warning-bg: rgba(245, 158, 11, 0.08);
        --vb-error: #ef4444;
        --vb-error-bg: rgba(239, 68, 68, 0.06);
        --vb-info: #3b82f6;
        --vb-info-bg: rgba(59, 130, 246, 0.08);
        /* ── Typography: optical precision ── */
        --vb-font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        --vb-font-mono: 'SF Mono', 'Cascadia Code', 'Fira Code', ui-monospace, monospace;
        --vb-text-2xs: 0.625rem;
        --vb-text-xs: 0.6875rem;
        --vb-text-sm: 0.8125rem;
        --vb-text-base: 0.875rem;
        --vb-text-md: 0.9375rem;
        --vb-text-lg: 1.0625rem;
        --vb-text-xl: 1.25rem;
        --vb-text-2xl: 1.5rem;
        --vb-text-3xl: 1.875rem;
        --vb-text-4xl: 2.25rem;
        --vb-leading-tight: 1.2;
        --vb-leading-snug: 1.35;
        --vb-leading-normal: 1.5;
        --vb-leading-relaxed: 1.65;
        --vb-tracking-tighter: -0.04em;
        --vb-tracking-tight: -0.025em;
        --vb-tracking-normal: -0.011em;
        --vb-tracking-wide: 0.05em;
        /* ── Radii ── */
        --vb-radius-xs: 4px;
        --vb-radius-sm: 6px;
        --vb-radius-md: 8px;
        --vb-radius-lg: 10px;
        --vb-radius: 12px;
        --vb-radius-xl: 14px;
        --vb-radius-2xl: 16px;
        --vb-radius-3xl: 20px;
        --vb-radius-full: 9999px;
        /* ── Layout ── */
        --vb-sidebar-width: 248px;
        --vb-sidebar-collapsed: 56px;
        --vb-topbar-height: 52px;
        /* ── Motion: spring-inspired curves ── */
        --vb-ease-out: cubic-bezier(0.16, 1, 0.3, 1);
        --vb-ease-in: cubic-bezier(0.4, 0, 1, 1);
        --vb-ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
        --vb-ease-smooth: cubic-bezier(0.25, 0.1, 0.25, 1);
        --vb-duration-instant: 100ms;
        --vb-duration-fast: 150ms;
        --vb-duration-normal: 220ms;
        --vb-duration-slow: 320ms;
        --vb-duration-slower: 500ms;
    }

    /* ── Dark: luminous depth on dark surfaces ── */
    [data-theme="dark"] {
        --vb-bg-base: #0c0c14;
        --vb-bg-surface: #151520;
        --vb-bg-surface-rgb: 21, 21, 32;
        --vb-bg-raised: #1c1c2e;
        --vb-bg-input: rgba(28, 28, 46, 0.8);
        --vb-bg-well: #0c0c14;
        --vb-bg-hover: rgba(255, 255, 255, 0.04);
        --vb-bg-active: rgba(255, 255, 255, 0.07);
        --vb-border-subtle: rgba(255, 255, 255, 0.06);
        --vb-border-default: rgba(255, 255, 255, 0.1);
        --vb-border-strong: rgba(255, 255, 255, 0.16);
        --vb-border-card: rgba(255, 255, 255, 0.05);
        --vb-text-primary: #ededf0;
        --vb-text-secondary: #8888a0;
        --vb-text-tertiary: #5c5c72;
        --vb-text-ghost: #3a3a50;
        --vb-accent: #818cf8;
        --vb-accent-hover: #9198fa;
        --vb-accent-active: #a5aefb;
        --vb-accent-subtle: rgba(129, 140, 248, 0.1);
        --vb-accent-glow: rgba(129, 140, 248, 0.25);
        --vb-accent-gradient: linear-gradient(135deg, #818cf8, #a78bfa);
        --vb-shadow-xs: none;
        --vb-shadow-sm: none;
        --vb-shadow-md: 0 4px 24px rgba(0, 0, 0, 0.3);
        --vb-shadow-lg: 0 8px 40px rgba(0, 0, 0, 0.4);
        --vb-shadow-xl: 0 16px 64px rgba(0, 0, 0, 0.5);
        --vb-shadow-glow: 0 0 0 1px rgba(129, 140, 248, 0.2), 0 0 24px rgba(129, 140, 248, 0.08);
        --vb-shadow-card: 0 0 0 1px var(--vb-border-card), inset 0 1px 0 rgba(255, 255, 255, 0.02);
        --vb-shadow-card-hover: 0 0 0 1px var(--vb-border-default), 0 4px 24px rgba(0, 0, 0, 0.2);
        --vb-inner-glow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
        --vb-success: #34d399;
        --vb-success-bg: rgba(52, 211, 153, 0.08);
        --vb-warning: #fbbf24;
        --vb-warning-bg: rgba(251, 191, 36, 0.08);
        --vb-error: #f87171;
        --vb-error-bg: rgba(248, 113, 113, 0.06);
        --vb-info: #60a5fa;
        --vb-info-bg: rgba(96, 165, 250, 0.08);
    }

    /* ── Token aliases (backward compat for existing pages) ── */
    :root, [data-theme="dark"] {
        --vb-admin-bg-base: var(--vb-bg-base);
        --vb-admin-bg-surface: var(--vb-bg-surface);
        --vb-admin-bg-surface-rgb: var(--vb-bg-surface-rgb);
        --vb-admin-bg-raised: var(--vb-bg-raised);
        --vb-admin-bg-input: var(--vb-bg-input);
        --vb-admin-bg-well: var(--vb-bg-well);
        --vb-admin-bg-hover: var(--vb-bg-hover);
        --vb-admin-border-subtle: var(--vb-border-subtle);
        --vb-admin-border-medium: var(--vb-border-default);
        --vb-admin-border-strong: var(--vb-border-strong);
        --vb-admin-text-primary: var(--vb-text-primary);
        --vb-admin-text-secondary: var(--vb-text-secondary);
        --vb-admin-text-tertiary: var(--vb-text-tertiary);
        --vb-admin-text-ghost: var(--vb-text-ghost);
        --vb-admin-accent: var(--vb-accent);
        --vb-admin-accent-hover: var(--vb-accent-hover);
        --vb-admin-accent-dim: var(--vb-accent-subtle);
        --vb-admin-accent-glow: var(--vb-accent-glow);
        --vb-admin-shadow-sm: var(--vb-shadow-sm);
        --vb-admin-shadow-md: var(--vb-shadow-md);
        --vb-admin-shadow-lg: var(--vb-shadow-lg);
        --vb-admin-shadow-xl: var(--vb-shadow-xl);
        --vb-admin-highlight: var(--vb-inner-glow);
        --vb-admin-success: var(--vb-success);
        --vb-admin-success-bg: var(--vb-success-bg);
        --vb-admin-warning: var(--vb-warning);
        --vb-admin-warning-bg: var(--vb-warning-bg);
        --vb-admin-error: var(--vb-error);
        --vb-admin-error-bg: var(--vb-error-bg);
        --vb-admin-info: var(--vb-info);
        --vb-admin-info-bg: var(--vb-info-bg);
    }

    /* ══════════════════════════════════════════════════════════════
       RESET & BASE
       ══════════════════════════════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html { height: 100%; }

    body {
        font-family: var(--vb-font-sans);
        font-feature-settings: 'cv02' 1, 'cv03' 1, 'cv04' 1, 'cv11' 1;
        color: var(--vb-text-primary);
        line-height: var(--vb-leading-normal);
        letter-spacing: var(--vb-tracking-normal);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
    }

    [data-theme="dark"] body { font-weight: 350; }

    /* ══════════════════════════════════════════════════════════════
       ANIMATION SYSTEM — Spring-physics inspired
       ══════════════════════════════════════════════════════════════ */
    @keyframes vb-fade-in {
        from { opacity: 0; }
        to   { opacity: 1; }
    }
    @keyframes vb-fade-in-up {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes vb-fade-in-down {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes vb-scale-in {
        from { opacity: 0; transform: scale(0.96); }
        to   { opacity: 1; transform: scale(1); }
    }
    @keyframes vb-shimmer {
        0%   { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }
    @keyframes vb-pulse-ring {
        0%   { box-shadow: 0 0 0 0 var(--vb-accent-glow); }
        70%  { box-shadow: 0 0 0 6px transparent; }
        100% { box-shadow: 0 0 0 0 transparent; }
    }
    @keyframes vb-gradient-shift {
        0%   { background-position: 0% 50%; }
        50%  { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    @keyframes vb-spin { to { transform: rotate(360deg); } }
    @keyframes vb-float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-4px); }
    }
    @keyframes vb-breathe {
        0%, 100% { opacity: 0.4; }
        50% { opacity: 0.8; }
    }

    .vb-animate-in {
        opacity: 0; transform: translateY(6px);
        animation: vb-fade-in-up var(--vb-duration-slow) var(--vb-ease-out) forwards;
    }
    .vb-scale-in { animation: vb-scale-in 250ms var(--vb-ease-spring) both; }

    .stagger-1 { animation-delay: 0ms; }
    .stagger-2 { animation-delay: 60ms; }
    .stagger-3 { animation-delay: 120ms; }
    .stagger-4 { animation-delay: 180ms; }
    .stagger-5 { animation-delay: 260ms; }
    .stagger-6 { animation-delay: 340ms; }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }

    /* ══════════════════════════════════════════════════════════════
       BUTTON SYSTEM — Premium tactile buttons
       ══════════════════════════════════════════════════════════════ */
    .vb-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 0.4375rem;
        height: 36px; padding: 0 0.875rem;
        font-family: var(--vb-font-sans); font-size: var(--vb-text-sm); font-weight: 500;
        border: none; border-radius: var(--vb-radius-md); cursor: pointer;
        transition: all var(--vb-duration-fast) var(--vb-ease-out);
        text-decoration: none; white-space: nowrap;
        position: relative; overflow: hidden;
    }
    .vb-btn svg { width: 15px; height: 15px; flex-shrink: 0; }
    .vb-btn:active { transform: scale(0.97); transition-duration: 80ms; }
    .vb-btn:disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }

    /* Primary: gradient with inner glow */
    .vb-btn-primary {
        color: #fff;
        background: var(--vb-accent-gradient);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12), var(--vb-inner-glow),
                    inset 0 -1px 0 rgba(0, 0, 0, 0.1);
    }
    .vb-btn-primary:hover {
        box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3), var(--vb-inner-glow),
                    inset 0 -1px 0 rgba(0, 0, 0, 0.1);
        transform: translateY(-1px);
    }
    .vb-btn-primary:active { transform: translateY(0) scale(0.98); }

    /* Secondary: glass border */
    .vb-btn-secondary {
        color: var(--vb-text-secondary);
        background: var(--vb-bg-surface);
        box-shadow: var(--vb-shadow-card);
    }
    .vb-btn-secondary:hover {
        color: var(--vb-text-primary);
        box-shadow: var(--vb-shadow-card-hover);
    }

    /* Ghost: invisible until hovered */
    .vb-btn-ghost {
        color: var(--vb-text-secondary); background: transparent; border: none;
    }
    .vb-btn-ghost:hover { background: var(--vb-bg-hover); color: var(--vb-text-primary); }

    /* Danger */
    .vb-btn-danger {
        color: var(--vb-error); background: transparent;
        box-shadow: inset 0 0 0 1px var(--vb-error);
    }
    .vb-btn-danger:hover { background: var(--vb-error-bg); }

    /* Sizes */
    .vb-btn-sm { height: 30px; padding: 0 0.625rem; font-size: var(--vb-text-xs); border-radius: var(--vb-radius-sm); }
    .vb-btn-lg {
        height: 42px; padding: 0 1.25rem; font-size: var(--vb-text-base); font-weight: 600;
        border-radius: var(--vb-radius-lg);
    }
    .vb-btn-xl {
        height: 48px; padding: 0 1.5rem; font-size: var(--vb-text-md); font-weight: 600;
        border-radius: var(--vb-radius);
    }

    /* ══════════════════════════════════════════════════════════════
       INPUT SYSTEM — Refined form controls
       ══════════════════════════════════════════════════════════════ */
    .vb-label {
        display: block; font-size: var(--vb-text-sm); font-weight: 500;
        color: var(--vb-text-primary); margin-bottom: 0.375rem;
    }
    .vb-hint { font-size: 0.75rem; color: var(--vb-text-tertiary); margin-top: 0.3125rem; }

    .vb-input, .vb-select {
        display: block; width: 100%; height: 38px; padding: 0 0.75rem;
        font-family: var(--vb-font-sans); font-size: var(--vb-text-base);
        color: var(--vb-text-primary); background: var(--vb-bg-input);
        border: 1px solid var(--vb-border-subtle); border-radius: var(--vb-radius-md);
        outline: none;
        transition: border-color var(--vb-duration-fast), box-shadow var(--vb-duration-fast);
        box-shadow: var(--vb-shadow-xs);
    }
    .vb-input:hover, .vb-select:hover { border-color: var(--vb-border-default); }
    .vb-input:focus, .vb-select:focus {
        border-color: var(--vb-accent);
        box-shadow: 0 0 0 3px var(--vb-accent-subtle), var(--vb-shadow-xs);
    }
    .vb-input::placeholder { color: var(--vb-text-ghost); }
    .vb-input[readonly] { background: var(--vb-bg-well); color: var(--vb-text-tertiary); cursor: default; }

    .vb-input-lg { height: 44px; padding: 0 1rem; font-size: var(--vb-text-md); border-radius: var(--vb-radius-lg); }
    .vb-input-icon { padding-left: 2.75rem; }
    .vb-input-mono { font-family: var(--vb-font-mono); font-size: var(--vb-text-sm); letter-spacing: 0.02em; }

    .vb-input-wrap { position: relative; }
    .vb-input-wrap .vb-icon-left {
        position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%);
        width: 16px; height: 16px; color: var(--vb-text-ghost); pointer-events: none;
    }

    /* ══════════════════════════════════════════════════════════════
       FORM STRUCTURE
       ══════════════════════════════════════════════════════════════ */
    .vb-form-group { margin-bottom: 1.25rem; }
    .vb-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 640px) { .vb-form-row { grid-template-columns: 1fr; } }
    .vb-form-actions { margin-top: 1.75rem; display: flex; align-items: center; gap: 0.75rem; }

    /* ══════════════════════════════════════════════════════════════
       CARD SYSTEM — Luminous layered surfaces
       ══════════════════════════════════════════════════════════════ */
    .vb-card {
        background: var(--vb-bg-surface);
        border-radius: var(--vb-radius-xl);
        padding: 1.5rem;
        box-shadow: var(--vb-shadow-card);
        transition: box-shadow var(--vb-duration-normal) var(--vb-ease-out);
    }
    .vb-card:hover { box-shadow: var(--vb-shadow-card-hover); }
    .vb-card-header { margin-bottom: 1.25rem; }
    .vb-card-title {
        font-size: var(--vb-text-md); font-weight: 600;
        letter-spacing: var(--vb-tracking-tight);
    }
    .vb-card-desc {
        font-size: var(--vb-text-sm); color: var(--vb-text-secondary);
        margin-top: 0.25rem;
    }

    /* ══════════════════════════════════════════════════════════════
       TAB SYSTEM — Premium pill tabs with indicator
       ══════════════════════════════════════════════════════════════ */
    .vb-tabs {
        display: inline-flex; align-items: center; gap: 2px;
        background: var(--vb-bg-hover);
        border-radius: var(--vb-radius-lg);
        padding: 3px;
        margin-bottom: 1.75rem;
    }
    .vb-tab {
        display: flex; align-items: center; gap: 0.375rem;
        padding: 0.4375rem 0.875rem;
        font-size: var(--vb-text-sm); font-weight: 450;
        color: var(--vb-text-tertiary); text-decoration: none;
        border-radius: var(--vb-radius-md);
        transition: color var(--vb-duration-fast), background var(--vb-duration-fast),
                    box-shadow var(--vb-duration-fast);
        white-space: nowrap; cursor: pointer;
    }
    .vb-tab:hover { color: var(--vb-text-primary); }
    .vb-tab.active {
        color: var(--vb-text-primary);
        background: var(--vb-bg-surface);
        box-shadow: var(--vb-shadow-sm);
        font-weight: 500;
    }
    .vb-tab svg { width: 14px; height: 14px; flex-shrink: 0; }

    /* Underline tab variant (for settings pages) */
    .vb-tabs-line {
        display: flex; gap: 0; border-bottom: 1px solid var(--vb-border-subtle);
        padding: 0; margin-bottom: 1.75rem; background: none; border-radius: 0;
    }
    .vb-tabs-line .vb-tab {
        border-radius: 0; padding: 0.625rem 1rem;
        border-bottom: 2px solid transparent; margin-bottom: -1px;
    }
    .vb-tabs-line .vb-tab.active {
        border-bottom-color: var(--vb-accent);
        color: var(--vb-accent); background: none; box-shadow: none;
    }

    /* ══════════════════════════════════════════════════════════════
       BADGE SYSTEM — Semantic status indicators
       ══════════════════════════════════════════════════════════════ */
    .vb-badge {
        display: inline-flex; align-items: center; gap: 0.25rem;
        font-size: var(--vb-text-2xs); font-weight: 600;
        padding: 0.1875rem 0.5rem; border-radius: var(--vb-radius-full);
        letter-spacing: 0.02em; white-space: nowrap;
        text-transform: uppercase;
    }
    .vb-badge svg { width: 10px; height: 10px; }
    .vb-badge-default { background: var(--vb-bg-hover); color: var(--vb-text-secondary); }
    .vb-badge-accent { background: var(--vb-accent-subtle); color: var(--vb-accent); }
    .vb-badge-success { background: var(--vb-success-bg); color: var(--vb-success); }
    .vb-badge-warning { background: var(--vb-warning-bg); color: var(--vb-warning); }
    .vb-badge-error { background: var(--vb-error-bg); color: var(--vb-error); }

    /* Dot variant — subtle presence indicator */
    .vb-dot {
        width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0;
    }
    .vb-dot-success { background: var(--vb-success); box-shadow: 0 0 6px var(--vb-success); }
    .vb-dot-warning { background: var(--vb-warning); box-shadow: 0 0 6px var(--vb-warning); }
    .vb-dot-error { background: var(--vb-error); box-shadow: 0 0 6px var(--vb-error); }
    .vb-dot-accent { background: var(--vb-accent); box-shadow: 0 0 6px var(--vb-accent); }

    /* ══════════════════════════════════════════════════════════════
       METRIC CARD — Dashboard analytics
       ══════════════════════════════════════════════════════════════ */
    .vb-metric {
        background: var(--vb-bg-surface);
        border-radius: var(--vb-radius-xl); padding: 1.375rem 1.5rem;
        box-shadow: var(--vb-shadow-card);
        position: relative; overflow: hidden;
        transition: box-shadow var(--vb-duration-normal) var(--vb-ease-out),
                    transform var(--vb-duration-normal) var(--vb-ease-out);
    }
    .vb-metric:hover {
        box-shadow: var(--vb-shadow-card-hover);
        transform: translateY(-1px);
    }
    .vb-metric-label {
        font-size: var(--vb-text-xs); font-weight: 500;
        letter-spacing: var(--vb-tracking-wide); color: var(--vb-text-tertiary);
        text-transform: uppercase;
    }
    .vb-metric-value {
        font-size: 2rem; font-weight: 700; letter-spacing: var(--vb-tracking-tighter);
        font-variant-numeric: tabular-nums; margin-top: 0.375rem;
        line-height: 1; color: var(--vb-text-primary);
    }
    .vb-metric-trend {
        display: inline-flex; align-items: center; gap: 0.25rem;
        font-size: var(--vb-text-xs); font-weight: 500; margin-top: 0.625rem;
    }
    .vb-metric-trend svg { width: 12px; height: 12px; }
    .vb-metric-trend.is-up { color: var(--vb-success); }
    .vb-metric-trend.is-down { color: var(--vb-error); }
    .vb-metric-trend.is-flat { color: var(--vb-text-ghost); }

    /* Decorative gradient accent bar */
    .vb-metric-accent {
        position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
        background: var(--vb-accent-gradient); opacity: 0;
        transition: opacity var(--vb-duration-normal);
    }
    .vb-metric:hover .vb-metric-accent { opacity: 1; }

    /* ══════════════════════════════════════════════════════════════
       EMPTY STATE — Onboarding & placeholder
       ══════════════════════════════════════════════════════════════ */
    .vb-empty {
        background: var(--vb-bg-surface);
        border-radius: var(--vb-radius-2xl); padding: 2.5rem;
        box-shadow: var(--vb-shadow-card);
        display: flex; align-items: flex-start; gap: 1.5rem;
    }
    .vb-empty-icon {
        width: 44px; height: 44px; flex-shrink: 0;
        color: var(--vb-accent); opacity: 0.4; margin-top: 0.125rem;
    }
    .vb-empty-title {
        font-size: var(--vb-text-xl); font-weight: 650;
        letter-spacing: var(--vb-tracking-tight); margin-bottom: 0.5rem;
    }
    .vb-empty-desc {
        font-size: var(--vb-text-sm); color: var(--vb-text-secondary);
        max-width: 440px; margin-bottom: 1.5rem;
        line-height: var(--vb-leading-relaxed);
    }
    .vb-empty-steps {
        display: flex; gap: 1.5rem; margin-top: 1.25rem;
        padding-top: 1.25rem; border-top: 1px solid var(--vb-border-subtle);
    }
    .vb-empty-step {
        display: flex; align-items: center; gap: 0.4375rem;
        font-size: var(--vb-text-xs); color: var(--vb-text-tertiary); font-weight: 500;
    }
    .vb-empty-step-num {
        width: 20px; height: 20px; border-radius: var(--vb-radius-full);
        background: var(--vb-accent-subtle); color: var(--vb-accent);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.625rem; font-weight: 700; flex-shrink: 0;
    }
    .vb-empty-step-arrow { color: var(--vb-text-ghost); width: 13px; height: 13px; }
    @media (max-width: 640px) {
        .vb-empty { flex-direction: column; text-align: center; align-items: center; }
        .vb-empty-steps { flex-direction: column; gap: 0.75rem; }
    }

    /* ══════════════════════════════════════════════════════════════
       ALERT / FLASH — Semantic feedback
       ══════════════════════════════════════════════════════════════ */
    .vb-alert {
        display: flex; align-items: center; gap: 0.625rem;
        padding: 0.75rem 1rem; border-radius: var(--vb-radius-md);
        font-size: var(--vb-text-sm); font-weight: 450;
        animation: vb-fade-in-down var(--vb-duration-normal) var(--vb-ease-out) both;
    }
    .vb-alert svg { width: 16px; height: 16px; flex-shrink: 0; }
    .vb-alert-success { background: var(--vb-success-bg); color: var(--vb-success); }
    .vb-alert-error { background: var(--vb-error-bg); color: var(--vb-error); }
    .vb-alert-warning { background: var(--vb-warning-bg); color: var(--vb-warning); }
    .vb-alert-info { background: var(--vb-info-bg); color: var(--vb-info); }
    .vb-alert-info { align-items: flex-start; } /* multi-line info alerts align top */

    /* Credential card (one-time post-creation display) */
    .vb-credentials-card {
        margin-top: 0.5rem; padding: 0.75rem 1rem;
        background: var(--vb-surface); border-radius: var(--vb-radius-md);
        border: 1px solid var(--vb-border);
        font-family: var(--vb-font-mono, 'JetBrains Mono', monospace);
    }
    .vb-credentials-row {
        display: flex; align-items: center; gap: 0.5rem;
        padding: 0.25rem 0; font-size: var(--vb-text-sm);
    }
    .vb-credentials-label {
        color: var(--vb-text-muted); font-weight: 500;
        min-width: 10rem; font-family: var(--vb-font-sans, Inter, sans-serif);
    }
    .vb-credentials-value {
        color: var(--vb-text); background: transparent;
        padding: 0; font-size: var(--vb-text-sm);
        user-select: all; /* easy copy */
    }
    .vb-credentials-hint {
        margin: 0.5rem 0 0; font-size: 12px;
        color: var(--vb-text-muted); font-style: italic;
        font-family: var(--vb-font-sans, Inter, sans-serif);
    }

    /* ══════════════════════════════════════════════════════════════
       OWNER SETUP — Toggle, password field, section divider
       ══════════════════════════════════════════════════════════════ */
    .vb-section-divider {
        height: 1px; background: var(--vb-border);
        margin: 1rem 0 1.25rem;
    }
    .vb-toggle-row {
        display: flex; align-items: flex-start; gap: 0.75rem;
        cursor: pointer; user-select: none;
    }
    .vb-checkbox {
        width: 18px; height: 18px; flex-shrink: 0;
        margin-top: 2px; accent-color: var(--vb-primary);
        cursor: pointer;
    }
    .vb-password-field {
        display: flex; align-items: center; gap: 0.375rem;
    }
    .vb-password-field .vb-input { flex: 1; }
    .vb-password-field .vb-btn { padding: 0.375rem; }
    .vb-password-field .vb-btn svg { width: 15px; height: 15px; }
    .vb-hint-warning {
        color: var(--vb-warning) !important;
    }
    .vb-owner-section {
        margin-top: 0.5rem;
    }

    /* ══════════════════════════════════════════════════════════════
       SETTINGS SYSTEM — Tabs, info rows, settings cards
       ══════════════════════════════════════════════════════════════ */
    /* Info rows (system info, account details) */
    .vb-info-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--vb-border-subtle);
    }
    .vb-info-row:last-child { border-bottom: none; }
    .vb-info-label { font-size: var(--vb-text-sm); font-weight: 500; color: var(--vb-text-secondary); }
    .vb-info-value { font-size: var(--vb-text-sm); color: var(--vb-text-primary); }
    .vb-info-value code {
        font-family: var(--vb-font-mono); font-size: var(--vb-text-xs);
        padding: 0.125rem 0.4375rem; background: var(--vb-bg-well);
        border-radius: var(--vb-radius-xs);
    }

    /* Log viewer */
    .vb-log-viewer {
        font-family: var(--vb-font-mono); font-size: 0.75rem;
        line-height: 1.6; background: var(--vb-bg-base);
        color: var(--vb-text-secondary);
        padding: 1rem; border-radius: var(--vb-radius-md);
        max-height: 400px; overflow-y: auto;
        white-space: pre-wrap; word-break: break-all;
        box-shadow: var(--vb-shadow-card);
    }

    /* Password strength bar */
    .vb-pw-track {
        height: 3px; border-radius: 2px; margin-top: 0.375rem;
        background: var(--vb-border-subtle); overflow: hidden;
    }
    .vb-pw-fill {
        height: 100%; width: 0; border-radius: 2px;
        transition: width var(--vb-duration-normal), background var(--vb-duration-normal);
    }

    /* ══════════════════════════════════════════════════════════════
       TOGGLE SWITCH — Premium slide toggle
       ══════════════════════════════════════════════════════════════ */
    .vb-toggle {
        position: relative; width: 40px; height: 22px;
        background: var(--vb-border-default); border-radius: 11px;
        cursor: pointer; border: none;
        transition: background var(--vb-duration-fast);
    }
    .vb-toggle::after {
        content: ''; position: absolute; top: 2px; left: 2px;
        width: 18px; height: 18px; border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
        transition: transform var(--vb-duration-normal) var(--vb-ease-spring);
    }
    .vb-toggle.is-on { background: var(--vb-accent); }
    .vb-toggle.is-on::after { transform: translateX(18px); }

    /* ══════════════════════════════════════════════════════════════
       AVATAR — User/entity identity
       ══════════════════════════════════════════════════════════════ */
    .vb-avatar {
        width: 32px; height: 32px; border-radius: var(--vb-radius-full);
        background: var(--vb-accent-gradient);
        color: #fff; font-weight: 600; font-size: var(--vb-text-xs);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; text-transform: uppercase;
    }
    .vb-avatar-sm { width: 24px; height: 24px; font-size: 0.5625rem; }
    .vb-avatar-lg { width: 40px; height: 40px; font-size: var(--vb-text-sm); }

    /* ══════════════════════════════════════════════════════════════
       TOOLTIP — Contextual hints
       ══════════════════════════════════════════════════════════════ */
    .vb-tooltip {
        position: relative; cursor: help;
    }
    .vb-tooltip::before {
        content: attr(data-tooltip);
        position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%) translateY(4px);
        padding: 0.375rem 0.625rem; border-radius: var(--vb-radius-sm);
        background: var(--vb-text-primary); color: var(--vb-text-inverted);
        font-size: var(--vb-text-xs); font-weight: 450;
        white-space: nowrap; pointer-events: none;
        opacity: 0; transition: all var(--vb-duration-fast) var(--vb-ease-out);
    }
    .vb-tooltip:hover::before {
        opacity: 1; transform: translateX(-50%) translateY(-6px);
    }

    /* ══════════════════════════════════════════════════════════════
       DIVIDER — Subtle content separation
       ══════════════════════════════════════════════════════════════ */
    .vb-divider {
        height: 1px; background: var(--vb-border-subtle);
        margin: 1.5rem 0; border: none;
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
    @media (max-width: 768px) { .vb-grid-2, .vb-grid-3, .vb-grid-4 { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 480px) { .vb-grid-2, .vb-grid-3, .vb-grid-4 { grid-template-columns: 1fr; } }

    /* Focus visible */
    :focus-visible { outline: 2px solid var(--vb-accent); outline-offset: 2px; }
    :focus:not(:focus-visible) { outline: none; }
    .vb-input:focus-visible, .vb-select:focus-visible { outline: none; }

    /* Color scheme for native controls (scrollbars, form elements) */
    :root { color-scheme: light; }
    [data-theme="dark"] { color-scheme: dark; }

    /* Scrollbar — slim, theme-aware */
    * { scrollbar-width: thin; scrollbar-color: var(--vb-border-default) transparent; }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--vb-border-default); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--vb-border-strong); }
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
