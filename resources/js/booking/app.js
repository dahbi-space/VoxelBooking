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
    User, Users, ExternalLink,
} from 'lucide';

const ICON_SET = {
    ChevronLeft, ChevronRight, ChevronDown, Clock, Globe, Check, X,
    AlertCircle, Info, AlertTriangle, Calendar: CalendarIcon,
    User, Users, ExternalLink,
};

// ── Globals injected by PHP ──
const config = window.__VB_CONFIG__;
const apiBase = `/api/${config.slug}`;
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

// ── Timezone conversion engine ──
// Convert a date+time from one IANA timezone to another.
// Returns { date: 'YYYY-MM-DD', time: 'HH:MM' } in the target timezone.
function convertTime(date, time, fromTz, toTz) {
    if (fromTz === toTz) return { date, time };

    // Build ISO-like string and parse in source timezone
    // We use Intl.DateTimeFormat to resolve the UTC offset, then convert
    const srcParts = new Date(`${date}T${time}:00`);
    // Get the source and target offsets
    const srcOffset = getTimezoneOffsetMinutes(date, time, fromTz);
    const tgtOffset = getTimezoneOffsetMinutes(date, time, toTz);

    // Convert to UTC, then to target
    const utcMs = srcParts.getTime() + srcOffset * 60000;
    const tgtMs = utcMs - tgtOffset * 60000;
    const tgtDate = new Date(tgtMs);

    return {
        date: `${tgtDate.getFullYear()}-${String(tgtDate.getMonth() + 1).padStart(2, '0')}-${String(tgtDate.getDate()).padStart(2, '0')}`,
        time: `${String(tgtDate.getHours()).padStart(2, '0')}:${String(tgtDate.getMinutes()).padStart(2, '0')}`,
    };
}

// Get the UTC offset in minutes for a given date/time in a timezone
// Positive = behind UTC (e.g. +60 for CET = UTC+1)
function getTimezoneOffsetMinutes(date, time, tz) {
    const dtStr = `${date}T${time}:00`;
    const dt = new Date(dtStr);

    // Format in the target timezone to get the local representation
    const formatter = new Intl.DateTimeFormat('en-US', {
        timeZone: tz,
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
        hour12: false,
    });

    const parts = {};
    for (const p of formatter.formatToParts(dt)) {
        parts[p.type] = p.value;
    }

    // Reconstruct what the local time is in that timezone for this UTC instant
    const localStr = `${parts.year}-${parts.month}-${parts.day}T${parts.hour === '24' ? '00' : parts.hour}:${parts.minute}:${parts.second}`;
    const localDt = new Date(localStr);

    // The offset = local interpretation - UTC interpretation
    return (dt.getTime() - localDt.getTime()) / 60000;
}

// Format display time for a slot (in customer's timezone)
function formatSlotDisplay(slotTime, slotDate, tenantTz, customerTz) {
    if (tenantTz === customerTz) return slotTime;
    const converted = convertTime(slotDate, slotTime, tenantTz, customerTz);
    return converted.time;
}

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
    stepTransition: '',

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
    get hasToast() { return !!this.toast; },
    get hasSelectedDate() { return !!this.selectedDate; },
    get hasNoSlots() { return this.availableSlots.length === 0 && !!this.selectedDate; },
    get hasSlots() { return this.availableSlots.length > 0; },
    get isTzMismatch() { return !this.tzMatch; },

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
        } else {
            this.step = 'unsupported';
        }
    },

    // ── Step transitions ──
    goToStep(name) {
        this.stepTransition = 'exit';
        setTimeout(() => {
            this.step = name;
            this.stepTransition = 'enter';
            this.$nextTick(() => {
                createIcons({ icons: ICON_SET });
            });
            // Clear transition class after animation
            setTimeout(() => { this.stepTransition = ''; }, 250);
        }, 160);
    },

    // ── Progress indicator ──
    get progressSteps() {
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
        return this.step !== 'loading' && this.step !== 'confirmed' && this.step !== 'unsupported';
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
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            ...options,
        });
        return res.json();
    },

    // ── Step 1: Services ──
    async loadServices() {
        const data = await this.api('/services');
        this.services = data.services || [];

        if (this.services.length === 0) {
            this.step = 'empty';
            return;
        }

        if (this.services.length === 1) {
            this.selectedService = this.services[0];
            this.loadStaff();
            return;
        }

        this.step = 'service';
        this.$nextTick(() => createIcons({ icons: ICON_SET }));
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
        this.currentMonth--;
        if (this.currentMonth < 0) { this.currentMonth = 11; this.currentYear--; }
        await this.loadDates();
    },

    async nextMonth() {
        this.currentMonth++;
        if (this.currentMonth > 11) { this.currentMonth = 0; this.currentYear++; }
        await this.loadDates();
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
        return rows;
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
        };

        try {
            const data = await this.api('/bookings', {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            if (data.error) {
                this.submitting = false;
                if (data.error === 'slot_unavailable') {
                    this.showToast(data.message || t('errors.slot_taken'), 'warn');
                    setTimeout(() => this.goToStep('date'), 3000);
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

    get gcalUrl() {
        if (!this.booking) return '#';
        const b = this.booking;
        const start = `${b.date.replace(/-/g, '')}T${b.time.replace(':', '')}00`;
        const end = `${b.date.replace(/-/g, '')}T${b.end_time.replace(':', '')}00`;
        const title = encodeURIComponent(b.service || config.name);
        return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&dates=${start}/${end}`;
    },

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
