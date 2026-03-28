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
} from 'lucide';

const ICON_SET = {
    LayoutDashboard, Calendar, CalendarDays, CalendarCheck,
    User, Users, Settings, LogOut,
    ChevronDown, ChevronRight, ChevronLeft, Plus, Search,
    Bell, Menu, X, Edit, Pencil, Trash2, Eye, EyeOff,
    Check, AlertCircle, Info, Shield, Key, Mail, Clock,
    Building2, UserPlus, FileText, Download, Upload,
    RefreshCw, MoreVertical, Minus, ExternalLink, Copy, Sun, Moon,
    Palette, Globe, Activity, TrendingUp, BarChart3, Hash,
    Bookmark, Briefcase, ShieldCheck, ScrollText, Server, Zap, HelpCircle, Lock,
    UserCog, Layers, Filter, Award, Archive, RotateCcw, CheckCircle,
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
