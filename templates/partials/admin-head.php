<?php
/**
 * Shared admin head partial: font-face, design tokens, reset, theme resolution.
 *
 * Included in all standalone admin templates (login, error pages)
 * and in the admin layout. Outputs two <style> blocks and the
 * theme-resolution <script>.
 *
 * Does NOT output <head> or <html> tags — the parent template owns those.
 *
 * Variables available: none required.
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
    /* ── Admin Design Tokens (PRD §3 / Visual Design §3) ── */
    :root {
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
        --vb-admin-success: #059669;
        --vb-admin-success-bg: #ECFDF5;
        --vb-admin-warning: #D97706;
        --vb-admin-warning-bg: #FFFBEB;
        --vb-admin-error: #DC2626;
        --vb-admin-error-bg: #FEF2F2;
        --vb-admin-info: #2563EB;
        --vb-admin-info-bg: #EFF6FF;
        --vb-font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        --vb-text-sm: 0.8125rem;
        --vb-text-base: 0.875rem;
        --vb-text-md: 0.9375rem;
        --vb-text-lg: 1.125rem;
        --vb-text-xl: 1.375rem;
        --vb-leading-normal: 1.5;
        --vb-tracking-tight: -0.025em;
        --vb-tracking-normal: -0.011em;
        --vb-radius: 12px;
        --vb-radius-sm: 8px;
        --vb-sidebar-width: 220px;
        --vb-sidebar-collapsed: 56px;
        --vb-topbar-height: 56px;
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

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    [data-theme="dark"] body { font-weight: 350; }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }
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
