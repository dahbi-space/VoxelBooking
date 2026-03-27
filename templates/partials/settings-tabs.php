<?php
/**
 * Settings sub-navigation partial.
 *
 * Included at the top of each settings page.
 * Variable: $activeTab (general|account|email|cron|logs)
 */
$activeTab = $activeTab ?? 'general';
$tabs = [
    'general' => ['url' => '/admin/settings', 'label' => 'General', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>'],
    'account' => ['url' => '/admin/settings/account', 'label' => 'Account', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'],
    'email'   => ['url' => '/admin/settings/email', 'label' => 'Email', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>'],
    'cron'    => ['url' => '/admin/settings/cron', 'label' => 'Cron', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'],
    'logs'    => ['url' => '/admin/settings/logs', 'label' => 'Logs', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>'],
];
?>
<style>
    .settings-tabs {
        display: flex; gap: 0.25rem; margin-bottom: 1.5rem;
        border-bottom: 1px solid var(--vb-admin-border-subtle);
        padding-bottom: 0; overflow-x: auto;
    }
    .settings-tab {
        display: flex; align-items: center; gap: 0.375rem;
        padding: 0.5rem 0.75rem; font-size: var(--vb-text-sm); font-weight: 450;
        color: var(--vb-admin-text-secondary); text-decoration: none;
        border-bottom: 2px solid transparent; transition: color 100ms, border-color 100ms;
        white-space: nowrap;
    }
    .settings-tab:hover { color: var(--vb-admin-text-primary); }
    .settings-tab.active { color: var(--vb-admin-accent); border-bottom-color: var(--vb-admin-accent); font-weight: 500; }
    .settings-tab svg { width: 16px; height: 16px; flex-shrink: 0; }
    .settings-card {
        background: var(--vb-admin-bg-surface); border: 1px solid var(--vb-admin-border-subtle);
        border-radius: var(--vb-radius); padding: 1.5rem; box-shadow: var(--vb-admin-shadow-sm);
        margin-bottom: 1.5rem;
    }
    .settings-card h3 { font-size: var(--vb-text-md); font-weight: 600; margin-bottom: 0.25rem; letter-spacing: var(--vb-tracking-tight); }
    .settings-card .card-desc { font-size: var(--vb-text-sm); color: var(--vb-admin-text-secondary); margin-bottom: 1.25rem; }
    .settings-card p { font-size: var(--vb-text-sm); color: var(--vb-admin-text-secondary); }

    /* Form elements */
    .form-group { margin-bottom: 1rem; }
    .form-label {
        display: block; font-size: var(--vb-text-sm); font-weight: 500;
        color: var(--vb-admin-text-primary); margin-bottom: 0.375rem;
    }
    .form-hint { font-size: 0.75rem; color: var(--vb-admin-text-tertiary); margin-top: 0.25rem; }
    .form-input, .form-select {
        width: 100%; padding: 0.5rem 0.75rem;
        font-family: var(--vb-font-sans); font-size: var(--vb-text-sm);
        color: var(--vb-admin-text-primary); background: var(--vb-admin-bg-base);
        border: 1px solid var(--vb-admin-border-subtle); border-radius: var(--vb-radius-sm);
        transition: border-color 150ms, box-shadow 150ms;
        box-sizing: border-box;
    }
    .form-input:focus, .form-select:focus {
        outline: none; border-color: var(--vb-admin-accent);
        box-shadow: 0 0 0 3px var(--vb-admin-accent-dim);
    }
    .form-input::placeholder { color: var(--vb-admin-text-ghost); }
    .form-input[readonly] { background: var(--vb-admin-bg-hover); color: var(--vb-admin-text-tertiary); cursor: not-allowed; }
    .form-input-mono { font-family: ui-monospace, 'SF Mono', 'Cascadia Mono', 'Segoe UI Mono', monospace; font-size: 0.8125rem; letter-spacing: 0.02em; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 640px) { .form-row { grid-template-columns: 1fr; } }

    .btn-primary {
        display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem;
        font-family: var(--vb-font-sans); font-size: var(--vb-text-sm); font-weight: 600;
        color: #fff; background: var(--vb-admin-accent); border: none; border-radius: var(--vb-radius-sm);
        cursor: pointer; transition: background 150ms;
    }
    .btn-primary:hover { background: var(--vb-admin-accent-hover); }
    .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
    .btn-primary svg { width: 16px; height: 16px; }

    .btn-secondary {
        display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem;
        font-family: var(--vb-font-sans); font-size: var(--vb-text-sm); font-weight: 500;
        color: var(--vb-admin-text-secondary); background: transparent;
        border: 1px solid var(--vb-admin-border-subtle); border-radius: var(--vb-radius-sm);
        cursor: pointer; transition: background 150ms, color 150ms;
    }
    .btn-secondary:hover { background: var(--vb-admin-bg-hover); color: var(--vb-admin-text-primary); }

    /* Flash messages */
    .flash-success {
        padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: var(--vb-radius-sm);
        font-size: var(--vb-text-sm); font-weight: 450;
        background: var(--vb-admin-success-bg); color: var(--vb-admin-success);
        border: 1px solid var(--vb-admin-success);
    }
    .flash-error {
        padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: var(--vb-radius-sm);
        font-size: var(--vb-text-sm); font-weight: 450;
        background: var(--vb-admin-error-bg); color: var(--vb-admin-error);
        border: 1px solid var(--vb-admin-error);
    }

    /* Readonly info row */
    .info-row { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--vb-admin-border-subtle); }
    .info-row:last-child { border-bottom: none; }
    .info-label { font-size: var(--vb-text-sm); font-weight: 500; color: var(--vb-admin-text-secondary); }
    .info-value { font-size: var(--vb-text-sm); color: var(--vb-admin-text-primary); }
    .info-value code { font-family: ui-monospace, 'SF Mono', monospace; font-size: 0.8125rem; padding: 0.125rem 0.375rem; background: var(--vb-admin-bg-hover); border-radius: 3px; }

    /* Log viewer */
    .log-viewer {
        font-family: ui-monospace, 'SF Mono', 'Cascadia Mono', monospace; font-size: 0.75rem;
        line-height: 1.6; background: var(--vb-admin-bg-base); color: var(--vb-admin-text-secondary);
        padding: 1rem; border-radius: var(--vb-radius-sm); max-height: 400px; overflow-y: auto;
        white-space: pre-wrap; word-break: break-all;
        border: 1px solid var(--vb-admin-border-subtle);
    }
</style>
<nav class="settings-tabs">
    <?php foreach ($tabs as $key => $tab): ?>
        <a href="<?= $tab['url'] ?>" class="settings-tab <?= $activeTab === $key ? 'active' : '' ?>">
            <?= $tab['icon'] ?>
            <?= $tab['label'] ?>
        </a>
    <?php endforeach; ?>
</nav>
