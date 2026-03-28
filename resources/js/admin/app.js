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
} from 'lucide';

const ICON_SET = {
    LayoutDashboard, Calendar, User, Users, Settings, LogOut,
    ChevronDown, ChevronRight, ChevronLeft, Plus, Search,
    Bell, Menu, X, Edit, Trash2, Eye, EyeOff,
    Check, AlertCircle, Info, Shield, Key, Mail, Clock,
    Building2, UserPlus, FileText, Download, Upload,
    RefreshCw, MoreVertical, Minus, ExternalLink, Copy, Sun, Moon,
    Palette, Globe, Activity, TrendingUp, BarChart3, Hash,
    Bookmark, Briefcase, ShieldCheck, ScrollText, Server, Zap, HelpCircle, Lock,
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

    init() {
        // Close sidebar on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeSidebar();
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

    toggleTheme() {
        const html = document.documentElement;
        const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('vb-theme', next);
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
