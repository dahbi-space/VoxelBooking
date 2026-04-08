/**
 * Admin form validator — core logic.
 *
 * Exported for both production use (admin/app.js) and test coverage.
 * If you change these functions, the regression tests in
 * tests/js/form-validator.test.js must still pass.
 */

/**
 * Returns true if the input should be SKIPPED during validation.
 * An input is skipped if it is disabled, or hidden inside a
 * display:none ancestor (e.g. a collapsed Alpine x-show section).
 */
export function shouldSkipInput(input) {
    if (input.disabled) return true;
    // Check for explicit display:none ancestor (Alpine x-show sets this)
    const hiddenAncestor = input.closest('[style*="display: none"]');
    if (hiddenAncestor) return true;
    // offsetParent is null for any element inside a hidden ancestor.
    // Skip unless it's a type="hidden" (those are always invisible).
    if (input.offsetParent === null && input.type !== 'hidden') return true;
    return false;
}

/**
 * Clear the error state from a field: remove .is-invalid and its
 * .vb-form-error sibling.
 */
export function clearFieldError(input) {
    input.classList.remove('is-invalid');
    const group = input.closest('.vb-form-group') || input.closest('.vb-settings-field');
    if (!group) return;
    const err = group.querySelector('.vb-form-error');
    if (err) err.remove();
}

/**
 * Mark a field as invalid: add .is-invalid and inject a
 * .vb-form-error message below it.
 */
export function markFieldError(input, message) {
    input.classList.add('is-invalid');
    const group = input.closest('.vb-form-group') || input.closest('.vb-settings-field');
    if (!group) return;
    // Prevent duplicate error messages
    if (group.querySelector('.vb-form-error')) return;
    const err = document.createElement('div');
    err.className = 'vb-form-error';
    err.setAttribute('role', 'alert');
    err.textContent = message;
    // Insert after the input (or after its wrapper)
    const anchor = input.closest('.vb-password-field') ||
                   input.closest('.vb-color-field') || input;
    anchor.parentNode.insertBefore(err, anchor.nextSibling);
}

/**
 * Get a human-readable validation message for the field.
 */
export function getErrorMessage(input) {
    if (input.validity.valueMissing) {
        return input.dataset.errorRequired || 'This field is required.';
    }
    if (input.validity.typeMismatch) {
        return input.dataset.errorType || 'Please enter a valid value.';
    }
    if (input.validity.tooShort) {
        return input.dataset.errorMinlength ||
               `At least ${input.minLength} characters required.`;
    }
    if (input.validity.patternMismatch) {
        return input.dataset.errorPattern || 'Please match the expected format.';
    }
    return input.validationMessage || 'Invalid value.';
}

/**
 * Validate all validatable inputs inside a form.
 * Returns the first invalid (and visible) input, or null if all valid.
 */
export function validateForm(form) {
    const inputs = form.querySelectorAll(
        '.vb-input[required], .vb-select[required], ' +
        '.vb-input[type="email"], .vb-input[pattern], ' +
        '.vb-input[minlength]'
    );

    let firstInvalid = null;

    inputs.forEach((input) => {
        clearFieldError(input);

        if (shouldSkipInput(input)) return;

        if (!input.checkValidity()) {
            markFieldError(input, getErrorMessage(input));
            if (!firstInvalid) firstInvalid = input;
        }
    });

    return firstInvalid;
}
