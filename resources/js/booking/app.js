/**
 * VoxelBooking — Booking Flow (Alpine.js CSP-safe)
 *
 * Architecture:
 *   Alpine.data('bookingWizard')  → single component for the entire flow
 *   Lucide via data-lucide        → icons rendered after Alpine commits DOM
 *   Timezone conversion           → all display times in customer TZ, storage in tenant TZ
 *
 * CSP-safe rules:
 *   - No inline JS in x-on / x-bind / x-show
 *   - All component logic registered via Alpine.data()
 *   - HTML references methods by name: @click="selectService"
 *   - x-show references computed booleans by name
 */

import Alpine from '@alpinejs/csp';
import { createIcons } from 'lucide';
import {
    ChevronLeft, ChevronRight, ChevronDown, Clock, Globe, Check, X,
    AlertCircle, Info, AlertTriangle, Calendar as CalendarIcon,
    User, Users, ExternalLink, Download,
} from 'lucide';

const ICON_SET = {
    ChevronLeft, ChevronRight, ChevronDown, Clock, Globe, Check, X,
    AlertCircle, Info, AlertTriangle, Calendar: CalendarIcon,
    User, Users, ExternalLink, Download,
};

// ── Globals injected by PHP ──
const config = window.__VB_CONFIG__;
const apiBase = `/api/${config.slug}`;
const csrfToken = window.__VB_CSRF__ || '';
const i18n = window.__VB_I18N__ || {};
const fmt   = window.__VB_FMT__  || {};

// ── Translation helper ──
function t(key, replace = {}) {
    let value = i18n[key] ?? key;
    for (const [k, v] of Object.entries(replace)) {
        value = value.replace(`:${k}`, v);
    }
    return value;
}

// ── HTML escape ──
function esc(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ── Timezone conversion engine (extracted to timezone.js for testability) ──
import { convertTime, getTimezoneOffsetMinutes, formatSlotDisplay } from './timezone.js';

// ── Common timezone list (grouped by continent) ──
const TIMEZONE_GROUPS = [
    { labelKey: 'timezone.group_americas', zones: [
        'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
        'America/Anchorage', 'Pacific/Honolulu', 'America/Phoenix',
        'America/Toronto', 'America/Vancouver', 'America/Mexico_City',
        'America/Bogota', 'America/Lima', 'America/Sao_Paulo', 'America/Argentina/Buenos_Aires',
    ]},
    { labelKey: 'timezone.group_europe', zones: [
        'Europe/London', 'Europe/Dublin', 'Europe/Paris', 'Europe/Berlin',
        'Europe/Amsterdam', 'Europe/Brussels', 'Europe/Madrid', 'Europe/Rome',
        'Europe/Zurich', 'Europe/Vienna', 'Europe/Stockholm', 'Europe/Oslo',
        'Europe/Copenhagen', 'Europe/Helsinki', 'Europe/Warsaw', 'Europe/Prague',
        'Europe/Lisbon', 'Europe/Athens', 'Europe/Bucharest', 'Europe/Moscow',
        'Europe/Istanbul',
    ]},
    { labelKey: 'timezone.group_asia', zones: [
        'Asia/Dubai', 'Asia/Kolkata', 'Asia/Bangkok', 'Asia/Singapore',
        'Asia/Hong_Kong', 'Asia/Shanghai', 'Asia/Tokyo', 'Asia/Seoul',
        'Asia/Jakarta', 'Asia/Karachi', 'Asia/Riyadh', 'Asia/Tehran',
        'Australia/Sydney', 'Australia/Melbourne', 'Australia/Perth',
        'Pacific/Auckland', 'Pacific/Fiji',
    ]},
    { labelKey: 'timezone.group_africa', zones: [
        'Africa/Cairo', 'Africa/Lagos', 'Africa/Johannesburg', 'Africa/Nairobi',
        'Africa/Casablanca', 'Africa/Accra',
    ]},
];

// Human-readable timezone label
function tzLabel(tz) {
    const city = tz.split('/').pop().replace(/_/g, ' ');
    try {
        const now = new Date();
        const offset = new Intl.DateTimeFormat('en-US', {
            timeZone: tz, timeZoneName: 'shortOffset',
        }).formatToParts(now).find(p => p.type === 'timeZoneName')?.value || '';
        return `${city} (${offset})`;
    } catch {
        return city;
    }
}


// ── Alpine: Booking Wizard Component ──
Alpine.data('bookingWizard', () => ({
    // Step management
    step: 'loading',
    calendarFading: false,

    // Data
    services: [],
    staff: [],
    selectedService: null,
    selectedStaff: null,
    selectedDate: null,
    selectedSlot: null,
    availableDates: [],
    availableSlots: [],
    currentMonth: new Date().getMonth(),
    currentYear: new Date().getFullYear(),
    customerName: '',
    customerEmail: '',
    customerPhone: '',
    customerNotes: '',
    customFields: {},
    consentGiven: false,
    booking: null,
    submitting: false,
    formErrors: {},
    policyOpen: false,
    slotAlternatives: [],

    // Resource pattern state
    resources: [],
    selectedResource: null,
    checkInDate: null,
    checkOutDate: null,
    guestCount: 1,
    resourceAvailability: null,
    resourceDates: [],
    checkInMonth: new Date().getMonth(),
    checkInYear: new Date().getFullYear(),

    // CSP-safe setters for x-model (nested property assignment is prohibited)
    setCustomerName(val) { this.customerName = val; },
    setCustomerEmail(val) { this.customerEmail = val; },
    setCustomerPhone(val) { this.customerPhone = val; },
    setCustomerNotes(val) { this.customerNotes = val; },
    setConsentGiven(val) { this.consentGiven = val; },
    setTzSearchQuery(val) { this.tzSearchQuery = val; },

    // Timezone
    customerTz: '',
    tenantTz: config.timezone || 'UTC',
    tzDropdownOpen: false,
    tzSearchQuery: '',

    // Toast
    toast: null,
    toastTimer: null,

    // Config passthrough
    config,

    // ── CSP-safe step visibility (x-show only accepts property/method refs) ──
    isStep(name) { return this.step === name; },
    get isLoading() { return this.step === 'loading'; },
    get isEmpty() { return this.step === 'empty'; },
    get isUnsupported() { return this.step === 'unsupported'; },
    get isServiceStep() { return this.step === 'service'; },
    get isStaffStep() { return this.step === 'staff'; },
    get isDateStep() { return this.step === 'date'; },
    get isDetailsStep() { return this.step === 'details'; },
    get isReviewStep() { return this.step === 'review'; },
    get isConfirmedStep() { return this.step === 'confirmed'; },
    get isResourceStep() { return this.step === 'resource'; },
    get isResourceDateStep() { return this.step === 'resource-date'; },
    get isGuestStep() { return this.step === 'guests'; },
    get hasToast() { return !!this.toast; },
    get hasSelectedDate() { return !!this.selectedDate; },
    get hasNoSlots() { return this.availableSlots.length === 0 && !!this.selectedDate; },
    get hasSlots() { return this.availableSlots.length > 0; },
    get isTzMismatch() { return !this.tzMatch; },
    get isCalendarFading() { return this.calendarFading; },

    // ── Init ──
    init() {
        // Detect browser timezone
        try {
            this.customerTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch {
            this.customerTz = this.tenantTz;
        }

        if (config.booking_pattern === 'timeslot') {
            this.loadServices();
        } else if (config.booking_pattern === 'resource') {
            this.loadResources();
        } else {
            this.step = 'unsupported';
        }
    },

    // ── Step transitions (spec: §6.4 Flow Orchestrator) ──
    // Exit: opacity 0, translateY -8px, 150ms ease-in
    // 50ms gap
    // Enter: opacity 1, translateY 0, 200ms ease-out
    // Focus: move to first interactive element in new step
    goToStep(name) {
        // Flush any stale exit marks from prior transitions
        this.clearExitStates();

        // Mark current visible step as exiting
        const currentStepEl = this.$el.querySelector('.vb-book-step:not([style*="display: none"])');
        if (currentStepEl) {
            currentStepEl.classList.add('is-exiting');
        }

        setTimeout(() => {
            // Remove exit class before step change — the element is about
            // to be hidden by Alpine's x-show, so the class must not persist
            // for when this step is revisited via back navigation.
            if (currentStepEl) {
                currentStepEl.classList.remove('is-exiting');
            }

            this.step = name;
            this.$nextTick(() => {
                createIcons({ icons: ICON_SET });
                // Focus management: move focus to first interactive element
                const newStep = this.$el.querySelector('.vb-book-step:not([style*="display: none"])');
                if (newStep) {
                    const focusTarget = newStep.querySelector(
                        'button:not([disabled]), [role="radio"], input:not([type="hidden"]), a[href], [tabindex="0"]'
                    );
                    if (focusTarget) {
                        focusTarget.focus({ preventScroll: true });
                    }
                }
            });
        }, 200); // 150ms exit + 50ms gap
    },

    // Remove .is-exiting from all step elements. Called before any
    // step assignment to prevent stale exit animation state.
    clearExitStates() {
        this.$el.querySelectorAll('.vb-book-step.is-exiting').forEach(
            el => el.classList.remove('is-exiting')
        );
    },

    // ── Progress indicator ──
    get progressSteps() {
        if (config.booking_pattern === 'resource') {
            return ['resource', 'resource-date', 'guests', 'details', 'review'];
        }
        const steps = ['service'];
        if (this.staff.length > 1) steps.push('staff');
        steps.push('date', 'details', 'review');
        return steps;
    },

    get currentStepIndex() {
        return this.progressSteps.indexOf(this.step);
    },

    isProgressDotActive(i) {
        return i === this.currentStepIndex;
    },

    isProgressDotCompleted(i) {
        return i < this.currentStepIndex;
    },

    get showProgress() {
        return this.step !== 'loading' && this.step !== 'confirmed' && this.step !== 'unsupported' && this.step !== 'empty';
    },

    // ── Timezone ──
    get tzMatch() {
        return this.customerTz === this.tenantTz;
    },

    get tzGroups() {
        const q = this.tzSearchQuery.toLowerCase();
        const resolved = TIMEZONE_GROUPS.map(g => ({
            label: t(g.labelKey),
            zones: q
                ? g.zones.filter(z => z.toLowerCase().includes(q) || tzLabel(z).toLowerCase().includes(q))
                : g.zones,
        }));
        return q ? resolved.filter(g => g.zones.length > 0) : resolved;
    },

    tzGroupLabel(group) {
        return group.label;
    },

    tzDisplayLabel(tz) {
        return tzLabel(tz);
    },

    selectTimezone(tz) {
        this.customerTz = tz;
        this.tzDropdownOpen = false;
        this.tzSearchQuery = '';
        // Re-render time slots if we're on the date step with slots loaded
        if (this.step === 'date' && this.selectedDate) {
            // Slots stay the same, just display changes via reactive binding
        }
    },

    toggleTzDropdown() {
        this.tzDropdownOpen = !this.tzDropdownOpen;
        if (this.tzDropdownOpen) {
            this.$nextTick(() => {
                const input = this.$refs.tzSearch;
                if (input) input.focus();
            });
        }
    },

    closeTzDropdown() {
        this.tzDropdownOpen = false;
        this.tzSearchQuery = '';
    },

    // ── API ──
    async api(path, options = {}) {
        const url = `${apiBase}${path}`;
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            ...options,
        });
        return res.json();
    },

    // ── Step 1: Services ──
    async loadServices() {
        const data = await this.api('/services');
        this.services = data.services || [];

        if (this.services.length === 0) {
            this.clearExitStates();
            this.step = 'empty';
            return;
        }

        if (this.services.length === 1) {
            this.selectedService = this.services[0];
            this.loadStaff();
            return;
        }

        this.clearExitStates();
        this.step = 'service';
        this.$nextTick(() => createIcons({ icons: ICON_SET }));
    },

    // Service card stagger index for animation-delay
    serviceAnimDelay(i) {
        return 'animation-delay:' + (i * 60) + 'ms';
    },

    selectService(service) {
        this.selectedService = service;
        setTimeout(() => this.loadStaff(), 200);
    },

    isServiceSelected(service) {
        return this.selectedService?.id === service.id;
    },

    // ── Step 2: Staff ──
    async loadStaff() {
        const params = this.selectedService ? `?service_id=${this.selectedService.id}` : '';
        const data = await this.api(`/staff${params}`);
        this.staff = data.staff || [];

        if (this.staff.length <= 1) {
            this.selectedStaff = this.staff[0] || null;
            this.loadDates();
            return;
        }

        this.goToStep('staff');
    },

    selectStaff(staff) {
        this.selectedStaff = staff;
        setTimeout(() => this.loadDates(), 200);
    },

    selectAnyStaff() {
        this.selectedStaff = null;
        setTimeout(() => this.loadDates(), 200);
    },

    isStaffSelected(staff) {
        return this.selectedStaff?.id === staff.id;
    },

    isAnyStaffSelected() {
        return !this.selectedStaff;
    },

    staffInitials(name) {
        return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
    },

    // ── Step 3: Date & Time ──
    async loadDates() {
        const params = new URLSearchParams({
            year: this.currentYear,
            month: this.currentMonth + 1,
        });
        if (this.selectedService) params.set('service_id', this.selectedService.id);
        if (this.selectedStaff) params.set('staff_id', this.selectedStaff.id);

        const data = await this.api(`/available-dates?${params}`);
        this.availableDates = data.dates || [];

        if (this.step !== 'date') {
            this.goToStep('date');
        }
    },

    async prevMonth() {
        this.calendarFading = true;
        this.currentMonth--;
        if (this.currentMonth < 0) { this.currentMonth = 11; this.currentYear--; }
        await this.loadDates();
        setTimeout(() => { this.calendarFading = false; }, 180);
    },

    async nextMonth() {
        this.calendarFading = true;
        this.currentMonth++;
        if (this.currentMonth > 11) { this.currentMonth = 0; this.currentYear++; }
        await this.loadDates();
        setTimeout(() => { this.calendarFading = false; }, 180);
    },

    get canPrevMonth() {
        const now = new Date();
        return !(this.currentYear === now.getFullYear() && this.currentMonth === now.getMonth());
    },

    get monthLabel() {
        return new Date(this.currentYear, this.currentMonth, 1)
            .toLocaleDateString(config.locale || 'en', { month: 'long', year: 'numeric' });
    },

    get dayNames() {
        const all = [t('days_short.0'), t('days_short.1'), t('days_short.2'), t('days_short.3'), t('days_short.4'), t('days_short.5'), t('days_short.6')];
        const ws = fmt.week_start ?? 0;
        return [...all.slice(ws), ...all.slice(0, ws)];
    },

    get calendarCells() {
        const year = this.currentYear;
        const month = this.currentMonth;
        const today = new Date();
        const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const weekStart = fmt.week_start ?? 0;
        const rawDay = new Date(year, month, 1).getDay();
        const firstDay = (rawDay - weekStart + 7) % 7;

        const cells = [];

        // Empty cells
        for (let i = 0; i < firstDay; i++) {
            cells.push({ day: '', dateStr: '', disabled: true, today: false, hasSlots: false, selected: false });
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isPast = new Date(dateStr) < new Date(today.toDateString());
            const hasSlots = this.availableDates.includes(dateStr);
            cells.push({
                day,
                dateStr,
                disabled: !hasSlots || isPast,
                today: dateStr === todayStr,
                hasSlots,
                selected: dateStr === this.selectedDate,
            });
        }

        return cells;
    },

    // CSP-safe helpers for template bindings
    cellKey(cell) {
        return cell.dateStr || ('empty-' + cell.day);
    },

    clickDate(cell) {
        if (!cell.disabled && cell.day) {
            this.selectDate(cell.dateStr);
        }
    },

    get dateBackLabel() {
        if (this.dateBackTarget === 'staff') return t('back.change_staff');
        return t('back.change_service');
    },

    fieldLabel(field) {
        return field.label + (field.required ? ' *' : '');
    },

    fieldPlaceholder(field) {
        return field.placeholder || '';
    },

    servicePriceLabel(service) {
        return service.price_label || this.formatPrice(service.price);
    },

    cellTabindex(cell) {
        return cell.disabled ? -1 : 0;
    },

    cellRole(cell) {
        return cell.day ? 'gridcell' : '';
    },

    consentLabel() {
        return config.consent_text || t('form.consent_default');
    },

    slotAnimDelay(i) {
        return 'animation-delay:' + (i * 40) + 'ms';
    },

    hasPrice(service) {
        return service.price !== null;
    },

    isTextarea(field) {
        return field.type === 'textarea';
    },

    isNotTextarea(field) {
        return field.type !== 'textarea';
    },

    hasAvatar(member) {
        return !!member.avatar_path;
    },

    avatarUrl(member) {
        return '/uploads/' + config.slug + '/' + member.avatar_path;
    },

    noAvatar(member) {
        return !member.avatar_path;
    },

    customFieldId(field) {
        return 'vb-cf-' + field.key;
    },

    async selectDate(dateStr) {
        this.selectedDate = dateStr;
        this.selectedSlot = null;
        this.availableSlots = [];

        const params = new URLSearchParams({ date: dateStr });
        if (this.selectedService) params.set('service_id', this.selectedService.id);
        if (this.selectedStaff) params.set('staff_id', this.selectedStaff.id);

        const data = await this.api(`/availability?${params}`);
        this.availableSlots = data.slots || [];
    },

    // Display a slot time in the customer's timezone
    displaySlotTime(slot) {
        return formatSlotDisplay(slot.time, this.selectedDate, this.tenantTz, this.customerTz);
    },

    selectSlot(slot) {
        this.selectedSlot = slot;
        setTimeout(() => this.goToStep('details'), 250);
    },

    isSlotSelected(slot) {
        return this.selectedSlot?.time === slot.time;
    },

    isSlotDimmed(slot) {
        return this.selectedSlot && this.selectedSlot.time !== slot.time;
    },

    // ── Step 4: Details ──
    submitDetails() {
        this.formErrors = {};

        if (!this.customerName.trim()) {
            this.formErrors.name = true;
        }
        if (!this.customerEmail.trim()) {
            this.formErrors.email = true;
        }
        if (config.require_phone && !this.customerPhone.trim()) {
            this.formErrors.phone = true;
        }
        if (config.requires_consent && !this.consentGiven) {
            this.formErrors.consent = true;
        }

        if (Object.keys(this.formErrors).length > 0) return;

        // Collect custom field values from refs
        const customEls = this.$el.querySelectorAll('[data-book-custom]');
        customEls.forEach(el => {
            this.customFields[el.dataset.bookCustom] = el.value.trim();
        });

        this.goToStep('review');
    },

    hasError(field) {
        return !!this.formErrors[field];
    },

    // ── Step 5: Review / Summary ──
    get summaryRows() {
        const rows = [];
        if (this.selectedService) {
            rows.push({ label: t('summary.service_label'), value: this.selectedService.name });
            if (this.selectedService.price !== null) {
                rows.push({ label: t('summary.price_label'), value: this.selectedService.price_label || this.formatPrice(this.selectedService.price) });
            }
        }
        if (this.selectedStaff) {
            rows.push({ label: t('summary.with_label'), value: this.selectedStaff.name });
        }
        rows.push({ label: t('summary.date_label'), value: this.formatDateDisplay(this.selectedDate) });
        if (this.selectedSlot) {
            const displayStart = formatSlotDisplay(this.selectedSlot.time, this.selectedDate, this.tenantTz, this.customerTz);
            const displayEnd   = formatSlotDisplay(this.selectedSlot.end_time, this.selectedDate, this.tenantTz, this.customerTz);
            rows.push({ label: t('summary.time_label'), value: `${displayStart} – ${displayEnd}` });
        }
        if (this.selectedService) {
            rows.push({ label: t('summary.duration_label'), value: this.formatDuration(this.selectedService.duration_minutes) });
        }
        if (!this.tzMatch) {
            rows.push({ label: t('timezone.label'), value: this.tzDisplayLabel(this.customerTz) });
        }

        // Custom field values (only filled fields appear in the summary)
        if (config.custom_fields && config.custom_fields.length) {
            for (const field of config.custom_fields) {
                const value = (this.customFields[field.key] || '').trim();
                if (value) {
                    rows.push({ label: field.label, value });
                }
            }
        }

        return rows;
    },

    // Resource pattern summary
    get resourceSummaryRows() {
        const rows = [];
        if (this.selectedResource) {
            rows.push({ label: t('resource.summary_resource'), value: this.selectedResource.name });
        }
        if (this.checkInDate) {
            rows.push({ label: t('resource.check_in_label'), value: this.formatDateDisplay(this.checkInDate) });
        }
        if (this.checkOutDate) {
            rows.push({ label: t('resource.check_out_label'), value: this.formatDateDisplay(this.checkOutDate) });
        }
        if (this.resourceAvailability) {
            rows.push({ label: t('resource.nights_label'), value: String(this.resourceAvailability.nights) });
            rows.push({ label: t('resource.total_label'), value: this.formatPrice(this.resourceAvailability.total) });
        }
        if (this.guestCount > 1) {
            rows.push({ label: t('resource.guests_label'), value: String(this.guestCount) });
        }

        // Custom field values
        if (config.custom_fields && config.custom_fields.length) {
            for (const field of config.custom_fields) {
                const value = (this.customFields[field.key] || '').trim();
                if (value) {
                    rows.push({ label: field.label, value });
                }
            }
        }
        return rows;
    },

    // Preparation text (service-level, shown as callout on review step)
    get preparationText() {
        return this.selectedService?.preparation_text || '';
    },

    // ── Step 6: Submit ──
    async submitBooking() {
        if (config.is_demo) {
            this.showToast(t('demo_notice') || 'This is a demo — bookings cannot be submitted.', 'error');
            return;
        }

        this.submitting = true;

        const slot = this.selectedSlot;
        const startDt = `${this.selectedDate}T${slot.time}:00`;

        const payload = {
            service_id: this.selectedService?.id || null,
            staff_id: this.selectedStaff?.id || slot.staff_id || null,
            start_datetime: startDt,
            customer: {
                name: this.customerName.trim(),
                email: this.customerEmail.trim(),
                phone: this.customerPhone.trim() || '',
            },
            notes: this.customerNotes.trim() || '',
            custom_fields: Object.keys(this.customFields).length > 0 ? this.customFields : null,
            consent_given: this.consentGiven,
            customer_timezone: this.customerTz,
            __ts: window.__VB_TS__,
            __hp: this.$el.querySelector('[name="__hp"]')?.value || '',
        };

        try {
            const data = await this.api('/bookings', {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            if (data.error) {
                this.submitting = false;
                if (data.error === 'slot_unavailable') {
                    const alts = data.alternatives || [];
                    if (alts.length > 0) {
                        this.slotAlternatives = alts;
                    } else {
                        this.showToast(data.message || t('errors.slot_taken'), 'warn');
                        this.goToStep('date');
                    }
                } else if (data.error === 'max_bookings_exceeded') {
                    this.showToast(data.message || t('errors.generic'), 'error');
                } else {
                    this.showToast(data.message || t('errors.generic'), 'error');
                }
                return;
            }

            this.booking = data.booking;
            this.goToStep('confirmed');
            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        } catch {
            this.submitting = false;
            this.showToast(t('errors.connection'), 'error');
        }
    },

    // Slot-taken recovery: user picks an alternative time
    selectAlternative(alt) {
        this.selectedSlot = { time: alt.time, end_time: alt.end_time, staff_id: alt.staff_id };
        this.slotAlternatives = [];
        // No auto-submit — user must explicitly click "Confirm booking" again
    },

    // Format a slot time for display (used by recovery pills)
    formatSlotTime(time) {
        return formatSlotDisplay(time, this.selectedDate, this.tenantTz, this.customerTz);
    },

    // ── Confirmation ──
    get confirmSummaryRows() {
        if (!this.booking) return [];
        const b = this.booking;
        const rows = [];
        if (b.service) rows.push({ label: t('summary.service_label'), value: b.service });
        if (b.staff) rows.push({ label: t('summary.with_label'), value: b.staff });
        rows.push({ label: t('summary.date_label'), value: this.formatDateDisplay(b.date) });

        const displayStart = formatSlotDisplay(b.time, b.date, this.tenantTz, this.customerTz);
        const displayEnd   = formatSlotDisplay(b.end_time, b.date, this.tenantTz, this.customerTz);
        rows.push({ label: t('summary.time_label'), value: `${displayStart} – ${displayEnd}` });
        rows.push({ label: t('summary.duration_label'), value: this.formatDuration(b.duration) });
        return rows;
    },

    get hasCalendarActions() {
        if (!this.booking) return false;
        const b = this.booking;
        // Timeslot: needs date + time; Resource: needs check_in + check_out
        if (config.booking_pattern === 'resource') return !!(b.check_in && b.check_out);
        return !!(b.date && b.time && b.end_time);
    },

    get gcalUrl() {
        if (!this.booking) return '#';
        const b = this.booking;

        if (config.booking_pattern === 'resource') {
            // All-day event: check_in → check_out
            const start = b.check_in.replace(/-/g, '');
            const end = b.check_out.replace(/-/g, '');
            const title = encodeURIComponent(b.resource || config.name);
            return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&dates=${start}/${end}`;
        }

        const start = `${b.date.replace(/-/g, '')}T${b.time.replace(':', '')}00`;
        const end = `${b.date.replace(/-/g, '')}T${b.end_time.replace(':', '')}00`;
        const title = encodeURIComponent(b.service || config.name);
        return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&dates=${start}/${end}`;
    },

    // Basic client-side .ics export (no VTIMEZONE definition block).
    // Values are escaped per iCalendar text rules but this is not a
    // full RFC 5545 implementation.
    downloadIcs() {
        if (!this.booking) return;
        const b = this.booking;
        const esc = (s) => s.replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\n/g, '\\n');
        const uid = `${b.id}@${config.slug}.voxelbooking`;

        let lines;

        if (config.booking_pattern === 'resource') {
            // All-day event for resource bookings
            const dtStart = b.check_in.replace(/-/g, '');
            const dtEnd = b.check_out.replace(/-/g, '');
            const summary = esc(b.resource || config.name);
            const nights = b.nights || '';
            const description = nights ? esc(`${nights} night stay`) : '';

            lines = [
                'BEGIN:VCALENDAR',
                'VERSION:2.0',
                'PRODID:-//VoxelBooking//EN',
                'CALSCALE:GREGORIAN',
                'METHOD:PUBLISH',
                'BEGIN:VEVENT',
                `UID:${uid}`,
                `DTSTART;VALUE=DATE:${dtStart}`,
                `DTEND;VALUE=DATE:${dtEnd}`,
                `SUMMARY:${summary}`,
                description ? `DESCRIPTION:${description}` : '',
                `DTSTAMP:${new Date().toISOString().replace(/[-:.]/g, '').slice(0, 15)}Z`,
                'END:VEVENT',
                'END:VCALENDAR',
            ];
        } else {
            const pad = (s) => s.replace(/-/g, '').replace(/:/g, '');
            const dtStart = `${pad(b.date)}T${pad(b.time)}00`;
            const dtEnd = `${pad(b.date)}T${pad(b.end_time)}00`;
            const summary = esc(b.service || config.name);
            const description = b.staff ? esc(`With ${b.staff}`) : '';

            lines = [
                'BEGIN:VCALENDAR',
                'VERSION:2.0',
                'PRODID:-//VoxelBooking//EN',
                'CALSCALE:GREGORIAN',
                'METHOD:PUBLISH',
                'BEGIN:VEVENT',
                `UID:${uid}`,
                `DTSTART;TZID=${this.tenantTz}:${dtStart}`,
                `DTEND;TZID=${this.tenantTz}:${dtEnd}`,
                `SUMMARY:${summary}`,
                description ? `DESCRIPTION:${description}` : '',
                `DTSTAMP:${new Date().toISOString().replace(/[-:.]/g, '').slice(0, 15)}Z`,
                'END:VEVENT',
                'END:VCALENDAR',
            ];
        }

        const ics = lines.filter(Boolean).join('\r\n');
        const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `booking-${b.id}.ics`;
        a.click();
        URL.revokeObjectURL(url);
    },

    // Confirmation email-sent message with :email replaced
    // Only shown when the API confirms an email was actually dispatched
    get confirmEmailSent() {
        if (!this.booking?.email_sent) return '';
        return t('confirmed.email_sent').replace(':email', this.customerEmail);
    },

    // Custom confirmation message from tenant config
    get confirmCustomMessage() {
        return config.confirmation_message || '';
    },

    // Affordance flags
    get showReschedule() { return config.allow_rescheduling; },
    get showCancel() { return config.allow_cancellation; },
    get bookingPageUrl() { return `/book/${config.slug}`; },

    // Book another: reload page
    bookAnother() {
        window.location.reload();
    },

    // Cancellation policy
    get hasCancellationPolicy() { return !!config.cancellation_policy; },
    get cancellationPolicyText() { return config.cancellation_policy || ''; },
    togglePolicy() { this.policyOpen = !this.policyOpen; },

    // ── Toast ──
    showToast(message, type = 'error') {
        if (this.toastTimer) clearTimeout(this.toastTimer);
        this.toast = { message, type };
        this.toastTimer = setTimeout(() => { this.toast = null; }, 5000);
    },

    dismissToast() {
        this.toast = null;
        if (this.toastTimer) clearTimeout(this.toastTimer);
    },

    get toastClass() {
        return this.toast ? 'vb-book-toast-' + this.toast.type : '';
    },

    isZoneSelected(zone) {
        return zone === this.customerTz;
    },

    // ── Format helpers ──
    formatPrice(price) {
        const num = parseFloat(price);
        if (isNaN(num)) return '';
        try {
            return new Intl.NumberFormat(config.locale || 'en', {
                style: 'currency', currency: config.currency || 'EUR',
            }).format(num);
        } catch { return `€${num.toFixed(2)}`; }
    },

    formatDuration(minutes) {
        if (!minutes) return '';
        if (minutes >= 60) {
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            return m > 0 ? `${h}${t('duration.hours')} ${m}${t('duration.minutes')}` : `${h}${t('duration.hours')}`;
        }
        return `${minutes} ${t('duration.minutes')}`;
    },

    formatDateDisplay(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString(config.locale || 'en', {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
        });
    },

    // ── Navigation helpers ──
    get showStaffBackLink() {
        return this.services.length > 1;
    },

    get dateBackTarget() {
        if (this.staff.length > 1) return 'staff';
        if (this.services.length > 1) return 'service';
        return null;
    },

    goBack(target) {
        if (target === 'service') this.loadServices();
        else if (target === 'staff') this.loadStaff();
        else if (target === 'date') this.loadDates();
        else if (target === 'details') this.goToStep('details');
        else if (target === 'resource') this.loadResources();
        else if (target === 'resource-date') this.goToStep('resource-date');
        else if (target === 'guests') this.goToStep('guests');
    },

    // ── Resource booking flow ──

    async loadResources() {
        const data = await this.api('/resources');
        this.resources = data.resources || [];

        if (this.resources.length === 0) {
            this.clearExitStates();
            this.step = 'empty';
            return;
        }

        if (this.resources.length === 1) {
            this.selectedResource = this.resources[0];
            this.guestCount = 1;
            this.loadResourceDates();
            return;
        }

        this.clearExitStates();
        this.step = 'resource';
        this.$nextTick(() => createIcons({ icons: ICON_SET }));
    },

    selectResource(resource) {
        this.selectedResource = resource;
        this.guestCount = 1;
        this.checkInDate = null;
        this.checkOutDate = null;
        this.resourceAvailability = null;
        setTimeout(() => this.loadResourceDates(), 200);
    },

    isResourceSelected(resource) {
        return this.selectedResource?.id === resource.id;
    },

    async loadResourceDates() {
        const params = new URLSearchParams({
            year: this.checkInYear,
            month: this.checkInMonth + 1,
        });

        const data = await this.api(`/resources/${this.selectedResource.id}/availability?${params}`);
        this.resourceDates = data.dates || [];
        this.goToStep('resource-date');
    },

    isResourceDateAvailable(dateStr) {
        return this.resourceDates.includes(dateStr);
    },

    selectCheckIn(dateStr) {
        if (!this.isResourceDateAvailable(dateStr)) return;
        this.checkInDate = dateStr;
        this.checkOutDate = null;
        this.resourceAvailability = null;
    },

    selectCheckOut(dateStr) {
        if (!this.isResourceDateAvailable(dateStr)) return;
        if (dateStr <= this.checkInDate) return;
        this.checkOutDate = dateStr;
        this.checkResourceAvailability();
    },

    async checkResourceAvailability() {
        if (!this.checkInDate || !this.checkOutDate) return;

        const data = await this.api(
            `/resources/${this.selectedResource.id}/availability?check_in=${this.checkInDate}&check_out=${this.checkOutDate}&guests=${this.guestCount}`
        );

        if (data.available) {
            this.resourceAvailability = data;
            this.goToStep('guests');
        } else {
            this.resourceAvailability = null;
            this.showToast(t(`resource.error_${data.error}`) || t('errors.generic'), 'warn');
        }
    },

    setGuestCount(val) {
        this.guestCount = Math.max(1, Math.min(parseInt(val) || 1, this.selectedResource?.capacity || 10));
    },

    submitGuests() {
        this.goToStep('details');
    },

    // ── Resource calendar navigation ──

    get resourceMonthLabel() {
        return new Date(this.checkInYear, this.checkInMonth, 1)
            .toLocaleDateString(config.locale || 'en', { month: 'long', year: 'numeric' });
    },

    get canPrevResourceMonth() {
        const now = new Date();
        return !(this.checkInYear === now.getFullYear() && this.checkInMonth === now.getMonth());
    },

    async prevResourceMonth() {
        this.checkInMonth--;
        if (this.checkInMonth < 0) { this.checkInMonth = 11; this.checkInYear--; }
        await this.loadResourceDates();
    },

    async nextResourceMonth() {
        this.checkInMonth++;
        if (this.checkInMonth > 11) { this.checkInMonth = 0; this.checkInYear++; }
        await this.loadResourceDates();
    },

    get resourceCalendarCells() {
        const year = this.checkInYear;
        const month = this.checkInMonth;
        const today = new Date();
        const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const weekStart = fmt.week_start ?? 0;
        const rawDay = new Date(year, month, 1).getDay();
        const firstDay = (rawDay - weekStart + 7) % 7;

        const cells = [];

        for (let i = 0; i < firstDay; i++) {
            cells.push({ day: '', dateStr: '', disabled: true, today: false, hasSlots: false, selected: false, inRange: false });
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isPast = new Date(dateStr) < new Date(today.toDateString());
            const available = this.resourceDates.includes(dateStr);
            const isCheckIn = dateStr === this.checkInDate;
            const isCheckOut = dateStr === this.checkOutDate;
            const inRange = this.checkInDate && this.checkOutDate && dateStr > this.checkInDate && dateStr < this.checkOutDate;

            cells.push({
                day,
                dateStr,
                disabled: !available || isPast,
                today: dateStr === todayStr,
                hasSlots: available,
                selected: isCheckIn || isCheckOut,
                inRange,
            });
        }

        return cells;
    },

    clickResourceDate(cell) {
        if (cell.disabled || !cell.day) return;

        if (!this.checkInDate || (this.checkInDate && this.checkOutDate)) {
            // Start a new selection
            this.selectCheckIn(cell.dateStr);
        } else {
            // Check-in is set, select check-out
            if (cell.dateStr <= this.checkInDate) {
                // Clicked before check-in, reset to this as new check-in
                this.selectCheckIn(cell.dateStr);
            } else {
                this.selectCheckOut(cell.dateStr);
            }
        }
    },

    // ── Pattern-aware submit handler ──
    activeSubmitHandler() {
        if (config.booking_pattern === 'resource') {
            this.submitResourceBooking();
        } else {
            this.submitBooking();
        }
    },

    // Resource pattern: review step uses resourceSummaryRows
    get activeReviewRows() {
        if (config.booking_pattern === 'resource') return this.resourceSummaryRows;
        return this.summaryRows;
    },

    // Pattern-aware back target from details step
    get activeDetailsBackTarget() {
        if (config.booking_pattern === 'resource') return 'guests';
        return 'date';
    },

    async submitResourceBooking() {
        if (config.is_demo) {
            this.showToast(t('demo_notice') || 'This is a demo — bookings cannot be submitted.', 'error');
            return;
        }

        this.submitting = true;

        const payload = {
            resource_id: this.selectedResource.id,
            check_in: this.checkInDate,
            check_out: this.checkOutDate,
            guest_count: this.guestCount,
            customer: {
                name: this.customerName.trim(),
                email: this.customerEmail.trim(),
                phone: this.customerPhone.trim() || '',
            },
            notes: this.customerNotes.trim() || '',
            custom_fields: Object.keys(this.customFields).length > 0 ? this.customFields : null,
            consent_given: this.consentGiven,
            customer_timezone: this.customerTz,
            __ts: window.__VB_TS__,
            __hp: this.$el.querySelector('[name="__hp"]')?.value || '',
        };

        try {
            const data = await this.api('/bookings', {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            if (data.error) {
                this.submitting = false;
                this.showToast(data.message || t('errors.generic'), 'error');
                return;
            }

            this.booking = data.booking;
            this.goToStep('confirmed');
            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        } catch {
            this.submitting = false;
            this.showToast(t('errors.connection'), 'error');
        }
    },

    // Resource confirmation summary
    get resourceConfirmRows() {
        if (!this.booking) return [];
        const b = this.booking;
        const rows = [];
        if (b.resource) rows.push({ label: t('resource.summary_resource'), value: b.resource });
        if (b.check_in) rows.push({ label: t('resource.check_in_label'), value: this.formatDateDisplay(b.check_in) });
        if (b.check_out) rows.push({ label: t('resource.check_out_label'), value: this.formatDateDisplay(b.check_out) });
        if (b.nights) rows.push({ label: t('resource.nights_label'), value: String(b.nights) });
        if (b.total) rows.push({ label: t('resource.total_label'), value: this.formatPrice(b.total) });
        return rows;
    },

    get activeConfirmRows() {
        if (config.booking_pattern === 'resource') return this.resourceConfirmRows;
        return this.confirmSummaryRows;
    },

    // Resource date back target
    get resourceDateBackTarget() {
        if (this.resources.length > 1) return 'resource';
        return null;
    },

    // ── Translation passthrough for templates ──
    t,
    esc,
}));


// ── Alpine: start ──
window.Alpine = Alpine;
Alpine.start();

// ── Lucide: initial render ──
createIcons({ icons: ICON_SET });

// ── Refresh helper for dynamically rendered content ──
window.refreshIcons = () => createIcons({ icons: ICON_SET });
