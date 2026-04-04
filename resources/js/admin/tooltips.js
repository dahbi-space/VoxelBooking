/**
 * VoxelBooking Admin — Custom Tooltip System
 *
 * Automatically intercepts native `title` attributes and replaces them
 * with styled, positioned tooltips. Zero changes required to existing
 * HTML — any element with a `title` attribute gets a custom tooltip.
 *
 * Design:
 *   - Dark pill, 11px Inter medium, compact padding
 *   - 80ms debounce before show, 100ms fade entrance, 60ms fade-out
 *   - CSS arrow, auto-flips above/below, clamped to viewport
 *   - position: fixed + z-index: 10003 (above everything)
 *
 * Adapted from VoxelSite Studio tooltip system.
 */

// ═══════════════════════════════════════════
//  State
// ═══════════════════════════════════════════

let _tooltip = null;
let _arrow = null;
let _activeEl = null;
let _pendingEl = null;
let _hideTimer = null;
let _showTimer = null;
let _initialized = false;

// ═══════════════════════════════════════════
//  Constants
// ═══════════════════════════════════════════

const SHOW_DELAY = 80;
const FADE_IN_DURATION = 100;
const FADE_OUT_DURATION = 60;
const GAP = 5;
const VIEWPORT_PAD = 6;

// ═══════════════════════════════════════════
//  Tooltip element (singleton)
// ═══════════════════════════════════════════

function escapeHTML(str) {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function ensureTooltipEl() {
  if (_tooltip) return;

  const el = document.createElement('div');
  el.className = 'vb-tooltip';
  el.setAttribute('role', 'tooltip');

  const content = document.createElement('span');
  content.className = 'vb-tooltip-content';

  const arrow = document.createElement('span');
  arrow.className = 'vb-tooltip-arrow';

  el.appendChild(content);
  el.appendChild(arrow);
  document.body.appendChild(el);

  _tooltip = el;
  _arrow = arrow;
}

// ═══════════════════════════════════════════
//  Positioning (viewport-relative, fixed)
// ═══════════════════════════════════════════

function positionTooltip(triggerEl) {
  if (!_tooltip) return;

  const triggerRect = triggerEl.getBoundingClientRect();
  const tooltipRect = _tooltip.getBoundingClientRect();

  let top;
  let placement = 'above';

  const spaceAbove = triggerRect.top;
  const spaceBelow = window.innerHeight - triggerRect.bottom;

  if (spaceAbove >= tooltipRect.height + GAP + VIEWPORT_PAD) {
    top = triggerRect.top - tooltipRect.height - GAP;
  } else if (spaceBelow >= tooltipRect.height + GAP + VIEWPORT_PAD) {
    top = triggerRect.bottom + GAP;
    placement = 'below';
  } else if (spaceAbove >= spaceBelow) {
    top = VIEWPORT_PAD;
  } else {
    top = triggerRect.bottom + GAP;
    placement = 'below';
  }

  const triggerCenter = triggerRect.left + triggerRect.width / 2;
  let left = triggerCenter - tooltipRect.width / 2;
  left = Math.max(VIEWPORT_PAD, Math.min(left, window.innerWidth - VIEWPORT_PAD - tooltipRect.width));

  const arrowLeft = triggerCenter - left;
  const clampedArrow = Math.max(8, Math.min(tooltipRect.width - 8, arrowLeft));

  _tooltip.style.top = `${top}px`;
  _tooltip.style.left = `${left}px`;
  _arrow.style.left = `${clampedArrow}px`;

  _tooltip.classList.remove('vb-tooltip--above', 'vb-tooltip--below');
  _tooltip.classList.add(`vb-tooltip--${placement}`);
}

// ═══════════════════════════════════════════
//  Show / Hide
// ═══════════════════════════════════════════

function show(el) {
  const text = el.getAttribute('data-tooltip') || el.getAttribute('title');
  if (!text || !text.trim()) return;

  if (el.hasAttribute('title')) {
    el.setAttribute('data-tooltip', el.getAttribute('title'));
    el.removeAttribute('title');
  }

  if (_hideTimer) { clearTimeout(_hideTimer); _hideTimer = null; }

  _activeEl = el;
  _pendingEl = null;

  ensureTooltipEl();

  _tooltip.querySelector('.vb-tooltip-content').innerHTML = escapeHTML(text.trim());

  _tooltip.classList.remove('vb-tooltip--visible', 'vb-tooltip--hiding');
  _tooltip.style.display = 'flex';
  _tooltip.style.opacity = '0';

  requestAnimationFrame(() => {
    if (_activeEl !== el) return;
    positionTooltip(el);
    _tooltip.classList.add('vb-tooltip--visible');
    _tooltip.style.opacity = '';
  });
}

function hide() {
  if (!_tooltip) return;

  if (_activeEl) {
    restoreTitle(_activeEl);
    _activeEl = null;
  }

  _tooltip.classList.remove('vb-tooltip--visible');
  _tooltip.classList.add('vb-tooltip--hiding');

  _hideTimer = setTimeout(() => {
    if (_tooltip) {
      _tooltip.style.display = 'none';
      _tooltip.classList.remove('vb-tooltip--hiding');
    }
    _hideTimer = null;
  }, FADE_OUT_DURATION);
}

function hideInstant() {
  if (_showTimer) { clearTimeout(_showTimer); _showTimer = null; }
  _pendingEl = null;

  if (_activeEl) {
    restoreTitle(_activeEl);
    _activeEl = null;
  }

  if (_hideTimer) { clearTimeout(_hideTimer); _hideTimer = null; }

  if (_tooltip) {
    _tooltip.style.display = 'none';
    _tooltip.classList.remove('vb-tooltip--visible', 'vb-tooltip--hiding');
  }
}

function restoreTitle(el) {
  if (!el) return;
  const text = el.getAttribute('data-tooltip');
  if (text && !el.hasAttribute('title')) {
    el.setAttribute('title', text);
    el.removeAttribute('data-tooltip');
  }
}

// ═══════════════════════════════════════════
//  Event Handlers
// ═══════════════════════════════════════════

function findTooltipTarget(el) {
  while (el && el !== document.body) {
    if (el.nodeType !== Node.ELEMENT_NODE) { el = el.parentElement; continue; }
    if (el.hasAttribute('data-tooltip-skip')) return null;
    if (el.hasAttribute('title') || el.hasAttribute('data-tooltip')) return el;
    el = el.parentElement;
  }
  return null;
}

function onMouseOver(e) {
  if (e.buttons !== 0) return;

  const target = findTooltipTarget(e.target);

  if (!target) {
    if (_showTimer) { clearTimeout(_showTimer); _showTimer = null; }
    _pendingEl = null;
    if (_activeEl) hide();
    return;
  }

  if (target === _activeEl) return;
  if (target === _pendingEl) return;

  if (_showTimer) { clearTimeout(_showTimer); _showTimer = null; }

  if (_activeEl) {
    restoreTitle(_activeEl);
    _activeEl = null;
    if (_hideTimer) { clearTimeout(_hideTimer); _hideTimer = null; }
    if (_tooltip) {
      _tooltip.style.display = 'none';
      _tooltip.classList.remove('vb-tooltip--visible', 'vb-tooltip--hiding');
    }
  }

  _pendingEl = target;
  _showTimer = setTimeout(() => {
    _showTimer = null;
    if (_pendingEl === target) {
      show(target);
    }
  }, SHOW_DELAY);
}

function onMouseOut(e) {
  const target = findTooltipTarget(e.target);
  if (!target) return;

  if (target === _activeEl) {
    const related = e.relatedTarget;
    if (related && target.contains(related)) return;

    if (_showTimer) { clearTimeout(_showTimer); _showTimer = null; }
    _pendingEl = null;
    hide();
  } else if (target === _pendingEl) {
    if (_showTimer) { clearTimeout(_showTimer); _showTimer = null; }
    _pendingEl = null;
  }
}

// ═══════════════════════════════════════════
//  Public API
// ═══════════════════════════════════════════

export function initTooltips() {
  if (_initialized) return;
  _initialized = true;

  document.addEventListener('mouseover', onMouseOver, { passive: true });
  document.addEventListener('mouseout', onMouseOut, { passive: true });
  document.addEventListener('scroll', () => { if (_activeEl || _pendingEl) hideInstant(); }, { passive: true, capture: true });
  document.addEventListener('keydown', () => { if (_activeEl || _pendingEl) hideInstant(); }, { passive: true });
  document.addEventListener('mousedown', () => { if (_activeEl || _pendingEl) hideInstant(); }, { passive: true });

  window.addEventListener('resize', () => {
    if (_activeEl) hideInstant();
  }, { passive: true });
}
