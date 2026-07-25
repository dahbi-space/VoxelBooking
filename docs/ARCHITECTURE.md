# VoxelBooking Architecture Guide

## Table of Contents
1. [System Overview](#system-overview)
2. [Tech Stack](#tech-stack)
3. [Folder Structure](#folder-structure)
4. [Core Components](#core-components)
5. [Request Flow](#request-flow)
6. [Design Patterns](#design-patterns)

---

## System Overview

VoxelBooking is a self-hosted, multi-tenant booking infrastructure designed for small businesses. It supports four distinct booking patterns within a unified codebase:

- **Timeslot Pattern**: For salons, clinics, consultants (services × staff matrix)
- **Resource Pattern**: For hotels, rentals, co-working (nightly availability)
- **Capacity Pattern**: For restaurants, classes (party size against slot capacity)
- **Event Pattern**: For workshops, concerts, classes (RRULE recurring events, waitlist)

### Core Philosophy
- **No vendor lock-in**: Self-hosted on any PHP/MySQL server
- **Privacy-first**: Zero outbound connections, GDPR-compliant by design
- **Zero dependencies**: No Node.js, Composer, or build steps required for production
- **Multi-tenant isolation**: Each business operates independently with separate databases per feature

---

## Tech Stack

### Backend
| Layer | Technology | Notes |
|-------|-----------|-------|
| **Framework** | Custom PHP 8.3+ micro-framework | No Laravel or Symfony—lean, explicit routing |
| **Database** | MySQL 8.0+ | PDO with prepared statements, no ORM |
| **Routing** | nikic/FastRoute | Fast HTTP routing with middleware pipeline |
| **Email** | PHPMailer | SMTP delivery with retry logic |
| **ID Generation** | robinvdvleuten/ulid | 128-bit ULIDs for privacy-friendly primary keys |
| **Recurring Events** | rlanvin/php-rrule | RFC 5545 RRULE expansion |

### Frontend
| Layer | Technology | Notes |
|-------|-----------|-------|
| **Interactivity** | Alpine.js 3 | Lightweight, performant DOM reactivity |
| **Styling** | Tailwind CSS 4 | Utility-first CSS with design tokens |
| **Icons** | Lucide 1.7+ | Minimal, consistent icon set |
| **Build Tool** | Vite 6 | Fast bundling for admin + booking entry points |
| **Testing** | Vitest | Unit tests for JS utilities |

### Infrastructure
| Component | Technology |
|-----------|-----------|
| **Web Server** | Apache (mod_rewrite) or Nginx |
| **Sessions** | PHP filesystem (8-hour expiry) |
| **Caching** | File-based (no Redis dependency) |
| **Logging** | File-based (storage/logs/) |

---

## Folder Structure

```
app/
├── Bootstrap.php              Application boot sequence
├── Routes.php                 All route definitions with middleware groups
├── Controllers/               Request handlers (route endpoints)
│   ├── Admin/                 Business dashboard (admin panel)
│   ├── Auth/                  Login, logout, password reset, MFA
│   ├── Booking/               Public booking page + API
│   ├── AgentApi/              LLM agent API (experimental)
│   ├── Install/               Installer wizard
│   └── HomeController.php     Root redirect
├── Models/                    Data access objects (query builders)
│   ├── Booking.php            Booking queries
│   ├── Customer.php           Customer queries
│   └── Tenant.php             Tenant queries
├── Engine/                    Core business logic & utilities
│   ├── App.php                Application kernel
│   ├── Router.php             Route dispatcher + middleware pipeline
│   ├── Database.php           PDO wrapper, prepared statements
│   ├── Calculators/
│   │   ├── TimeSlotCalculator.php
│   │   ├── ResourceCalculator.php
│   │   ├── CapacityCalculator.php
│   │   └── EventCalculator.php
│   ├── Services/
│   │   ├── BookingService.php Booking creation with GDPR consent
│   │   ├── CustomerService.php Customer CRUD + anonymization
│   │   └── Mailer.php         SMTP email delivery + templates
│   ├── Auth/
│   │   ├── Auth.php           Session management
│   │   ├── AgentAuth.php      API key authentication
│   │   └── LoginToken.php     Magic link token generation
│   ├── Utilities/
│   │   ├── Ulid.php           ID generation
│   │   ├── Logger.php         File logging
│   │   ├── Validator.php      Form validation
│   │   ├── AuditLog.php       Append-only audit trail
│   │   ├── Locale.php         i18n locale resolution
│   │   ├── Version.php        Semantic versioning
│   │   ├── ImageUpload.php    Logo/avatar handling
│   │   └── ...
│   └── Jobs/
│       ├── ReminderJob.php    Send booking reminders
│       └── RetentionJob.php   GDPR data retention
├── Middleware/                Request interceptors
│   ├── SecurityMiddleware.php Charset, X-Frame-Options, HTTPS redirect
│   ├── InstalledMiddleware.php Redirect to /install if not configured
│   ├── AuthMiddleware.php     Session check (admin routes)
│   ├── CsrfMiddleware.php     CSRF token verification
│   ├── ThrottleMiddleware.php Rate limiting per IP
│   ├── DemoMiddleware.php     Demo mode block/allow
│   └── AgentAuthMiddleware.php API key validation
└── Migrations/                Database schema (001-028)
    ├── 001_create_settings.php
    ├── 002_create_operators.php
    ├── 003_create_tenants.php
    ├── ...
    └── 028_create_business_applications.php

config/
├── locales.php                Locale registry (en, es, de, fr, nl, ...)
└── currencies.php             ISO 4217 code → symbol mapping

lang/
└── en/                        English translations
    ├── admin.php              Admin panel strings
    ├── auth.php               Login/logout strings
    ├── booking.php            Customer booking page strings
    ├── email.php              Email template strings
    ├── validation.php         Form error messages
    └── privacy.php            GDPR/privacy page strings

resources/
├── css/
│   ├── admin.css              Admin panel Tailwind
│   └── booking.css            Booking page Tailwind
└── js/
    ├── admin/
    │   ├── app.js             Admin Alpine app (calendar, forms, modals)
    │   ├── form-validator.js  Client-side form validation
    │   └── tooltips.js        Tooltip interactions
    └── booking/
        ├── app.js             Booking page Alpine app (date picker, slot selector)
        ├── party-size.js      Party size constraints
        └── timezone.js        Timezone detection + display

templates/
├── admin/                     Admin panel views (PHP templates)
│   ├── layout.php             Master layout with topbar
│   ├── dashboard.php          Dashboard + widgets
│   ├── bookings/
│   │   ├── index.php          Booking list with filters
│   │   ├── show.php           Booking detail + actions
│   │   └── create.php         Manual booking creation
│   ├── calendar/
│   │   ├── month.php          Month view
│   │   ├── week.php           Week view
│   │   └── day.php            Day view
│   ├── services/
│   │   ├── index.php          Services list
│   │   ├── create.php         Service creation
│   │   └── edit.php           Service edit
│   ├── staff/
│   │   ├── index.php          Staff list
│   │   ├── create.php         Staff creation
│   │   └── edit.php           Staff edit
│   ├── availability/
│   │   └── index.php          Availability rules per day/staff
│   ├── resources/
│   │   ├── index.php          Resource (room) list
│   │   └── edit.php           Resource edit
│   ├── events/
│   │   ├── index.php          Events list with RRULE
│   │   └── edit.php           Event edit (recurrence + exceptions)
│   ├── settings/
│   │   ├── general.php        Tenant name, timezone, booking rules
│   │   ├── branding.php       Logo, cover image, colors
│   │   ├── bookingpage.php    Heading, description, messages
│   │   ├── notifications.php  Email configuration
│   │   ├── privacy.php        Consent text, data retention
│   │   ├── emails.php         Email template customization
│   │   └── embed.php          Widget embed config
│   ├── customers/
│   │   ├── index.php          Customer list
│   │   └── show.php           Customer detail + booking history
│   └── errors/
│       ├── 404.php            Not found page
│       └── 500.php            Server error page
└── booking/                   Public booking page views
    └── index.php              Booking widget shell (Alpine renders calendar/slots)

public/
├── index.php                  Front controller
├── assets/
│   ├── js/
│   │   ├── admin.js           Compiled admin JS (Vite output)
│   │   └── booking.js         Compiled booking JS (Vite output)
│   └── css/
│       ├── admin.css          Compiled admin CSS
│       └── booking.css        Compiled booking CSS
└── uploads/
    ├── logos/                 Tenant logos
    ├── covers/                Tenant cover images
    └── staff/                 Staff avatars

storage/
├── logs/                      Application logs (date-stamped)
├── sessions/                  PHP session files (8-hour TTL)
├── cache/                     Cache files
└── demo/                      Demo SQLite database (demo.db)

tests/
├── Unit/                      Calculator + model unit tests
│   ├── TimeSlotCalculatorTest.php
│   ├── ResourceCalculatorTest.php
│   ├── CapacityCalculatorTest.php
│   └── ...
└── Integration/               HTTP-level booking flow tests
    ├── TimeslotBookingFlowTest.php
    ├── ResourceBookingFlowTest.php
    ├── CapacityBookingFlowTest.php
    └── EventBookingFlowTest.php

scripts/
├── audit-css-tokens.py        Token hardcoding audit
└── build-dist.sh              Release archive builder
```

---

## Core Components

### 1. Application Kernel (`App.php`)

Boots the application through these steps:
1. Load `.env` file
2. Initialize logger
3. Register error handlers
4. Set timezone
5. Initialize view engine
6. Initialize locale resolver
7. Initialize demo mode

### 2. Router (`Router.php`)

Custom router built on FastRoute:
- Supports route groups with shared middleware
- Middleware pipeline: applied in reverse order (innermost first)
- No reflection—explicit handler specification
- Supports path parameters with type hints

### 3. Database Layer (`Database.php`)

PDO wrapper with prepared statements:
- No query builder—raw SQL with bound parameters
- Connection pooling handled by PDO
- Safe against SQL injection via parameter binding
- Transaction support (not used extensively)

### 4. Availability Calculators

Each booking pattern has a dedicated calculator:

**TimeSlotCalculator**
- Resolves services' duration and staff eligibility
- Queries availability rules by day of week
- Subtracts existing bookings + buffer time
- Applies min_advance_hours and max_advance_days constraints
- Returns array of available slots (HH:MM format)

**ResourceCalculator**
- Per-unit daily availability (check-in/out constraints)
- Multi-day bookings with availability gap checking
- Returns calendar grid of available days

**CapacityCalculator**
- Slot-based capacity with min/max party sizes
- Per-slot remaining seat calculation
- Real-time capacity updates

**EventCalculator**
- RRULE expansion for recurring events
- Exception date handling
- Waitlist when at capacity
- Per-booking spot limits

### 5. Booking Service (`BookingService.php`)

Central booking creation entrypoint:
- GDPR consent evidence capture (stores exact text shown)
- Validates required fields
- Captures source (web, admin, api, embed)
- Logs to audit trail
- Returns booking ID + consent status

### 6. Customer Service (`CustomerService.php`)

Customer management:
- Lookup or create by tenant + email
- Anonymization (name → "Deleted", email → SHA-256 hash, phone → NULL)
- Deletion request handling
- Profile data JSON storage

### 7. Mailer (`Mailer.php`)

Email delivery pipeline:
- SMTP configuration from settings
- Template rendering (PHP views)
- Retry logic with exponential backoff
- Email logging to audit trail

### 8. Authentication

**Session-based (Admin)**
- `Auth::startSession()` initializes PHP session
- `Auth::user()` returns logged-in operator/business user
- Session expires after 8 hours of inactivity

**Magic Link / OTP**
- `LoginToken::generate()` creates time-limited token
- Email delivers verification link or code
- One-time use only

**API Keys (Agent API)**
- `AgentAuth::verifyApiKey()` checks Bearer token
- API keys stored in `api_keys` table (tenant-scoped)
- Rate limiting per key

### 9. Audit Logging (`AuditLog.php`)

Append-only audit trail:
- `AuditLog::log()` records action with context
- Captures user, entity, action type, changes
- Passwords and tokens never logged
- Email addresses stored as SHA-256 prefixes
- Searchable by date range and action type

### 10. Locale Resolution (`Locale.php`)

Multi-locale support with fallback chain:

| Surface | Resolution Chain |
|---------|-----------------|
| **Booking Page** | Business override → browser Accept-Language → business default → `en` |
| **Admin Panel** | Business locale → `APP_LOCALE` env → `en` |
| **Email** | Active locale at send time |

Per-business formatting (validated against whitelist):
- Date format (Y-m-d, d/m/Y, m/d/Y, d-m-Y, d.m.Y)
- Time format (12h, 24h)
- Number format (period, comma, space)
- Week start (0=Sun, 1=Mon, 6=Sat)

---

## Request Flow

### 1. Public Booking Page Request

```
GET /book/{slug}
  ↓
BookingPageController::show()
  ├─ Resolve tenant by slug
  ├─ Resolve locale from Accept-Language
  ├─ Render booking page shell (Alpine app mounts here)
  └─ Return HTML
```

### 2. Slot Availability Request

```
GET /api/{slug}/availability?date=2024-01-15&service_id=...&staff_id=...
  ↓
BookingApiController::availability()
  ├─ Resolve tenant
  ├─ Resolve locale
  ├─ Delegate to appropriate Calculator
  │   ├─ TimeSlotCalculator::getAvailableSlots()
  │   ├─ ResourceCalculator::getAvailableResources()
  │   ├─ CapacityCalculator::getAvailableSlots()
  │   └─ EventCalculator::getUpcomingEvents()
  └─ Return JSON [{ time, end_time, staff_id? }, ...]
```

### 3. Booking Creation Request

```
POST /api/{slug}/bookings
  ├─ Data: { service_id?, staff_id?, resource_id?, event_id?,
             start_datetime, end_datetime, name, email, phone, consent_given, ... }
  ↓
BookingApiController::createBooking()
  ├─ Validate input against tenant rules
  ├─ Resolve or create customer
  ├─ Check availability (re-validate server-side)
  ├─ Call BookingService::createBooking()
  │   ├─ Validate consent requirement
  │   ├─ Insert booking record with consent evidence
  │   ├─ Log to audit trail
  │   └─ Return booking ID
  ├─ Queue confirmation email
  ├─ If send_reminders enabled, create reminder record
  └─ Return JSON { booking_id, ... }
```

### 4. Admin Login Request

```
POST /admin/login
  ├─ Data: { email, password (or code for MFA) }
  ↓
AuthController::login()
  ├─ Query operators/business_users table
  ├─ Verify password (bcrypt)
  ├─ Create PHP session
  ├─ Log to audit trail
  ├─ Redirect to /admin dashboard
  └─ Set vb_session cookie
```

### 5. Admin Booking Creation Request

```
POST /admin/tenants/{tenant_id}/bookings/create
  ├─ Data: { service_id, staff_id, customer_id, start_datetime, end_datetime, ... }
  ↓
BookingsController::tenantStore()
  ├─ Verify auth (admin or business user for this tenant)
  ├─ Validate availability
  ├─ Call BookingService::createBooking() with source='admin'
  │   └─ Skips consent requirement (admin action)
  ├─ Log to audit trail
  └─ Redirect to booking detail
```

---

## Design Patterns

### 1. Service Layer Pattern

Business logic isolated from HTTP concerns:
- `BookingService::createBooking()` handles all booking creation
- `CustomerService::getOrCreate()` handles customer lookup/creation
- Controllers call services, never directly query database

### 2. Repository Pattern (Simplified)

Models provide query methods, no ORM:
- `Booking::find($id)` returns single booking with joins
- `Booking::forTenant($tenantId, ...)` returns paginated results
- `Booking::statusCounts($tenantId)` returns aggregated stats
- No lazy loading, all queries explicit

### 3. Middleware Pipeline Pattern

Request flows through composable middleware:
- `SecurityMiddleware` → headers
- `InstalledMiddleware` → redirect to /install if not configured
- `ThrottleMiddleware` → rate limiting
- `CsrfMiddleware` → token verification
- `AuthMiddleware` → session validation

Each middleware calls `$next($request)` to pass control down the stack.

### 4. Calculator Strategy Pattern

Pluggable availability calculation per pattern:
- Controller calls appropriate calculator based on `booking_pattern`
- Calculators return consistent format (array of available slots/resources)
- Easy to extend with new patterns

### 5. Tenant Isolation

Every query includes tenant_id:
- Foreign keys ensure cross-tenant data leakage is impossible
- Session-based auth includes tenant context
- Business users see only their tenant's data

### 6. Audit-First Design

Every state-changing action is logged:
- `AuditLog::log()` called after every INSERT/UPDATE/DELETE
- Immutable append-only table
- Enables GDPR data access requests
- Enables audit compliance reports

---

## Key Architectural Decisions

### No ORM
- Explicit SQL with prepared statements is faster and more transparent
- Query complexity is visible to developers
- No magic N+1 queries or lazy-load surprises
- Easier to optimize specific query hot paths

### Micro-framework
- No unnecessary dependencies or abstraction layers
- Direct control over routing, middleware, and database access
- Smaller surface area for security vulnerabilities
- Easier to understand the entire codebase

### File-based Sessions
- No Redis dependency
- Session directory must be writable and non-web-accessible
- Sessions expire after 8 hours (server-side)

### ULID Primary Keys
- 128-bit random identifiers (vs 64-bit auto-increment)
- No sequential IDs leaked to customers (privacy)
- Sortable by creation timestamp
- No collision risk in distributed systems

### GDPR-First Consent
- Consent captured with exact text shown (legal evidence)
- Changing consent text does not retroactively alter past consents
- Consent records are legal-hold data (never anonymized or deleted)
- Anonymization clears PII but preserves booking structure

---

## Extension Points for New Features

1. **New Booking Pattern**: Create `[Pattern]Calculator.php` in Engine/
2. **New Locale**: Add entry to `config/locales.php`, create `lang/{locale}/` files
3. **Custom Field Data**: Stored as JSON in `bookings.custom_field_data` and `customers.profile_data`
4. **Webhooks**: Add `webhooks` table, call from `BookingService::createBooking()`, `Mailer::send()`
5. **Payment Integration**: Add payment table, call gateway before `BookingService::createBooking()`
6. **SMS Notifications**: Extend `Mailer` class to support Twilio/Plivo

---

## Performance Considerations

### Database Indexing
All heavy queries are indexed:
- `bookings_tenant_pattern_status_idx` for tenant + pattern + status filters
- `bookings_tenant_status_start_idx` for calendar queries
- `availability_tenant_day_idx` for availability lookups
- `service_staff_idx` for staff-service association lookups

### Caching Strategy
- No distributed cache (Redis not required)
- File-based cache in `storage/cache/`
- Invalidated manually after updates
- Booking page renders without session (0 latency cookie overhead)

### Query Optimization
- Prepared statements (no query parsing per request)
- JOIN operations for related data (vs N+1 queries)
- LIMIT/OFFSET for pagination
- No wildcard prefix searches (expensive)

### Scaling Horizontal
- Stateless design enables load balancing
- Session files can be shared via NFS
- Database is single source of truth
- No in-process caches to reconcile across instances
