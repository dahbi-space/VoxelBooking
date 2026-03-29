<?php
/**
 * Invite business user form.
 *
 * Uses Alpine.js CSP build — all bindings use method/property references.
 * Alpine component: inviteUser (registered in app.js)
 *
 * Variables: $tenant, $tenantId, $flash, $csrfToken
 */
$tenant = $tenant ?? [];
$tenantId = $tenantId ?? '';

ob_start();
?>
<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title"><?= __('admin.users.invite_title') ?></h2>
        <p class="vb-page-subtitle"><?= __('admin.users.invite_subtitle') ?></p>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="vb-alert vb-alert-<?= htmlspecialchars($flash['type'] ?? 'error', ENT_QUOTES, 'UTF-8') ?>">
        <i data-lucide="alert-circle" style="width: 16px; height: 16px; flex-shrink: 0;"></i>
        <span><?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<div class="vb-card" x-data="inviteUser" data-smtp-configured="<?= \App\Engine\Mailer::isConfigured() ? '1' : '0' ?>">
    <form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/users/invite">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <!-- Name -->
        <div class="vb-form-group">
            <label for="invite_name" class="vb-label"><?= __('admin.users.invite_name') ?> *</label>
            <input type="text" id="invite_name" name="name" class="vb-input"
                   placeholder="Jane Doe" required>
        </div>

        <!-- Email -->
        <div class="vb-form-group">
            <label for="invite_email" class="vb-label"><?= __('admin.users.invite_email') ?> *</label>
            <input type="email" id="invite_email" name="email" class="vb-input"
                   placeholder="owner@example.com" required>
        </div>

        <!-- Role -->
        <div class="vb-form-group">
            <label class="vb-label"><?= __('admin.users.invite_role') ?> *</label>
            <div class="vb-role-cards">
                <label class="vb-role-card" :class="roleOwnerClass">
                    <input type="radio" name="role" value="owner" :checked="isOwner" @change="selectOwner" class="vb-sr-only">
                    <i data-lucide="shield-check" style="width: 20px; height: 20px; margin-bottom: 4px;"></i>
                    <span class="vb-role-card-title"><?= __('admin.users.invite_role_owner_title') ?></span>
                    <span class="vb-role-card-desc"><?= __('admin.users.invite_role_owner_desc') ?></span>
                </label>
                <label class="vb-role-card" :class="roleManagerClass">
                    <input type="radio" name="role" value="manager" :checked="isManager" @change="selectManager" class="vb-sr-only">
                    <i data-lucide="briefcase" style="width: 20px; height: 20px; margin-bottom: 4px;"></i>
                    <span class="vb-role-card-title"><?= __('admin.users.invite_role_manager_title') ?></span>
                    <span class="vb-role-card-desc"><?= __('admin.users.invite_role_manager_desc') ?></span>
                </label>
            </div>
        </div>

        <!-- Password -->
        <div class="vb-form-group">
            <label for="invite_password" class="vb-label"><?= __('admin.users.invite_password') ?> *</label>
            <div class="vb-password-field">
                <input type="password" id="invite_password" name="password" class="vb-input"
                       x-ref="passwordField" autocomplete="new-password" required minlength="8">
                <button type="button" class="vb-btn vb-btn-ghost vb-btn-sm" @click="togglePasswordVisibility"
                        title="<?= __('admin.users.invite_toggle_visibility') ?>">
                    <i data-lucide="eye" x-show="!showPassword"></i>
                    <i data-lucide="eye-off" x-show="showPassword"></i>
                </button>
                <button type="button" class="vb-btn vb-btn-ghost vb-btn-sm" @click="generatePassword"
                        title="<?= __('admin.users.invite_generate') ?>">
                    <i data-lucide="refresh-cw"></i>
                </button>
            </div>
        </div>

        <!-- Send email toggle -->
        <div class="vb-form-group">
            <div class="vb-toggle-row">
                <input type="checkbox" id="invite_send_email" name="send_email" value="1"
                       :checked="sendEmail"
                       @change="onToggleSendEmail"
                       :disabled="smtpNotConfigured"
                       class="vb-checkbox">
                <label for="invite_send_email" style="cursor: pointer;">
                    <span class="vb-label" style="margin-bottom: 0;">
                        <i data-lucide="mail" style="width: 14px; height: 14px; display: inline; vertical-align: -2px; margin-right: 4px;"></i>
                        <?= __('admin.users.invite_send_email') ?>
                    </span>
                    <?php if (!\App\Engine\Mailer::isConfigured()): ?>
                        <span class="vb-hint vb-hint-warning" style="margin-top: 2px;">
                            <i data-lucide="alert-triangle" style="width: 12px; height: 12px; display: inline; vertical-align: -1px; margin-right: 3px;"></i>
                            <?= __('admin.users.invite_smtp_hint') ?>
                        </span>
                    <?php endif; ?>
                </label>
            </div>
        </div>

        <div class="vb-form-actions">
            <button type="submit" class="vb-btn vb-btn-primary">
                <i data-lucide="user-plus" style="width: 16px; height: 16px;"></i>
                <?= __('admin.users.invite_submit') ?>
            </button>
            <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/users" class="vb-btn vb-btn-ghost">
                <?= __('admin.common.cancel') ?>
            </a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
