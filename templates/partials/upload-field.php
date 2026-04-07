<?php
/**
 * Reusable image upload field partial (Alpine CSP-safe).
 *
 * Variables:
 *   $uploadFieldName   - input name attribute (e.g. 'avatar')
 *   $uploadFieldId     - unique DOM id (e.g. 'staff_avatar')
 *   $uploadLabel       - label text
 *   $uploadHint        - optional hint text (defaults to upload.formats_hint)
 *   $uploadCurrentPath - current image relative path or null
 *   $uploadShape       - 'circle' or 'rect' (default: 'circle')
 *
 * Alpine component: imageUpload (registered in admin/app.js)
 * Reads data-preview and data-has-file from the root element on init().
 * Drag/drop is handled by native event listeners in the component's init().
 */
$uploadFieldName   = $uploadFieldName ?? 'image';
$uploadFieldId     = $uploadFieldId ?? 'upload_image';
$uploadLabel       = $uploadLabel ?? __('admin.upload.choose_file');
$uploadHint        = $uploadHint ?? __('admin.upload.formats_hint');
$uploadCurrentPath = $uploadCurrentPath ?? null;
$uploadShape       = $uploadShape ?? 'circle';
$hasExisting       = !empty($uploadCurrentPath);
$previewSrc        = $hasExisting ? '/' . ltrim($uploadCurrentPath, '/') : '';
$isAvatar          = ($uploadShape === 'circle');
?>

<div class="vb-form-group">
    <label class="vb-label">
        <?= htmlspecialchars($uploadLabel, ENT_QUOTES, 'UTF-8') ?>
    </label>

    <div class="vb-upload <?= $isAvatar ? 'vb-upload--avatar' : 'vb-upload--cover' ?>"
         x-data="imageUpload"
         data-preview="<?= htmlspecialchars($previewSrc, ENT_QUOTES, 'UTF-8') ?>"
         data-has-file="<?= $hasExisting ? '1' : '0' ?>">

<?php if ($isAvatar): ?>
        <!-- ═══ AVATAR variant: compact circle, icon-only ═══ -->
        <div class="vb-avatar-field">
            <label class="vb-avatar-target"
                   for="<?= htmlspecialchars($uploadFieldId, ENT_QUOTES, 'UTF-8') ?>">
                <!-- Empty state: icon -->
                <template x-if="!hasFile">
                    <span class="vb-avatar-empty">
                        <i data-lucide="user-round" class="vb-avatar-empty-icon"></i>
                    </span>
                </template>
                <!-- Populated state: image + hover overlay -->
                <template x-if="hasFile">
                    <span class="vb-avatar-filled">
                        <img x-bind:src="previewSrc" alt="" class="vb-avatar-img">
                        <span class="vb-avatar-overlay">
                            <i data-lucide="camera" class="vb-avatar-overlay-icon"></i>
                        </span>
                    </span>
                </template>
            </label>
            <button type="button" class="vb-avatar-remove" x-show="hasFile" @click="remove"
                    title="<?= __('admin.upload.remove') ?>">
                <i data-lucide="x"></i>
            </button>
        </div>

<?php else: ?>
        <!-- ═══ COVER variant: 16:9 media panel ═══ -->
        <div class="vb-cover-surface">
            <img x-show="hasFile" x-bind:src="previewSrc" alt="" class="vb-cover-img">

            <label class="vb-cover-zone"
                   x-bind:class="hasFile ? 'is-populated' : 'is-empty'"
                   for="<?= htmlspecialchars($uploadFieldId, ENT_QUOTES, 'UTF-8') ?>">
                <template x-if="!hasFile">
                    <div class="vb-cover-placeholder">
                        <i data-lucide="image-plus" class="vb-cover-placeholder-icon"></i>
                        <span class="vb-cover-placeholder-text"><?= __('admin.upload.drag_or_click') ?></span>
                        <span class="vb-cover-placeholder-hint"><?= htmlspecialchars($uploadHint, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </template>
                <template x-if="hasFile">
                    <span class="vb-cover-change">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                        <?= __('admin.upload.change') ?>
                    </span>
                </template>
            </label>
        </div>

        <div class="vb-cover-actions" x-show="hasFile">
            <button type="button" class="vb-cover-remove" @click="remove">
                <i data-lucide="x"></i>
                <?= __('admin.upload.remove') ?>
            </button>
        </div>

<?php endif; ?>

        <!-- File input (visually hidden) -->
        <input type="file"
               id="<?= htmlspecialchars($uploadFieldId, ENT_QUOTES, 'UTF-8') ?>"
               name="<?= htmlspecialchars($uploadFieldName, ENT_QUOTES, 'UTF-8') ?>"
               accept="image/jpeg,image/png,image/webp,image/gif"
               class="vb-upload-input"
               x-ref="fileInput"
               @change="onFileChange">

        <!-- Hidden flag to signal removal of existing image -->
        <input type="hidden" name="remove_<?= htmlspecialchars($uploadFieldName, ENT_QUOTES, 'UTF-8') ?>"
               x-bind:value="removeValue">
    </div>
</div>
