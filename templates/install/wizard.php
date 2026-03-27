<?php
/**
 * Installation Wizard Template
 *
 * Variables: $step (int|'complete'), $checks (array), $errors (array), $flash (array), $session (array)
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
    <title>Install — VoxelBooking</title>
    <style>
        :root {
            --vb-bg: #F8FAFC;
            --vb-surface: #FFFFFF;
            --vb-surface-alt: #F1F5F9;
            --vb-border: #E2E8F0;
            --vb-text: #1A1A2E;
            --vb-text-muted: #64748B;
            --vb-primary: #2563EB;
            --vb-primary-hover: #1D4ED8;
            --vb-primary-text: #FFFFFF;
            --vb-success: #16A34A;
            --vb-error: #DC2626;
            --vb-warning: #F59E0B;
            --vb-info: #3B82F6;
            --vb-radius: 12px;
            --vb-radius-sm: 8px;
            --vb-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --vb-shadow-lg: 0 10px 25px rgba(0,0,0,0.08);
            --vb-font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        }

        [data-theme="dark"] {
            --vb-bg: #0F172A;
            --vb-surface: #1E293B;
            --vb-surface-alt: #334155;
            --vb-border: #475569;
            --vb-text: #F1F5F9;
            --vb-text-muted: #94A3B8;
            --vb-shadow: 0 1px 3px rgba(0,0,0,0.2);
            --vb-shadow-lg: 0 10px 25px rgba(0,0,0,0.3);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--vb-font);
            background: var(--vb-bg);
            color: var(--vb-text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .wizard {
            width: 100%;
            max-width: 560px;
        }

        .wizard-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .wizard-logo {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
            display: block;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .wizard-title {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.25rem;
        }

        .wizard-subtitle {
            color: var(--vb-text-muted);
            font-size: 0.9375rem;
        }

        /* Step indicator */
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
            background: var(--vb-border);
            transition: all 0.3s ease;
        }

        .step-dot.active {
            background: var(--vb-primary);
            transform: scale(1.2);
        }

        .step-dot.done {
            background: var(--vb-success);
        }

        /* Card */
        .card {
            background: var(--vb-surface);
            border: 1px solid var(--vb-border);
            border-radius: var(--vb-radius);
            padding: 2rem;
            box-shadow: var(--vb-shadow-lg);
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.375rem;
            color: var(--vb-text);
        }

        .form-input {
            width: 100%;
            padding: 0.625rem 0.875rem;
            font-size: 0.9375rem;
            font-family: var(--vb-font);
            background: var(--vb-surface-alt);
            border: 1px solid var(--vb-border);
            border-radius: var(--vb-radius-sm);
            color: var(--vb-text);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--vb-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .form-input.error {
            border-color: var(--vb-error);
        }

        .form-error {
            font-size: 0.8125rem;
            color: var(--vb-error);
            margin-top: 0.25rem;
        }

        .form-hint {
            font-size: 0.8125rem;
            color: var(--vb-text-muted);
            margin-top: 0.25rem;
        }

        select.form-input {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5rem;
            padding-right: 2.5rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.5rem;
            font-size: 0.9375rem;
            font-weight: 600;
            font-family: var(--vb-font);
            border: none;
            border-radius: var(--vb-radius-sm);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--vb-primary);
            color: var(--vb-primary-text);
        }

        .btn-primary:hover {
            background: var(--vb-primary-hover);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-ghost {
            background: transparent;
            color: var(--vb-text-muted);
            padding: 0.5rem 1rem;
        }

        .btn-ghost:hover {
            color: var(--vb-text);
            background: var(--vb-surface-alt);
        }

        .btn-block { width: 100%; }

        /* System checks */
        .check-list {
            list-style: none;
        }

        .check-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 0;
            border-bottom: 1px solid var(--vb-border);
            font-size: 0.9375rem;
            opacity: 0;
            animation: fadeIn 0.3s ease forwards;
        }

        .check-item:last-child { border-bottom: none; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .check-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .check-icon.pass { color: var(--vb-success); }
        .check-icon.fail { color: var(--vb-error); }

        .check-name { flex: 1; }
        .check-status {
            font-size: 0.8125rem;
            color: var(--vb-text-muted);
        }

        /* Flash messages */
        .flash {
            padding: 0.75rem 1rem;
            border-radius: var(--vb-radius-sm);
            margin-bottom: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .flash-success { background: #DCFCE7; color: #166534; }
        .flash-error { background: #FEE2E2; color: #991B1B; }
        .flash-info { background: #DBEAFE; color: #1E40AF; }
        .flash-warning { background: #FEF3C7; color: #92400E; }

        [data-theme="dark"] .flash-success { background: #166534; color: #DCFCE7; }
        [data-theme="dark"] .flash-error { background: #991B1B; color: #FEE2E2; }
        [data-theme="dark"] .flash-info { background: #1E40AF; color: #DBEAFE; }
        [data-theme="dark"] .flash-warning { background: #92400E; color: #FEF3C7; }

        /* Connection error */
        .connection-error {
            background: #FEE2E2;
            border: 1px solid #FECACA;
            border-radius: var(--vb-radius-sm);
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            color: #991B1B;
            font-size: 0.875rem;
        }

        [data-theme="dark"] .connection-error {
            background: #991B1B;
            border-color: #DC2626;
            color: #FEE2E2;
        }

        /* Password strength */
        .password-strength {
            height: 4px;
            border-radius: 2px;
            background: var(--vb-border);
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease, background-color 0.3s ease;
            width: 0;
        }

        /* Theme toggle */
        .theme-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: var(--vb-surface);
            border: 1px solid var(--vb-border);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.125rem;
            box-shadow: var(--vb-shadow);
            transition: all 0.2s;
            z-index: 10;
        }

        .theme-toggle:hover {
            box-shadow: var(--vb-shadow-lg);
        }

        /* Booking pattern cards */
        .pattern-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .pattern-card {
            border: 2px solid var(--vb-border);
            border-radius: var(--vb-radius-sm);
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .pattern-card:hover { border-color: var(--vb-primary); }

        .pattern-card.selected {
            border-color: var(--vb-primary);
            background: rgba(37, 99, 235, 0.05);
        }

        .pattern-card input { display: none; }

        .pattern-card-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .pattern-card-name {
            font-weight: 600;
            font-size: 0.875rem;
        }

        /* Color input */
        .color-input-group {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .color-swatch {
            width: 40px;
            height: 40px;
            border-radius: var(--vb-radius-sm);
            border: 2px solid var(--vb-border);
            cursor: pointer;
        }

        /* Completion screen */
        .completion {
            text-align: center;
            padding: 3rem 2rem;
        }

        .completion-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.5rem;
            color: var(--vb-success);
        }

        .completion h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .completion p {
            color: var(--vb-text-muted);
            margin-bottom: 1.5rem;
        }

        .booking-url {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--vb-surface-alt);
            padding: 0.75rem 1rem;
            border-radius: var(--vb-radius-sm);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            word-break: break-all;
        }

        .booking-url a {
            color: var(--vb-primary);
            text-decoration: none;
            flex: 1;
        }

        .copy-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--vb-text-muted);
            padding: 0.25rem;
            font-size: 0.875rem;
        }

        .copy-btn:hover { color: var(--vb-text); }

        /* Form row for side-by-side fields */
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
            color: var(--vb-text-muted);
            font-size: 0.875rem;
            text-decoration: none;
            cursor: pointer;
        }

        .skip-link:hover { color: var(--vb-text); }

        .actions {
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle dark mode" title="Toggle dark mode">
        <span id="theme-icon">🌙</span>
    </button>

    <div class="wizard">
        <div class="wizard-header">
            <!-- VoxelBooking Logo (inline SVG placeholder — replaced with actual logo in Phase 2) -->
            <svg class="wizard-logo" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="48" height="48" rx="12" fill="var(--vb-primary)"/>
                <path d="M14 16L24 32L34 16" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>

            <?php if ($step === 'complete'): ?>
                <h1 class="wizard-title">Installation Complete</h1>
                <p class="wizard-subtitle">VoxelBooking is ready.</p>
            <?php else: ?>
                <h1 class="wizard-title">Install VoxelBooking</h1>
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
                <?= htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endforeach; ?>

        <div class="card">

            <?php if ($step === 1): ?>
            <!-- ══════════ Step 1: System Requirements ══════════ -->
            <h2 class="card-title">System Requirements</h2>

            <ul class="check-list" id="check-list">
                <?php foreach ($checks as $index => $check): ?>
                <li class="check-item" style="animation-delay: <?= $index * 150 ?>ms">
                    <svg class="check-icon <?= $check['passed'] ? 'pass' : 'fail' ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                <a href="/install?step=2" class="btn btn-primary btn-block <?= !$allChecksPassed ? 'disabled' : '' ?>"
                   <?= !$allChecksPassed ? 'onclick="return false" style="pointer-events:none"' : '' ?>>
                    Continue
                </a>
            </div>

            <?php elseif ($step === 2): ?>
            <!-- ══════════ Step 2: Database Configuration ══════════ -->
            <h2 class="card-title">Database Configuration</h2>

            <?php if (!empty($errors['db_connection'])): ?>
                <div class="connection-error"><?= htmlspecialchars($errors['db_connection'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if (!empty($errors['db_migration'])): ?>
                <div class="connection-error"><?= htmlspecialchars($errors['db_migration'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="/install/step/2">
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
                    <button type="submit" class="btn btn-primary btn-block">Test Connection & Continue</button>
                </div>
            </form>

            <?php elseif ($step === 3): ?>
            <!-- ══════════ Step 3: Email Configuration ══════════ -->
            <h2 class="card-title">Email Configuration</h2>

            <form method="POST" action="/install/step/3">
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
                    <button type="submit" class="btn btn-primary btn-block">Save & Continue</button>
                </div>
            </form>

            <form method="POST" action="/install/step/3">
                <input type="hidden" name="skip" value="1">
                <button type="submit" class="skip-link">I'll configure this later</button>
            </form>

            <?php elseif ($step === 4): ?>
            <!-- ══════════ Step 4: Operator Account ══════════ -->
            <h2 class="card-title">Create Your Account</h2>

            <form method="POST" action="/install/step/4">
                <div class="form-group">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-input <?= isset($errors['name']) ? 'error' : '' ?>" required autocomplete="name">
                    <?php if (isset($errors['name'])): ?>
                        <div class="form-error"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-input <?= isset($errors['email']) ? 'error' : '' ?>" required autocomplete="email">
                    <?php if (isset($errors['email'])): ?>
                        <div class="form-error"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input <?= isset($errors['password']) ? 'error' : '' ?>" required minlength="8" autocomplete="new-password" oninput="updateStrength(this.value)">
                    <?php if (isset($errors['password'])): ?>
                        <div class="form-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <div class="password-strength"><div class="password-strength-bar" id="strength-bar"></div></div>
                    <div class="form-hint">Minimum 8 characters.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-input <?= isset($errors['password_confirmation']) ? 'error' : '' ?>" required autocomplete="new-password">
                    <?php if (isset($errors['password_confirmation'])): ?>
                        <div class="form-error"><?= htmlspecialchars($errors['password_confirmation'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary btn-block">Create Account & Continue</button>
                </div>
            </form>

            <?php elseif ($step === 5): ?>
            <!-- ══════════ Step 5: First Tenant ══════════ -->
            <h2 class="card-title">Create Your First Business</h2>

            <form method="POST" action="/install/step/5">
                <div class="form-group">
                    <label class="form-label" for="tenant_name">Business Name</label>
                    <input type="text" id="tenant_name" name="name" class="form-input <?= isset($errors['name']) ? 'error' : '' ?>" required placeholder="e.g. Salon Bella">
                    <?php if (isset($errors['name'])): ?>
                        <div class="form-error"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Booking Pattern</label>
                    <div class="pattern-cards">
                        <label class="pattern-card" onclick="selectPattern(this)">
                            <input type="radio" name="booking_pattern" value="timeslot" checked>
                            <span class="pattern-card-icon">🕐</span>
                            <span class="pattern-card-name">Time Slots</span>
                        </label>
                        <label class="pattern-card" onclick="selectPattern(this)">
                            <input type="radio" name="booking_pattern" value="resource">
                            <span class="pattern-card-icon">🏠</span>
                            <span class="pattern-card-name">Resources</span>
                        </label>
                        <label class="pattern-card" onclick="selectPattern(this)">
                            <input type="radio" name="booking_pattern" value="capacity">
                            <span class="pattern-card-icon">👥</span>
                            <span class="pattern-card-name">Capacity</span>
                        </label>
                        <label class="pattern-card" onclick="selectPattern(this)">
                            <input type="radio" name="booking_pattern" value="event">
                            <span class="pattern-card-icon">📅</span>
                            <span class="pattern-card-name">Events</span>
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
                        <input type="color" id="brand_color_picker" class="color-swatch" value="#2563EB" onchange="document.getElementById('brand_color').value=this.value">
                        <input type="text" id="brand_color" name="brand_color" class="form-input" value="#2563EB" maxlength="7" onchange="document.getElementById('brand_color_picker').value=this.value">
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary btn-block">Create Business & Finish</button>
                </div>
            </form>

            <form method="POST" action="/install/step/5">
                <input type="hidden" name="skip" value="1">
                <button type="submit" class="skip-link">I'll do this from the dashboard</button>
            </form>

            <?php elseif ($step === 'complete'): ?>
            <!-- ══════════ Completion ══════════ -->
            <div class="completion">
                <svg class="completion-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>

                <h2>Installation Complete</h2>
                <p>VoxelBooking is ready to accept bookings.</p>

                <?php if (!empty($session['tenant_slug'])): ?>
                <div class="booking-url">
                    <?php $bookingUrl = ($_ENV['APP_URL'] ?? '') . '/book/' . ($session['tenant_slug'] ?? ''); ?>
                    <a href="<?= htmlspecialchars($bookingUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                        <?= htmlspecialchars($bookingUrl, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <button class="copy-btn" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($bookingUrl, ENT_QUOTES, 'UTF-8') ?>')" title="Copy URL">📋</button>
                </div>
                <?php endif; ?>

                <a href="/admin" class="btn btn-primary btn-block">Go to Dashboard</a>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        // Theme toggle
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('vb-theme', next);
            document.getElementById('theme-icon').textContent = next === 'dark' ? '☀️' : '🌙';
        }

        // Restore saved theme
        const savedTheme = localStorage.getItem('vb-theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.getElementById('theme-icon').textContent = savedTheme === 'dark' ? '☀️' : '🌙';
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.getElementById('theme-icon').textContent = '☀️';
        }

        // Password strength
        function updateStrength(password) {
            const bar = document.getElementById('strength-bar');
            if (!bar) return;
            let score = 0;
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            const width = Math.min(100, score * 20);
            const colors = ['#DC2626', '#F59E0B', '#F59E0B', '#16A34A', '#16A34A'];
            bar.style.width = width + '%';
            bar.style.backgroundColor = colors[Math.min(score, colors.length) - 1] || '#DC2626';
        }

        // Pattern card selection
        function selectPattern(el) {
            document.querySelectorAll('.pattern-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
        }

        // Initialize first selected pattern
        document.addEventListener('DOMContentLoaded', function() {
            const checked = document.querySelector('.pattern-card input:checked');
            if (checked) checked.closest('.pattern-card').classList.add('selected');
        });
    </script>
</body>
</html>
