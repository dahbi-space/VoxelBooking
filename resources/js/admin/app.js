/**
 * VoxelBooking Admin JavaScript
 *
 * Alpine.js — reactive state management
 * Lucide — icon rendering via data-lucide attribute (tree-shaken)
 *
 * Usage in templates:
 *   <i data-lucide="home"></i>
 *   <i data-lucide="settings" class="w-5 h-5 text-zinc-400"></i>
 *
 * To add a new icon: import it below and add it to ICON_SET.
 *
 * Built by Vite, shipped as compiled JS.
 */

import Alpine from 'alpinejs';
import { createIcons } from 'lucide';

// ── Icon Registry (tree-shaken) ──
// Only icons listed here are bundled. Add new icons as needed.
import {
    LayoutDashboard,
    Calendar,
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
} from 'lucide';

const ICON_SET = {
    LayoutDashboard, Calendar, Users, Settings, LogOut,
    ChevronDown, ChevronRight, ChevronLeft, Plus, Search,
    Bell, Menu, X, Edit, Trash2, Eye, EyeOff,
    Check, AlertCircle, Info, Shield, Key, Mail, Clock,
    Building2, UserPlus, FileText, Download, Upload,
    RefreshCw, MoreVertical, ExternalLink, Copy, Sun, Moon,
    Palette, Globe, Activity, TrendingUp, BarChart3, Hash,
    Bookmark, Briefcase, ShieldCheck, ScrollText, Server, Zap,
};

// ── Lucide: initial render ──
document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons: ICON_SET });
});

// ── Refresh helper for Alpine-rendered content ──
window.refreshIcons = () => {
    createIcons({ icons: ICON_SET });
};

// ── Alpine: start ──
window.Alpine = Alpine;

Alpine.start();

