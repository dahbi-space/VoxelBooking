/**
 * Regression tests for the platform-wide admin form validator.
 *
 * Tests the hidden-field skip logic: inputs inside collapsed (display: none)
 * Alpine x-show sections must not block form submission.
 *
 * Since the validator is a global IIFE in admin/app.js, we re-implement the
 * core skip logic here to test it in isolation. The production code and this
 * test share the same algorithm — if the production logic changes, this
 * test must be updated to match.
 *
 * @vitest-environment jsdom
 */
import { describe, expect, it, beforeEach } from 'vitest';

/**
 * Extracted hidden-field skip logic from admin/app.js (lines 920-926).
 * Returns true if the input should be SKIPPED (not validated).
 */
function shouldSkipInput(input) {
    if (input.disabled) return true;
    const hiddenAncestor = input.closest('[style*="display: none"]');
    if (hiddenAncestor) return true;
    if (input.offsetParent === null && input.type !== 'hidden') return true;
    return false;
}

describe('Admin form validator: hidden-field skip logic', () => {
    let container;

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
    });

    it('skips inputs inside display:none ancestors (Alpine x-show collapsed)', () => {
        container.innerHTML = `
            <div class="vb-owner-section">
                <div style="display: none;">
                    <input type="email" id="owner_email" class="vb-input" required value="bad">
                </div>
            </div>
        `;
        const input = container.querySelector('#owner_email');
        expect(shouldSkipInput(input)).toBe(true);
    });

    it('validates inputs that are visible (not inside display:none)', () => {
        container.innerHTML = `
            <div class="vb-form-group">
                <input type="email" id="tenant_email" class="vb-input" required value="">
            </div>
        `;
        const input = container.querySelector('#tenant_email');
        // In JSDOM offsetParent is always null, so we can't fully test this case.
        // But we CAN verify that the display:none check does NOT trigger.
        const hiddenAncestor = input.closest('[style*="display: none"]');
        expect(hiddenAncestor).toBe(null);
    });

    it('skips disabled inputs regardless of visibility', () => {
        container.innerHTML = `
            <div class="vb-form-group">
                <input type="text" id="disabled_field" class="vb-input" required disabled value="">
            </div>
        `;
        const input = container.querySelector('#disabled_field');
        expect(shouldSkipInput(input)).toBe(true);
    });

    it('does not skip inputs when Alpine x-show is expanded (no display:none)', () => {
        container.innerHTML = `
            <div class="vb-owner-section">
                <div>
                    <input type="email" id="owner_email_visible" class="vb-input" required value="">
                </div>
            </div>
        `;
        const input = container.querySelector('#owner_email_visible');
        const hiddenAncestor = input.closest('[style*="display: none"]');
        expect(hiddenAncestor).toBe(null);
        // Input is not disabled
        expect(input.disabled).toBe(false);
    });

    it('correctly identifies nested display:none (section inside section)', () => {
        container.innerHTML = `
            <div style="display: none;">
                <div class="vb-form-group">
                    <div>
                        <input type="text" id="deeply_hidden" class="vb-input" required value="">
                    </div>
                </div>
            </div>
        `;
        const input = container.querySelector('#deeply_hidden');
        expect(shouldSkipInput(input)).toBe(true);
    });

    it('handles the exact Alpine x-show pattern: style="display: none;"', () => {
        // Alpine sets style="display: none;" with the semicolon
        container.innerHTML = `
            <div x-show="enabled" x-transition.duration.200ms style="display: none;">
                <input type="email" id="alpine_hidden" class="vb-input" required value="invalid">
            </div>
        `;
        const input = container.querySelector('#alpine_hidden');
        expect(shouldSkipInput(input)).toBe(true);
    });
});
