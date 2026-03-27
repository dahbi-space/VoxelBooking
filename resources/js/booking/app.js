/**
 * VoxelBooking — Booking Flow Orchestrator
 *
 * Per PRD §V and .ai/11 §6.4:
 * - Vanilla JS, no framework
 * - Step-based flow with animated transitions
 * - State held in a plain object
 * - All API calls via fetch to /api/{slug}/...
 *
 * Architecture:
 *   window.__VB_CONFIG__  → tenant config (injected by PHP)
 *   window.__VB_TS__      → page load timestamp (anti-spam)
 */

const config = window.__VB_CONFIG__;
const apiBase = `/api/${config.slug}`;
const flowEl = document.getElementById('vb-book-flow');
const appEl = document.getElementById('vb-book-app');

// ── Toast Notification System ──
function showToast(message, { type = 'error', duration = 5000, action = null } = {}) {
  // Remove existing toast
  const existing = document.getElementById('vb-book-toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.id = 'vb-book-toast';
  toast.className = `vb-book-toast vb-book-toast-${type}`;
  toast.setAttribute('role', 'alert');
  toast.setAttribute('aria-live', 'assertive');

  const iconMap = {
    error: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    warn: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
  };

  let html = `<span class="vb-book-toast-icon">${iconMap[type] || iconMap.error}</span>`;
  html += `<span class="vb-book-toast-message">${esc(message)}</span>`;

  if (action) {
    html += `<button class="vb-book-toast-action" type="button">${esc(action.label)}</button>`;
  }

  html += `<button class="vb-book-toast-close" type="button" aria-label="Dismiss">`;
  html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
  html += '</button>';

  toast.innerHTML = html;
  appEl.appendChild(toast);

  // Trigger entrance animation
  requestAnimationFrame(() => toast.classList.add('is-visible'));

  // Bind action
  if (action?.onClick) {
    toast.querySelector('.vb-book-toast-action')?.addEventListener('click', () => {
      action.onClick();
      dismissToast(toast);
    });
  }

  // Bind close
  toast.querySelector('.vb-book-toast-close').addEventListener('click', () => dismissToast(toast));

  // Auto-dismiss
  if (duration > 0) {
    setTimeout(() => dismissToast(toast), duration);
  }
}

function dismissToast(el) {
  if (!el || !el.parentNode) return;
  el.classList.remove('is-visible');
  el.classList.add('is-leaving');
  setTimeout(() => el.remove(), 200);
}

// ── State ──
const state = {
  services: [],
  staff: [],
  selectedService: null,
  selectedStaff: null,    // null = "any available"
  selectedDate: null,
  selectedSlot: null,
  availableDates: [],
  availableSlots: [],
  currentMonth: new Date().getMonth(),
  currentYear: new Date().getFullYear(),
  customer: { name: '', email: '', phone: '', notes: '' },
  customFields: {},
  consentGiven: false,
  booking: null,          // confirmation data
};

// ── API ──
async function api(path, options = {}) {
  const url = `${apiBase}${path}`;
  const res = await fetch(url, {
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
    ...options,
  });
  return res.json();
}

// ── Step Rendering ──
function renderStep(html) {
  return new Promise(resolve => {
    const existing = flowEl.querySelector('.vb-book-step');
    if (existing) {
      existing.classList.add('is-exiting');
      setTimeout(() => {
        flowEl.innerHTML = '';
        insertStep(html);
        resolve();
      }, 160);
    } else {
      flowEl.innerHTML = '';
      insertStep(html);
      resolve();
    }
  });
}

function insertStep(html) {
  const step = document.createElement('div');
  step.className = 'vb-book-step';
  step.innerHTML = html;
  flowEl.appendChild(step);

  // Focus first interactive element
  requestAnimationFrame(() => {
    const focusable = step.querySelector('[data-book-focus], input, button, [tabindex="0"]');
    if (focusable) focusable.focus({ preventScroll: true });
  });
}

function hideLoading() {
  const loading = document.getElementById('vb-book-loading');
  if (loading) loading.style.display = 'none';
}

// ── Format helpers ──
function formatPrice(price, currency) {
  if (price === null || price === undefined) return '';
  const num = parseFloat(price);
  if (isNaN(num)) return '';
  try {
    return new Intl.NumberFormat(config.locale || 'en', {
      style: 'currency', currency: currency || config.currency || 'EUR',
    }).format(num);
  } catch { return `€${num.toFixed(2)}`; }
}

function formatDuration(minutes) {
  if (minutes >= 60) {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m > 0 ? `${h}h ${m}min` : `${h}h`;
  }
  return `${minutes} min`;
}

function formatDate(dateStr) {
  const d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString(config.locale || 'en', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
  });
}

function initials(name) {
  return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
}

// ════════════════════════════════════════════════════════════════════════
// STEPS
// ════════════════════════════════════════════════════════════════════════

// ── Step 1: Service Selection ──
async function stepService() {
  const data = await api('/services');
  state.services = data.services || [];

  if (state.services.length === 0) {
    await renderStep(`
      <div class="vb-book-step-header">
        <div class="vb-book-step-title">No services available</div>
        <div class="vb-book-step-subtitle">This business has not configured any services yet.</div>
      </div>
    `);
    return;
  }

  // If only one service, auto-select and skip
  if (state.services.length === 1) {
    state.selectedService = state.services[0];
    stepStaff();
    return;
  }

  const cards = state.services.map(s => `
    <div class="vb-book-service-card" data-book-service="${s.id}" role="radio" tabindex="0"
         aria-checked="false" aria-label="${s.name}">
      <div class="vb-book-service-info">
        <div class="vb-book-service-name">${esc(s.name)}</div>
        <div class="vb-book-service-meta">
          <span>${formatDuration(s.duration_minutes)}</span>
          ${s.description ? `<span>·</span>` : ''}
        </div>
        ${s.description ? `<div class="vb-book-service-desc">${esc(s.description)}</div>` : ''}
      </div>
      ${s.price !== null ? `<div class="vb-book-service-price">${s.price_label || formatPrice(s.price)}</div>` : ''}
    </div>
  `).join('');

  await renderStep(`
    <div class="vb-book-step-header">
      <div class="vb-book-step-title">Choose a service</div>
    </div>
    <div class="vb-book-service-list" role="radiogroup" aria-label="Services">${cards}</div>
  `);

  // Bind clicks
  flowEl.querySelectorAll('[data-book-service]').forEach(el => {
    const handler = () => {
      const id = el.dataset.bookService;
      state.selectedService = state.services.find(s => s.id === id);

      // Visual feedback
      flowEl.querySelectorAll('[data-book-service]').forEach(c => {
        c.classList.remove('is-selected');
        c.setAttribute('aria-checked', 'false');
      });
      el.classList.add('is-selected');
      el.setAttribute('aria-checked', 'true');

      // Advance after brief pause for selection feel
      setTimeout(() => stepStaff(), 200);
    };
    el.addEventListener('click', handler);
    el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handler(); }});
  });
}

// ── Step 2: Staff Selection ──
async function stepStaff() {
  const serviceId = state.selectedService?.id;
  const params = serviceId ? `?service_id=${serviceId}` : '';
  const data = await api(`/staff${params}`);
  state.staff = data.staff || [];

  // Skip if no staff or only one
  if (state.staff.length <= 1) {
    state.selectedStaff = state.staff[0] || null;
    stepDate();
    return;
  }

  // "Any available" + staff cards
  const anyCard = `
    <div class="vb-book-staff-card is-selected" data-book-staff="" role="radio" tabindex="0"
         aria-checked="true" aria-label="Any available">
      <div class="vb-book-staff-avatar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <div class="vb-book-staff-name">Any available</div>
    </div>
  `;

  const staffCards = state.staff.map(s => `
    <div class="vb-book-staff-card" data-book-staff="${s.id}" role="radio" tabindex="0"
         aria-checked="false" aria-label="${s.name}">
      <div class="vb-book-staff-avatar">
        ${s.avatar_path
          ? `<img src="/uploads/${config.slug}/${s.avatar_path}" alt="${esc(s.name)}">`
          : initials(s.name)}
      </div>
      <div class="vb-book-staff-name">${esc(s.name)}</div>
      ${s.title ? `<div class="vb-book-staff-title">${esc(s.title)}</div>` : ''}
    </div>
  `).join('');

  await renderStep(`
    <div class="vb-book-step-header">
      <div class="vb-book-step-title">Choose a stylist</div>
      <div class="vb-book-step-subtitle">Or let us pick whoever is available first.</div>
    </div>
    <div class="vb-book-staff-grid" role="radiogroup" aria-label="Staff">${anyCard}${staffCards}</div>
  `);

  flowEl.querySelectorAll('[data-book-staff]').forEach(el => {
    const handler = () => {
      const id = el.dataset.bookStaff;
      state.selectedStaff = id ? state.staff.find(s => s.id === id) : null;

      flowEl.querySelectorAll('[data-book-staff]').forEach(c => {
        c.classList.remove('is-selected');
        c.setAttribute('aria-checked', 'false');
      });
      el.classList.add('is-selected');
      el.setAttribute('aria-checked', 'true');

      setTimeout(() => stepDate(), 200);
    };
    el.addEventListener('click', handler);
    el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handler(); }});
  });
}

// ── Step 3: Date Selection ──
async function stepDate() {
  await loadAvailableDates();
  renderCalendar();
}

async function loadAvailableDates() {
  const params = new URLSearchParams({
    year: state.currentYear,
    month: state.currentMonth + 1,
  });
  if (state.selectedService) params.set('service_id', state.selectedService.id);
  if (state.selectedStaff) params.set('staff_id', state.selectedStaff.id);

  const data = await api(`/available-dates?${params}`);
  state.availableDates = data.dates || [];
}

async function renderCalendar() {
  const year = state.currentYear;
  const month = state.currentMonth;
  const today = new Date();
  const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

  const monthName = new Date(year, month, 1).toLocaleDateString(config.locale || 'en', { month: 'long', year: 'numeric' });
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const firstDay = (new Date(year, month, 1).getDay() + 6) % 7; // Mon=0

  const dayNames = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
  const dayHeaders = dayNames.map(d => `<div class="vb-book-calendar-dayname">${d}</div>`).join('');

  let cells = '';
  // Empty cells before first day
  for (let i = 0; i < firstDay; i++) {
    cells += '<div class="vb-book-calendar-cell is-disabled"></div>';
  }

  for (let day = 1; day <= daysInMonth; day++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const isToday = dateStr === todayStr;
    const hasSlots = state.availableDates.includes(dateStr);
    const isPast = new Date(dateStr) < new Date(new Date().toDateString());
    const isSelected = dateStr === state.selectedDate;

    const classes = [
      'vb-book-calendar-cell',
      isToday ? 'is-today' : '',
      hasSlots ? 'has-slots' : '',
      (!hasSlots || isPast) ? 'is-disabled' : '',
      isSelected ? 'is-selected' : '',
    ].filter(Boolean).join(' ');

    cells += `<div class="${classes}" data-book-date="${dateStr}"
      ${hasSlots && !isPast ? 'tabindex="0" role="gridcell"' : 'role="gridcell" aria-disabled="true"'}
      ${isSelected ? 'aria-selected="true"' : ''}>${day}</div>`;
  }

  // Can go back?
  const canPrev = !(year === today.getFullYear() && month === today.getMonth());

  await renderStep(`
    <div class="vb-book-step-header">
      <div class="vb-book-step-title">Pick a date</div>
    </div>
    <div class="vb-book-calendar" role="grid" aria-label="Calendar">
      <div class="vb-book-calendar-nav">
        <button class="vb-book-calendar-btn" data-book-prev-month ${canPrev ? '' : 'disabled'} aria-label="Previous month">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <span class="vb-book-calendar-month">${monthName}</span>
        <button class="vb-book-calendar-btn" data-book-next-month aria-label="Next month">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 18l6-6-6-6"/></svg>
        </button>
      </div>
      <div class="vb-book-calendar-grid vb-book-calendar-fade">
        ${dayHeaders}
        ${cells}
      </div>
    </div>
    <div id="vb-time-container"></div>
  `);

  // Bind month navigation
  flowEl.querySelector('[data-book-prev-month]')?.addEventListener('click', async () => {
    state.currentMonth--;
    if (state.currentMonth < 0) { state.currentMonth = 11; state.currentYear--; }
    await loadAvailableDates();
    renderCalendarGrid();
  });

  flowEl.querySelector('[data-book-next-month]')?.addEventListener('click', async () => {
    state.currentMonth++;
    if (state.currentMonth > 11) { state.currentMonth = 0; state.currentYear++; }
    await loadAvailableDates();
    renderCalendarGrid();
  });

  bindDateCells();
}

function renderCalendarGrid() {
  const year = state.currentYear;
  const month = state.currentMonth;
  const today = new Date();
  const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
  const monthName = new Date(year, month, 1).toLocaleDateString(config.locale || 'en', { month: 'long', year: 'numeric' });
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const firstDay = (new Date(year, month, 1).getDay() + 6) % 7;
  const canPrev = !(year === today.getFullYear() && month === today.getMonth());

  const dayNames = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
  const dayHeaders = dayNames.map(d => `<div class="vb-book-calendar-dayname">${d}</div>`).join('');

  let cells = '';
  for (let i = 0; i < firstDay; i++) cells += '<div class="vb-book-calendar-cell is-disabled"></div>';
  for (let day = 1; day <= daysInMonth; day++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const isToday = dateStr === todayStr;
    const hasSlots = state.availableDates.includes(dateStr);
    const isPast = new Date(dateStr) < new Date(new Date().toDateString());
    const isSelected = dateStr === state.selectedDate;
    const classes = [
      'vb-book-calendar-cell',
      isToday ? 'is-today' : '', hasSlots ? 'has-slots' : '',
      (!hasSlots || isPast) ? 'is-disabled' : '', isSelected ? 'is-selected' : '',
    ].filter(Boolean).join(' ');
    cells += `<div class="${classes}" data-book-date="${dateStr}"
      ${hasSlots && !isPast ? 'tabindex="0" role="gridcell"' : 'role="gridcell" aria-disabled="true"'}
      ${isSelected ? 'aria-selected="true"' : ''}>${day}</div>`;
  }

  // Update month label
  flowEl.querySelector('.vb-book-calendar-month').textContent = monthName;
  const prevBtn = flowEl.querySelector('[data-book-prev-month]');
  if (prevBtn) prevBtn.disabled = !canPrev;

  // Replace grid with fade
  const grid = flowEl.querySelector('.vb-book-calendar-grid');
  grid.innerHTML = dayHeaders + cells;
  grid.classList.remove('vb-book-calendar-fade');
  void grid.offsetWidth; // Force reflow
  grid.classList.add('vb-book-calendar-fade');

  bindDateCells();

  // Clear time container
  const timeContainer = document.getElementById('vb-time-container');
  if (timeContainer) timeContainer.innerHTML = '';
}

function bindDateCells() {
  flowEl.querySelectorAll('[data-book-date]').forEach(el => {
    if (el.classList.contains('is-disabled')) return;
    const handler = async () => {
      state.selectedDate = el.dataset.bookDate;

      // Update visual state
      flowEl.querySelectorAll('[data-book-date]').forEach(c => {
        c.classList.remove('is-selected');
        c.removeAttribute('aria-selected');
      });
      el.classList.add('is-selected');
      el.setAttribute('aria-selected', 'true');

      // Load time slots
      await loadTimeSlots();
    };
    el.addEventListener('click', handler);
    el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handler(); }});
  });
}

async function loadTimeSlots() {
  const params = new URLSearchParams({ date: state.selectedDate });
  if (state.selectedService) params.set('service_id', state.selectedService.id);
  if (state.selectedStaff) params.set('staff_id', state.selectedStaff.id);

  const data = await api(`/availability?${params}`);
  state.availableSlots = data.slots || [];

  const container = document.getElementById('vb-time-container');
  if (!container) return;

  if (state.availableSlots.length === 0) {
    container.innerHTML = '<div class="vb-book-empty">No available times on this day.</div>';
    return;
  }

  const pills = state.availableSlots.map((s, i) => `
    <div class="vb-book-time-pill" data-book-slot="${s.time}" data-book-staff-id="${s.staff_id || ''}"
         role="radio" tabindex="0" aria-checked="false" aria-label="${s.time}"
         style="animation-delay: ${i * parseInt(getComputedStyle(document.documentElement).getPropertyValue('--vb-stagger') || '40')}ms">
      ${s.time}
    </div>
  `).join('');

  container.innerHTML = `
    <div class="vb-book-time-grid" role="radiogroup" aria-label="Available times">${pills}</div>
  `;

  container.querySelectorAll('[data-book-slot]').forEach(el => {
    const handler = () => {
      const time = el.dataset.bookSlot;
      const staffId = el.dataset.bookStaffId;
      state.selectedSlot = state.availableSlots.find(s => s.time === time);

      // If staff was resolved by slot, update state
      if (staffId && !state.selectedStaff) {
        state.selectedSlot._resolvedStaffId = staffId;
      }

      container.querySelectorAll('[data-book-slot]').forEach(p => {
        p.classList.remove('is-selected');
        p.classList.toggle('is-dimmed', p.dataset.bookSlot !== time);
        p.setAttribute('aria-checked', 'false');
      });
      el.classList.add('is-selected');
      el.classList.remove('is-dimmed');
      el.setAttribute('aria-checked', 'true');

      setTimeout(() => stepDetails(), 250);
    };
    el.addEventListener('click', handler);
    el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handler(); }});
  });
}

// ── Step 4: Customer Details ──
async function stepDetails() {
  const phoneField = config.require_phone ? `
    <div class="vb-book-form-group">
      <label class="vb-book-label" for="vb-phone">Phone <span class="vb-book-required" aria-hidden="true">*</span></label>
      <input class="vb-book-input" id="vb-phone" type="tel" required aria-required="true"
             value="${esc(state.customer.phone)}" placeholder="+31 6 12345678" autocomplete="tel">
    </div>
  ` : '';

  // Custom fields
  const customFieldsHtml = (config.custom_fields || []).map(f => {
    const isRequired = f.required ? `required aria-required="true"` : '';
    const reqStar = f.required ? ' <span class="vb-book-required" aria-hidden="true">*</span>' : '';
    if (f.type === 'textarea') {
      return `
        <div class="vb-book-form-group">
          <label class="vb-book-label" for="vb-cf-${f.key}">${esc(f.label)}${reqStar}</label>
          <textarea class="vb-book-textarea" id="vb-cf-${f.key}" data-book-custom="${f.key}"
                    placeholder="${esc(f.placeholder || '')}" ${isRequired}>${esc(state.customFields[f.key] || '')}</textarea>
        </div>
      `;
    }
    return `
      <div class="vb-book-form-group">
        <label class="vb-book-label" for="vb-cf-${f.key}">${esc(f.label)}${reqStar}</label>
        <input class="vb-book-input" id="vb-cf-${f.key}" type="text" data-book-custom="${f.key}"
               placeholder="${esc(f.placeholder || '')}" value="${esc(state.customFields[f.key] || '')}" ${isRequired}>
      </div>
    `;
  }).join('');

  // Consent
  let consentHtml = '';
  if (config.requires_consent) {
    const consentLabel = config.consent_text || 'I agree to the processing of my personal data for this booking.';
    const policyLink = config.privacy_policy_url
      ? ` <a href="${esc(config.privacy_policy_url)}" target="_blank" rel="noopener">Privacy policy</a>`
      : '';
    consentHtml = `
      <div class="vb-book-consent">
        <input type="checkbox" class="vb-book-consent-checkbox" id="vb-consent"
               ${state.consentGiven ? 'checked' : ''} aria-required="true">
        <label class="vb-book-consent-label" for="vb-consent">
          ${esc(consentLabel)}${policyLink}
        </label>
      </div>
    `;
  }

  await renderStep(`
    <div class="vb-book-step-header">
      <div class="vb-book-step-title">Your details</div>
      <div class="vb-book-step-subtitle">We'll send a confirmation to your email.</div>
    </div>

    <form id="vb-details-form" novalidate>
      <div class="vb-book-form-group">
        <label class="vb-book-label" for="vb-name">Name <span class="vb-book-required" aria-hidden="true">*</span></label>
        <input class="vb-book-input" id="vb-name" type="text" required aria-required="true"
               value="${esc(state.customer.name)}" placeholder="Your name" autocomplete="name"
               data-book-focus>
      </div>

      <div class="vb-book-form-group">
        <label class="vb-book-label" for="vb-email">Email <span class="vb-book-required" aria-hidden="true">*</span></label>
        <input class="vb-book-input" id="vb-email" type="email" required aria-required="true"
               value="${esc(state.customer.email)}" placeholder="you@example.com" autocomplete="email">
      </div>

      ${phoneField}

      <div class="vb-book-form-group">
        <label class="vb-book-label" for="vb-notes">Notes</label>
        <textarea class="vb-book-textarea" id="vb-notes" placeholder="Any special requests?">${esc(state.customer.notes)}</textarea>
      </div>

      ${customFieldsHtml}
      ${consentHtml}

      <div class="vb-book-form-actions">
        <button type="submit" class="vb-book-btn vb-book-btn-primary" id="vb-to-summary">
          <span class="vb-book-btn-text">Review booking</span>
        </button>
        <div class="vb-book-back-link">
          <button type="button" class="vb-book-btn vb-book-btn-ghost" data-book-back-date>← Change date or time</button>
        </div>
      </div>
    </form>
  `);

  // Bind form
  document.getElementById('vb-details-form').addEventListener('submit', e => {
    e.preventDefault();

    // Collect values
    state.customer.name = document.getElementById('vb-name').value.trim();
    state.customer.email = document.getElementById('vb-email').value.trim();

    const phoneEl = document.getElementById('vb-phone');
    if (phoneEl) state.customer.phone = phoneEl.value.trim();

    state.customer.notes = document.getElementById('vb-notes').value.trim();

    // Custom fields
    flowEl.querySelectorAll('[data-book-custom]').forEach(el => {
      state.customFields[el.dataset.bookCustom] = el.value.trim();
    });

    // Consent
    const consentEl = document.getElementById('vb-consent');
    if (consentEl) state.consentGiven = consentEl.checked;

    // Validate
    if (!state.customer.name || !state.customer.email) {
      const nameEl = document.getElementById('vb-name');
      const emailEl = document.getElementById('vb-email');
      if (!state.customer.name) nameEl.classList.add('has-error');
      if (!state.customer.email) emailEl.classList.add('has-error');
      return;
    }

    if (config.requires_consent && !state.consentGiven) {
      const consentCb = document.getElementById('vb-consent');
      consentCb.focus();
      return;
    }

    stepSummary();
  });

  // Back button
  flowEl.querySelector('[data-book-back-date]')?.addEventListener('click', () => stepDate());
}

// ── Step 5: Summary ──
async function stepSummary() {
  const service = state.selectedService;
  const staff = state.selectedStaff;
  const slot = state.selectedSlot;

  const rows = [];
  if (service) {
    rows.push({ label: 'Service', value: service.name });
    if (service.price !== null) {
      rows.push({ label: 'Price', value: service.price_label || formatPrice(service.price) });
    }
  }
  if (staff) {
    rows.push({ label: 'With', value: staff.name });
  }
  rows.push({ label: 'Date', value: formatDate(state.selectedDate) });
  rows.push({ label: 'Time', value: `${slot.time} – ${slot.end_time}` });
  if (service) {
    rows.push({ label: 'Duration', value: formatDuration(service.duration_minutes) });
  }

  const summaryRows = rows.map(r =>
    `<div class="vb-book-summary-row">
      <span class="vb-book-summary-label">${r.label}</span>
      <span class="vb-book-summary-value">${r.value}</span>
    </div>`
  ).join('');

  await renderStep(`
    <div class="vb-book-step-header">
      <div class="vb-book-step-title">Confirm your booking</div>
      <div class="vb-book-step-subtitle">Please review the details below.</div>
    </div>

    <div class="vb-book-summary">${summaryRows}</div>

    <div class="vb-book-form-actions">
      <button class="vb-book-btn vb-book-btn-primary" id="vb-confirm-btn">
        <span class="vb-book-btn-text">Confirm booking</span>
      </button>
      <div class="vb-book-back-link">
        <button type="button" class="vb-book-btn vb-book-btn-ghost" data-book-back-details>← Edit details</button>
      </div>
    </div>
  `);

  document.getElementById('vb-confirm-btn').addEventListener('click', submitBooking);
  flowEl.querySelector('[data-book-back-details]')?.addEventListener('click', () => stepDetails());
}

// ── Submit Booking ──
async function submitBooking() {
  const btn = document.getElementById('vb-confirm-btn');
  btn.classList.add('is-loading');
  btn.disabled = true;

  const slot = state.selectedSlot;
  const startDt = `${state.selectedDate}T${slot.time}:00`;

  const payload = {
    service_id: state.selectedService?.id || null,
    staff_id: state.selectedStaff?.id || slot._resolvedStaffId || null,
    start_datetime: startDt,
    customer: {
      name: state.customer.name,
      email: state.customer.email,
      phone: state.customer.phone || '',
    },
    notes: state.customer.notes || '',
    custom_fields: Object.keys(state.customFields).length > 0 ? state.customFields : null,
    consent_given: state.consentGiven,
    __ts: window.__VB_TS__,
  };

  try {
    const data = await api('/bookings', {
      method: 'POST',
      body: JSON.stringify(payload),
    });

    if (data.error) {
      btn.classList.remove('is-loading');
      btn.disabled = false;

      if (data.error === 'slot_unavailable') {
        showToast(
          data.message || 'This time slot was just taken.',
          {
            type: 'warn',
            duration: 6000,
            action: { label: 'Pick another time', onClick: () => stepDate() },
          }
        );
        setTimeout(() => stepDate(), 3000);
      } else {
        showToast(data.message || 'Something went wrong. Please try again.', { type: 'error' });
      }
      return;
    }

    state.booking = data.booking;
    stepConfirmation();
  } catch (err) {
    btn.classList.remove('is-loading');
    btn.disabled = false;
    showToast('A connection error occurred. Please try again.', { type: 'error', duration: 6000 });
  }
}

// ── Step 6: Confirmation ──
async function stepConfirmation() {
  const b = state.booking;

  const summaryRows = [];
  if (b.service) summaryRows.push({ label: 'Service', value: b.service });
  if (b.staff) summaryRows.push({ label: 'With', value: b.staff });
  summaryRows.push({ label: 'Date', value: formatDate(b.date) });
  summaryRows.push({ label: 'Time', value: `${b.time} – ${b.end_time}` });
  summaryRows.push({ label: 'Duration', value: formatDuration(b.duration) });

  const summaryHtml = summaryRows.map(r =>
    `<div class="vb-book-summary-row">
      <span class="vb-book-summary-label">${r.label}</span>
      <span class="vb-book-summary-value">${r.value}</span>
    </div>`
  ).join('');

  // Google Calendar URL
  const gcalStart = `${b.date.replace(/-/g, '')}T${b.time.replace(':', '')}00`;
  const gcalEnd = `${b.date.replace(/-/g, '')}T${b.end_time.replace(':', '')}00`;
  const gcalTitle = encodeURIComponent(b.service || config.name);
  const gcalUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${gcalTitle}&dates=${gcalStart}/${gcalEnd}`;

  await renderStep(`
    <div class="vb-book-confirmation">
      <div class="vb-book-checkmark-wrap vb-book-confirm-scale">
        <svg class="vb-book-checkmark" viewBox="0 0 64 64">
          <circle class="vb-book-checkmark-circle" cx="32" cy="32" r="28"/>
          <path class="vb-book-checkmark-check" d="M20 33 L28 41 L44 25"/>
        </svg>
      </div>
      <div class="vb-book-confirm-heading">Booking confirmed</div>
      <div class="vb-book-confirm-ref">${b.id}</div>

      <div class="vb-book-confirm-summary">
        <div class="vb-book-summary">${summaryHtml}</div>
      </div>

      <div class="vb-book-confirm-actions">
        <a href="${gcalUrl}" target="_blank" rel="noopener" class="vb-book-btn vb-book-btn-secondary">
          <span class="vb-book-btn-text">Add to Google Calendar</span>
        </a>
      </div>
    </div>
  `);

  // Scroll to top
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Utilities ──
function esc(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// ── Init ──
async function init() {
  hideLoading();
  appEl.classList.add('is-ready');

  if (config.booking_pattern === 'timeslot') {
    stepService();
  } else {
    await renderStep(`
      <div class="vb-book-step-header">
        <div class="vb-book-step-title">Coming soon</div>
        <div class="vb-book-step-subtitle">This booking pattern is not yet available.</div>
      </div>
    `);
  }
}

init();
