/**
 * VoxelBooking Admin JavaScript
 *
 * Alpine.js (CSP build) — reactive state management without unsafe-eval
 * Lucide — icon rendering via data-lucide attribute (tree-shaken)
 *
 * Usage in templates:
 *   <i data-lucide="home"></i>
 *   <i data-lucide="settings" class="w-5 h-5"></i>
 *
 * Alpine CSP build rules:
 *   - No inline JS expressions in x-on, x-bind, x-show, etc.
 *   - All component logic must be registered via Alpine.data()
 *   - HTML references components by name: x-data="adminShell"
 *   - x-show can reference a property name directly
 *   - @click can reference a method name directly
 *
 * To add a new icon: import it below and add it to ICON_SET.
 *
 * Built by Vite, shipped as compiled JS.
 */

import Alpine from '@alpinejs/csp';
import { createIcons } from 'lucide';

// ── Icon Registry (tree-shaken) ──
// Only icons listed here are bundled. Add new icons as needed.
import {
    LayoutDashboard,
    Calendar,
    CalendarDays,
    CalendarCheck,
    User,
    Users,
    Settings,
    LogOut,
    ChevronDown,
    ChevronRight,
    ChevronLeft,
    Plus,
    Search,
    Bell,
    Menu,
    X,
    Edit,
    Pencil,
    Trash2,
    Eye,
    EyeOff,
    Check,
    AlertCircle,
    AlertTriangle,
    Info,
    Shield,
    Key,
    Mail,
    Clock,
    Building2,
    UserPlus,
    FileText,
    Download,
    Upload,
    RefreshCw,
    MoreVertical,
    Minus,
    ExternalLink,
    Copy,
    Sun,
    Moon,
    Palette,
    Globe,
    Activity,
    TrendingUp,
    BarChart3,
    Hash,
    Bookmark,
    Briefcase,
    ShieldCheck,
    ScrollText,
    Server,
    Zap,
    HelpCircle,
    Lock,
    UserCog,
    Layers,
    Filter,
    Award,
    Archive,
    RotateCcw,
    CheckCircle,
    CalendarX,
    Contact,
    StickyNote,
    ArrowLeft,
    ArrowRight,
    List,
    Save,
    CalendarOff,
    PlusCircle,
    UserCheck,
    UserMinus,
    Ticket,
    Bed,
    Grid3X3,
} from 'lucide';

const ICON_SET = {
    LayoutDashboard, Calendar, CalendarDays, CalendarCheck,
    User, Users, Settings, LogOut,
    ChevronDown, ChevronRight, ChevronLeft, Plus, Search,
    Bell, Menu, X, Edit, Pencil, Trash2, Eye, EyeOff,
    Check, AlertCircle, AlertTriangle, Info, Shield, Key, Mail, Clock,
    Building2, UserPlus, FileText, Download, Upload,
    RefreshCw, MoreVertical, Minus, ExternalLink, Copy, Sun, Moon,
    Palette, Globe, Activity, TrendingUp, BarChart3, Hash,
    Bookmark, Briefcase, ShieldCheck, ScrollText, Server, Zap, HelpCircle, Lock,
    UserCog, Layers, Filter, Award, Archive, RotateCcw, CheckCircle,
    CalendarX, Contact, StickyNote, ArrowLeft, ArrowRight, List, Save,
    CalendarOff, PlusCircle, UserCheck, UserMinus,
    Ticket, Bed, Grid3X3,
};

// ── Alpine: CSP-safe component registration ──
// All interactive admin components are registered here.
// Templates reference by name: x-data="adminShell"
//
// CSP build constraint: x-show and @click accept only property/method names,
// NOT arbitrary JS expressions. So we use x-show="sidebarOpen" (prop ref)
// and @click="toggleSidebar" (method ref).
// For sidebar CSS class toggling, we use $refs in the methods.

Alpine.data('adminShell', () => ({
    sidebarOpen: false,
    profileOpen: false,

    init() {
        // Close all on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeSidebar();
                this.closeProfile();
            }
        });

        // Close profile on click outside
        document.addEventListener('click', (e) => {
            if (this.profileOpen && this.$refs.profileMenu &&
                !this.$refs.profileMenu.contains(e.target)) {
                this.closeProfile();
            }
        });
    },

    toggleSidebar() {
        this.sidebarOpen = !this.sidebarOpen;
        this.$refs.sidebar.classList.toggle('open', this.sidebarOpen);
    },

    closeSidebar() {
        this.sidebarOpen = false;
        this.$refs.sidebar.classList.remove('open');
    },

    toggleProfile() {
        this.profileOpen = !this.profileOpen;
    },

    closeProfile() {
        this.profileOpen = false;
    },

    toggleTheme() {
        const html = document.documentElement;
        const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('vb-theme', next);
    },

    switchTheme() {
        this.toggleTheme();
        this.closeProfile();
    },

    copyBookingUrl(event) {
        const btn = event.currentTarget;
        const url = btn.getAttribute('data-copy-url');
        if (!url) return;

        navigator.clipboard.writeText(url).then(() => {
            btn.classList.add('is-copied');
            setTimeout(() => btn.classList.remove('is-copied'), 1500);
        });
    },
}));

// ── Alpine: Pattern Cards (CSP-safe radio card selection) ──
Alpine.data('patternCards', () => ({
    selected: 'timeslot',

    select(value) {
        this.selected = value;
    },

    isSelected(value) {
        return this.selected === value;
    },
}));

// ── Alpine: Color Sync (swatch ↔ text bidirectional sync) ──
Alpine.data('colorSync', () => ({
    hex: '#2563EB',

    init() {
        // Sync initial value from the color input if present
        if (this.$refs.colorPicker) {
            this.hex = this.$refs.colorPicker.value;
        }
    },

    onPickerChange() {
        this.hex = this.$refs.colorPicker.value;
        if (this.$refs.colorText) {
            this.$refs.colorText.value = this.hex;
        }
    },

    onTextChange() {
        const v = this.$refs.colorText.value;
        if (/^#[0-9a-fA-F]{6}$/.test(v)) {
            this.hex = v;
            if (this.$refs.colorPicker) {
                this.$refs.colorPicker.value = v;
            }
        }
    },
}));

// ── Alpine: Owner Setup (optional first-owner during tenant creation) ──
Alpine.data('ownerSetup', () => ({
    enabled: false,
    showPassword: false,
    smtpConfigured: false,
    sendEmail: false,

    init() {
        this.smtpConfigured = this.$el.dataset.smtpConfigured === '1';
        this.sendEmail = this.smtpConfigured;
    },

    // CSP-safe: referenced as @change="onToggleEnabled"
    onToggleEnabled() {
        this.enabled = !this.enabled;
        if (this.enabled) {
            this.$nextTick(() => {
                const tenantEmail = document.getElementById('tenant_email');
                const ownerEmail = this.$refs.ownerEmail;
                if (tenantEmail && ownerEmail && !ownerEmail.value) {
                    ownerEmail.value = tenantEmail.value;
                }
                this.generatePassword();
                if (window.refreshIcons) window.refreshIcons();
            });
        }
    },

    // CSP-safe: referenced as :value="ownerFormValue"
    get ownerFormValue() {
        return this.enabled ? '1' : '0';
    },

    // CSP-safe: referenced as :disabled="smtpNotConfigured"
    get smtpNotConfigured() {
        return !this.smtpConfigured;
    },

    // CSP-safe: referenced as @change="onToggleSendEmail"
    onToggleSendEmail() {
        this.sendEmail = !this.sendEmail;
    },

    generatePassword() {
        const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let pass = '';
        const arr = new Uint32Array(16);
        crypto.getRandomValues(arr);
        for (let i = 0; i < 16; i++) {
            pass += chars[arr[i] % chars.length];
        }
        if (this.$refs.ownerPassword) {
            this.$refs.ownerPassword.value = pass;
        }
    },

    togglePasswordVisibility() {
        this.showPassword = !this.showPassword;
        if (this.$refs.ownerPassword) {
            this.$refs.ownerPassword.type = this.showPassword ? 'text' : 'password';
        }
    },
}));

// ── Alpine: Invite User (business user invite form) ──
Alpine.data('inviteUser', () => ({
    role: 'owner',
    showPassword: false,
    smtpConfigured: false,
    sendEmail: false,

    init() {
        this.smtpConfigured = this.$el.dataset.smtpConfigured === '1';
        this.sendEmail = this.smtpConfigured;
        this.$nextTick(() => this.generatePassword());
    },

    // CSP-safe: property references for :class
    get isOwner() { return this.role === 'owner'; },
    get isManager() { return this.role === 'manager'; },
    get roleOwnerClass() { return this.role === 'owner' ? 'is-selected' : ''; },
    get roleManagerClass() { return this.role === 'manager' ? 'is-selected' : ''; },

    // CSP-safe: referenced as @change="selectOwner"
    selectOwner() { this.role = 'owner'; },
    selectManager() { this.role = 'manager'; },

    // CSP-safe: referenced as :disabled="smtpNotConfigured"
    get smtpNotConfigured() { return !this.smtpConfigured; },

    // CSP-safe: referenced as @change="onToggleSendEmail"
    onToggleSendEmail() { this.sendEmail = !this.sendEmail; },

    generatePassword() {
        const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let pass = '';
        const arr = new Uint32Array(16);
        crypto.getRandomValues(arr);
        for (let i = 0; i < 16; i++) {
            pass += chars[arr[i] % chars.length];
        }
        if (this.$refs.passwordField) {
            this.$refs.passwordField.value = pass;
        }
    },

    togglePasswordVisibility() {
        this.showPassword = !this.showPassword;
        if (this.$refs.passwordField) {
            this.$refs.passwordField.type = this.showPassword ? 'text' : 'password';
        }
    },
}));

// ── Alpine: Availability Grid (weekly hours editor) ──
Alpine.data('availabilityGrid', () => ({
    days: [],
    dayLabels: [],

    init() {
        const raw = this.$el.dataset.schedule;
        if (raw) {
            try { this.days = JSON.parse(raw); }
            catch { this.days = [[], [], [], [], [], [], []]; }
        } else {
            this.days = [[], [], [], [], [], [], []];
        }

        const labels = this.$el.dataset.dayLabels;
        if (labels) {
            try { this.dayLabels = JSON.parse(labels); }
            catch { this.dayLabels = []; }
        }
    },

    addWindow(day) {
        this.days[day].push({ start: '09:00', end: '17:00' });
        this.$nextTick(() => { if (window.refreshIcons) window.refreshIcons(); });
    },

    removeWindow(day, idx) {
        this.days[day].splice(idx, 1);
    },
}));

// ── Alpine: Blocked Date Scope (scope toggle for tenant vs staff) ──
Alpine.data('blockedDateScope', () => ({
    scope: 'tenant',

    onScopeChange() {
        // Clear both entity selectors when switching scope
        const staffEl = document.getElementById('bd-staff');
        const resourceEl = document.getElementById('bd-resource');
        if (staffEl) staffEl.value = '';
        if (resourceEl) resourceEl.value = '';
    },
}));

// ── Alpine: Booking Create (manual admin booking form) ──
Alpine.data('bookingCreate', () => ({
    slug: '',
    locale: 'en',
    staffMap: {},
    allStaff: [],
    serviceId: '',
    staffId: '',
    date: '',
    time: '',
    slots: [],
    loadingSlots: false,

    get filteredStaff() {
        if (!this.serviceId) return this.allStaff;
        const linked = this.staffMap[this.serviceId];
        if (!Array.isArray(linked)) return [];
        return this.allStaff.filter(m => linked.includes(m.id));
    },

    init() {
        // Hydrate from data attributes
        const el = this.$el;
        this.slug = el.dataset.slug || '';
        this.locale = el.dataset.locale || 'en';

        try { this.staffMap = JSON.parse(el.dataset.staffMap || '{}'); }
        catch { this.staffMap = {}; }

        try { this.allStaff = JSON.parse(el.dataset.allStaff || '[]'); }
        catch { this.allStaff = []; }

        try {
            const old = JSON.parse(el.dataset.old || '{}');
            this.serviceId = old.service_id || '';
            this.staffId = old.staff_id || '';
            this.date = old.date || '';
            this.time = old.time || '';
        } catch { /* no old values */ }

        // Normalize stale staffId
        if (this.staffId && this.serviceId) {
            if (!this.filteredStaff.some(m => m.id === this.staffId)) {
                this.staffId = '';
            }
        }
        if (this.serviceId && this.date) {
            this.fetchSlots();
        }
    },

    formatTime(timeStr) {
        try {
            const [h, m] = timeStr.split(':').map(Number);
            const d = new Date(2000, 0, 1, h, m);
            return d.toLocaleTimeString(this.locale, { hour: '2-digit', minute: '2-digit' });
        } catch {
            return timeStr;
        }
    },

    async onServiceChange() {
        if (this.staffId && !this.filteredStaff.some(m => m.id === this.staffId)) {
            this.staffId = '';
        }
        this.time = '';
        this.slots = [];
        if (this.serviceId && this.date) {
            await this.fetchSlots();
        }
    },

    async onStaffChange() {
        this.time = '';
        this.slots = [];
        if (this.serviceId && this.date) {
            await this.fetchSlots();
        }
    },

    async onDateChange() {
        this.time = '';
        this.slots = [];
        if (this.serviceId && this.date) {
            await this.fetchSlots();
        }
    },

    async fetchSlots() {
        this.loadingSlots = true;
        this.slots = [];

        try {
            const params = new URLSearchParams({
                date: this.date,
                service_id: this.serviceId,
            });
            if (this.staffId) {
                params.set('staff_id', this.staffId);
            }

            const res = await fetch('/api/' + encodeURIComponent(this.slug) + '/availability?' + params);
            if (res.ok) {
                const data = await res.json();
                this.slots = (data.slots || []).map(slot => ({
                    ...slot,
                    label: this.formatTime(slot.time) + ' \u2013 ' + this.formatTime(slot.end_time),
                }));
            }
        } catch (e) {
            console.error('Failed to fetch availability:', e);
        } finally {
            this.loadingSlots = false;
        }
    },
}));

// ── Alpine: start ──
window.Alpine = Alpine;
Alpine.start();

// ── Lucide: initial render ──
// Runs after Alpine has processed the DOM so x-if/x-for content is present.
createIcons({ icons: ICON_SET });

// ── Refresh helper for Alpine-rendered content ──
window.refreshIcons = () => {
    createIcons({ icons: ICON_SET });
};
