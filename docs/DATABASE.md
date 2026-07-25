# VoxelBooking Database Schema

## Table of Contents
1. [Entity-Relationship Diagram](#entity-relationship-diagram)
2. [Core Tables](#core-tables)
3. [Booking Patterns](#booking-patterns)
4. [Relationships](#relationships)
5. [Indexes](#indexes)
6. [Data Types & Constraints](#data-types--constraints)

---

## Entity-Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CORE INFRASTRUCTURE                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────────┐    ┌──────────────────┐                      │
│  │   settings       │    │   operators      │                      │
│  │   (app config)   │    │   (super admins) │                      │
│  └──────────────────┘    └──────────────────┘                      │
│           ▲                       ▲                                 │
│           │                       │                                 │
│           └───────────┬───────────┘                                │
│                       │                                             │
│          (1:N) audit_log (who did what when)                      │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                      TENANT (BUSINESS) CONTEXT                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│           ┌──────────────────────────────────────┐                 │
│           │          tenants                     │                 │
│           │  (one per business, one pattern)    │                 │
│           └──────────────────────────────────────┘                 │
│                         │                                           │
│        ┌────────────────┼────────────────┬─────────────┐           │
│        │                │                │             │           │
│        ▼                ▼                ▼             ▼           │
│   ┌─────────────┐  ┌──────────┐  ┌────────────┐  ┌──────────┐   │
│   │ services    │  │ staff    │  │ resources  │  │ events   │   │
│   │ (timeslot)  │  │          │  │ (resource) │  │ (event)  │   │
│   └─────────────┘  └──────────┘  └────────────┘  └──────────┘   │
│        │                │                │             │          │
│        │                │                │      ┌──────┴─────┐   │
│        │                │                │      │             │   │
│        ▼                ▼                ▼      ▼             ▼   │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │             bookings (unified)                          │   │
│   │  tenant_id, customer_id, booking_pattern,              │   │
│   │  service_id?, staff_id?, resource_id?, event_id?       │   │
│   └─────────────────────────────────────────────────────────┘   │
│        │                                      │                  │
│        ▼                                      ▼                  │
│   ┌──────────────┐                    ┌──────────────┐         │
│   │ customers    │                    │ reminders    │         │
│   │ (per-tenant) │                    │ (scheduled)  │         │
│   └──────────────┘                    └──────────────┘         │
│                                                                 │
│   ┌──────────────────┐  ┌───────────────┐  ┌──────────────┐  │
│   │ business_users   │  │ availability  │  │blocked_dates │  │
│   │ (owners/managers)│  │ (weekly rules)│  │ (blackouts)  │  │
│   └──────────────────┘  └───────────────┘  └──────────────┘  │
│                                                                 │
│   ┌──────────────────┐  ┌───────────────┐                    │
│   │ capacity_slots   │  │ service_staff │                    │
│   │ (capacity)       │  │ (many-to-many)│                    │
│   └──────────────────┘  └───────────────┘                    │
│                                                                 │
│   ┌──────────────────────────────────────┐                    │
│   │ tenant_email_templates               │                    │
│   │ (custom confirmation/reminder emails)│                    │
│   └──────────────────────────────────────┘                    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│              OPERATIONAL & INTEGRATIONS                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌────────────────┐  ┌───────────┐  ┌─────────────────────┐  │
│  │ api_keys       │  │ email_log │  │ rate_limits         │  │
│  │ (agent auth)   │  │ (audit)   │  │ (per-IP throttling) │  │
│  └────────────────┘  └───────────┘  └─────────────────────┘  │
│                                                                 │
│  ┌──────────────────────────────────────┐                     │
│  │ business_applications                │                     │
│  │ (future: multi-tenant approval flow) │                     │
│  └──────────────────────────────────────┘                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Core Tables

### `settings` (System Configuration)
```sql
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key VARCHAR(255) UNIQUE NOT NULL,      -- app_version, installed_at, db_version, ...
    value LONGTEXT,                         -- JSON for complex values
    updated_at DATETIME
);
```
**Purpose**: System-wide configuration (versions, installed status, defaults)
**Key Records**:
- `installed_at`: Timestamp when installer completed
- `app_version`: Semantic version from VERSION file
- `db_version`: Schema version for migrations
- `default_week_start`, `default_time_format`, etc.: Operator defaults for new tenants

---

### `operators` (Super-Administrators)
```sql
CREATE TABLE operators (
    id CHAR(26) NOT NULL PRIMARY KEY,       -- ULID
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,    -- bcrypt
    name VARCHAR(255),
    avatar_path VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```
**Purpose**: Super-admin accounts with system-wide access
**Relationship**: 
- Sees all tenants
- Can create/archive businesses
- Can manage system settings, email, updates
- Can impersonate business users

---

### `tenants` (Businesses)
```sql
CREATE TABLE tenants (
    id CHAR(26) NOT NULL PRIMARY KEY,                      -- ULID
    slug VARCHAR(100) UNIQUE NOT NULL,                     -- URL identifier
    name VARCHAR(255) NOT NULL,                            -- Display name
    email VARCHAR(255) NOT NULL,                           -- Business email
    phone VARCHAR(50),
    timezone VARCHAR(100) NOT NULL DEFAULT 'UTC',
    booking_pattern VARCHAR(20) NOT NULL,                  -- timeslot|resource|capacity|event
    
    -- Localization
    locale VARCHAR(10) NOT NULL DEFAULT 'en',              -- Primary language
    locale_override VARCHAR(10),                           -- Customer-visible override
    week_start TINYINT(1) NOT NULL DEFAULT 1,              -- 0=Sun, 1=Mon, 6=Sat
    time_format VARCHAR(3) NOT NULL DEFAULT '24h',         -- 12h|24h
    date_format VARCHAR(10) NOT NULL DEFAULT 'Y-m-d',      -- PHP date() format
    number_format VARCHAR(10) NOT NULL DEFAULT 'period',   -- period|comma|space
    currency VARCHAR(3) NOT NULL DEFAULT 'EUR',            -- ISO 4217
    
    -- Branding
    brand_color VARCHAR(7) NOT NULL DEFAULT '#2563EB',     -- Hex color
    brand_color_text VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
    show_powered_by TINYINT(1) NOT NULL DEFAULT 1,
    logo_path VARCHAR(500),                                -- /uploads/logos/{ulid}.jpg
    cover_image_path VARCHAR(500),                         -- /uploads/covers/{ulid}.webp
    
    -- Booking Page Content
    booking_page_heading VARCHAR(255),
    booking_page_description TEXT,
    confirmation_message TEXT,
    cancellation_policy TEXT,
    
    -- Booking Rules
    allow_cancellation TINYINT(1) NOT NULL DEFAULT 1,
    cancellation_hours_before INT NOT NULL DEFAULT 24,
    allow_rescheduling TINYINT(1) NOT NULL DEFAULT 1,
    rescheduling_hours_before INT NOT NULL DEFAULT 24,
    min_advance_hours INT NOT NULL DEFAULT 1,              -- Min. 1 hour ahead
    max_advance_days INT NOT NULL DEFAULT 90,              -- Max. 90 days out
    slot_duration_minutes INT NOT NULL DEFAULT 30,         -- Timeslot pattern
    buffer_minutes INT NOT NULL DEFAULT 0,                 -- Timeslot pattern
    max_bookings_per_customer_per_day INT NOT NULL DEFAULT 3,
    booking_requires_approval TINYINT(1) NOT NULL DEFAULT 0,
    require_phone TINYINT(1) NOT NULL DEFAULT 0,
    custom_fields JSON,                                    -- [{ name, type, required }, ...]
    
    -- Notifications
    notification_email VARCHAR(255),
    notify_on_booking TINYINT(1) NOT NULL DEFAULT 1,
    notify_on_cancellation TINYINT(1) NOT NULL DEFAULT 1,
    send_reminders TINYINT(1) NOT NULL DEFAULT 1,
    reminder_hours_before INT NOT NULL DEFAULT 24,
    
    -- Privacy & Compliance
    requires_consent TINYINT(1) NOT NULL DEFAULT 1,
    privacy_policy_url VARCHAR(500),
    consent_text VARCHAR(500),
    data_retention_months INT NOT NULL DEFAULT 24,         -- GDPR: auto-anonymize after N months
    
    -- Embed Widget
    allowed_embed_domains TEXT,                            -- Comma-separated whitelist
    embed_button_position VARCHAR(20) NOT NULL DEFAULT 'bottom-right',
    embed_button_label VARCHAR(50) NOT NULL DEFAULT 'Book Now',
    
    -- Status
    status VARCHAR(20) NOT NULL DEFAULT 'active',          -- active|paused|archived
    meta JSON,                                             -- Extension data
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY tenants_slug_unique (slug),
    KEY tenants_status_idx (status),
    KEY tenants_pattern_idx (booking_pattern)
);
```
**Purpose**: Each tenant = one business with independent booking page and settings
**Key Insight**: One pattern per tenant (decided at creation). All bookings for tenant share same pattern.

---

### `audit_log` (Immutable Audit Trail)
```sql
CREATE TABLE audit_log (
    id CHAR(26) NOT NULL PRIMARY KEY,
    operator_id CHAR(26),                          -- NULL if system action
    entity_type VARCHAR(50),                       -- booking, service, staff, tenant, ...
    entity_id CHAR(26),                            -- ULID of affected entity
    action VARCHAR(50),                            -- booking.created, staff.updated, ...
    old_values JSON,                               -- Before state (nullified for passwords)
    new_values JSON,                               -- After state (nullified for passwords)
    changes LONGTEXT,                              -- Human-readable diff
    ip_address VARCHAR(50),
    user_agent VARCHAR(500),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY audit_log_operator_idx (operator_id),
    KEY audit_log_entity_idx (entity_type, entity_id),
    KEY audit_log_action_idx (action),
    KEY audit_log_created_idx (created_at),
    
    FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE SET NULL
);
```
**Purpose**: Append-only audit trail for compliance and security investigations
**Never Logged**: Passwords, API keys, tokens, customer phone (stored as hash)

---

## Booking Patterns

### TIMESLOT PATTERN

**Tables**: `services`, `staff`, `service_staff`, `availability`

#### `services`
```sql
CREATE TABLE services (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,                -- "Haircut", "Massage", etc.
    description TEXT,
    preparation_text TEXT,                     -- Pre-booking instructions
    duration_minutes INT NOT NULL DEFAULT 30,
    duration_options JSON,                     -- [30, 60, 90] allow customer choice
    price DECIMAL(10,2),
    price_label VARCHAR(100),                  -- "From", "Starting at", etc.
    category VARCHAR(100),
    color VARCHAR(7),                          -- Calendar display color
    cover_image_path VARCHAR(500),
    max_per_day INT,
    requires_staff TINYINT(1) NOT NULL DEFAULT 1,
    staff_assignment_strategy VARCHAR(20) NOT NULL DEFAULT 'first_available',  -- first_available|round_robin
    is_virtual TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,         -- Display order
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    meta JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY services_tenant_active_idx (tenant_id, is_active, sort_order),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

#### `staff`
```sql
CREATE TABLE staff (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    title VARCHAR(255),
    bio TEXT,
    avatar_path VARCHAR(500),                  -- /uploads/staff/{ulid}.jpg
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    meta JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY staff_tenant_email_unique (tenant_id, email),
    KEY staff_tenant_active_idx (tenant_id, is_active, sort_order),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

#### `service_staff` (Many-to-Many)
```sql
CREATE TABLE service_staff (
    id CHAR(26) NOT NULL PRIMARY KEY,
    service_id CHAR(26) NOT NULL,
    staff_id CHAR(26) NOT NULL,
    is_preferred TINYINT(1) NOT NULL DEFAULT 0,  -- Prioritize in round-robin
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY service_staff_unique (service_id, staff_id),
    KEY service_staff_staff_idx (staff_id),
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);
```

#### `availability` (Weekly Recurring Rules)
```sql
CREATE TABLE availability (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    staff_id CHAR(26),                          -- NULL = tenant-level default
    day_of_week TINYINT NOT NULL,               -- 0=Mon, 6=Sun (ISO-8601)
    start_time TIME NOT NULL,                   -- 09:00:00
    end_time TIME NOT NULL,                     -- 17:00:00
    is_available TINYINT(1) NOT NULL DEFAULT 1, -- Can set to 0 to mark unavailable
    
    PRIMARY KEY (id),
    KEY availability_tenant_day_idx (tenant_id, day_of_week),
    KEY availability_staff_day_idx (staff_id, day_of_week),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);
```

**Timeslot Booking Flow**:
1. Customer selects service → duration lookup
2. Customer selects date → availability rules for day of week
3. Existing bookings subtracted (+ buffer time)
4. TimeSlotCalculator returns available slots
5. Customer selects time + staff (or staff auto-assigned)
6. Booking created with service_id + staff_id

---

### RESOURCE PATTERN

**Tables**: `resources`

#### `resources`
```sql
CREATE TABLE resources (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,                 -- "Room 101", "Suite A", etc.
    description TEXT,
    price DECIMAL(10,2),                        -- Nightly rate
    max_guests INT,
    capacity INT,                               -- Beds, seats, etc.
    cover_image_path VARCHAR(500),
    sort_order INT NOT NULL DEFAULT 0,
    checkin_allowed_days INT DEFAULT 0,         -- Which days allow check-in (bitmask)
    checkout_allowed_days INT DEFAULT 0,        -- Which days allow check-out (bitmask)
    min_stay_nights INT DEFAULT 1,              -- Minimum booking duration
    max_stay_nights INT DEFAULT 365,            -- Maximum booking duration
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    meta JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY resources_tenant_active_idx (tenant_id, is_active, sort_order),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

**Resource Booking Flow**:
1. Customer selects resource (room)
2. Customer selects check-in and check-out dates
3. ResourceCalculator checks availability (no overlapping bookings)
4. System validates check-in/check-out day restrictions
5. Booking created with resource_id + start_datetime + end_datetime

---

### CAPACITY PATTERN

**Tables**: `capacity_slots`

#### `capacity_slots`
```sql
CREATE TABLE capacity_slots (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    start_datetime DATETIME NOT NULL,          -- Slot start
    end_datetime DATETIME NOT NULL,            -- Slot end
    max_capacity INT NOT NULL,                 -- Total seats for this slot
    min_party_size INT NOT NULL DEFAULT 1,
    max_party_size INT NOT NULL DEFAULT 10,    -- Max per booking
    title VARCHAR(255),
    description TEXT,
    price DECIMAL(10,2),                       -- Per-person rate
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY capacity_slots_tenant_datetime_idx (tenant_id, start_datetime),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

**Capacity Booking Flow**:
1. Customer selects capacity slot (e.g., "Dinner 7:00 PM")
2. Customer selects party size
3. CapacityCalculator validates: min_party_size ≤ party_size ≤ max_party_size
4. Remaining seats = max_capacity - confirmed_bookings_party_size_sum
5. If party_size > remaining_seats: reject
6. Booking created with capacity_slot_id + party_size

---

### EVENT PATTERN

**Tables**: `events`

#### `events`
```sql
CREATE TABLE events (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(200) NOT NULL,                 -- "Yoga Class", "Workshop", etc.
    description TEXT,
    location VARCHAR(255),
    price DECIMAL(10, 2),
    max_participants INT NOT NULL DEFAULT 20,  -- Total capacity
    min_spot_count INT UNSIGNED NOT NULL DEFAULT 1,      -- Min spots per booking
    max_spot_count INT UNSIGNED,                -- Max spots per booking (NULL=unlimited)
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    
    -- Recurring
    is_recurring TINYINT(1) NOT NULL DEFAULT 0,
    rrule VARCHAR(500),                        -- RFC 5545: "FREQ=WEEKLY;BYDAY=MO,WE"
    exception_dates JSON,                      -- ["2024-01-01", "2024-01-15", ...]
    
    -- Waitlist
    allow_waitlist TINYINT(1) NOT NULL DEFAULT 0,
    waitlist_max INT NOT NULL DEFAULT 5,
    
    cover_image_path VARCHAR(500),
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY events_tenant_active_idx (tenant_id, is_active, start_datetime),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

**Event Booking Flow**:
1. Customer selects event (recurring events expanded by EventCalculator)
2. Customer selects spot count (1 to max_spot_count)
3. EventCalculator calculates remaining spots
4. If remaining >= spot_count: status='confirmed'
5. Else if allow_waitlist: status='waitlisted'
6. Else: reject
7. Booking created with event_id + party_size (represents spot count)

---

## Unified Booking Table

All four patterns share one `bookings` table:

### `bookings`
```sql
CREATE TABLE bookings (
    id CHAR(26) NOT NULL PRIMARY KEY,          -- ULID (sortable by time)
    tenant_id CHAR(26) NOT NULL,
    booking_pattern VARCHAR(20) NOT NULL,      -- timeslot|resource|capacity|event
    
    -- Pattern-specific Foreign Keys (1 populated, others NULL)
    service_id CHAR(26),                       -- Timeslot pattern
    staff_id CHAR(26),                         -- Timeslot pattern
    resource_id CHAR(26),                      -- Resource pattern
    event_id CHAR(26),                         -- Event pattern
    
    customer_id CHAR(26) NOT NULL,             -- Always populated
    
    -- Booking Times
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    
    -- Party/Spots
    party_size INT NOT NULL DEFAULT 1,         -- Capacity pattern: group size
                                               -- Event pattern: spot count
    
    -- Status
    status VARCHAR(20) NOT NULL DEFAULT 'confirmed',  -- pending|confirmed|cancelled|
                                                      -- completed|no_show|rescheduled|waitlisted
    rescheduled_to_id CHAR(26),                -- Links to new booking if rescheduled
    
    -- Customer Details
    notes TEXT,                                -- Customer notes
    internal_notes TEXT,                       -- Staff notes (not shown to customer)
    custom_field_data JSON,                    -- Custom field responses
    
    -- GDPR Consent Evidence (LEGAL HOLD — NEVER anonymized or deleted)
    consent_given_at DATETIME,                 -- When consent was given (if at all)
    consent_text_shown VARCHAR(500),           -- Exact text customer agreed to
    
    -- Customer Context
    customer_timezone VARCHAR(100),            -- Browser timezone at booking time
    
    -- Email/Notification State
    confirmation_sent_at DATETIME,
    reminder_sent_at DATETIME,
    
    -- Cancellation
    cancelled_at DATETIME,
    cancellation_reason VARCHAR(255),
    
    -- Audit
    source VARCHAR(10) NOT NULL DEFAULT 'web', -- web|admin|api|embed
    meta JSON,                                 -- Extension data
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    
    -- Indexes (optimized for common queries)
    KEY bookings_tenant_pattern_status_idx (tenant_id, booking_pattern, status),
    KEY bookings_tenant_status_start_idx (tenant_id, status, start_datetime),
    KEY bookings_customer_id_idx (customer_id),
    KEY bookings_staff_start_idx (staff_id, start_datetime),
    KEY bookings_resource_start_idx (resource_id, start_datetime),
    KEY bookings_event_id_idx (event_id),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);
```

### `reminders`
```sql
CREATE TABLE reminders (
    id CHAR(26) NOT NULL PRIMARY KEY,
    booking_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    scheduled_at DATETIME NOT NULL,            -- When to send
    sent_at DATETIME,                          -- NULL if not yet sent
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY reminders_pending_idx (sent_at, scheduled_at),
    KEY reminders_booking_idx (booking_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

---

## Supporting Tables

### `customers` (GDPR-Compliant)
```sql
CREATE TABLE customers (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,                -- Anonymized to "Deleted"
    email VARCHAR(255) NOT NULL,               -- Anonymized to SHA-256 hash
    phone VARCHAR(50),                         -- Anonymized to NULL
    notes TEXT,                                -- Anonymized to NULL
    profile_data JSON,                         -- Customer preferences, anonymized to NULL
    booking_count INT NOT NULL DEFAULT 0,
    last_booking_at DATETIME,
    
    -- Anonymization Status
    is_anonymized TINYINT(1) NOT NULL DEFAULT 0,
    anonymized_at DATETIME,
    deletion_requested_at DATETIME,            -- Self-service deletion request
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY customers_tenant_email_unique (tenant_id, email),
    KEY customers_tenant_id_idx (tenant_id),
    KEY customers_anonymized_idx (is_anonymized),
    KEY customers_deletion_pending_idx (deletion_requested_at, is_anonymized),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### `business_users` (Tenant Staff with Role-Based Access)
```sql
CREATE TABLE business_users (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255),                -- NULL for invitation-only flow
    name VARCHAR(255),
    role VARCHAR(20) NOT NULL,                 -- owner|manager|staff (extensible)
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY business_users_tenant_email_unique (tenant_id, email),
    KEY business_users_tenant_active_idx (tenant_id, is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### `blocked_dates` (Blackout Periods)
```sql
CREATE TABLE blocked_dates (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    staff_id CHAR(26),                         -- NULL = tenant-level
    resource_id CHAR(26),                      -- NULL = tenant-level
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY blocked_dates_tenant_date_idx (tenant_id, start_date, end_date),
    KEY blocked_dates_staff_date_idx (staff_id, start_date, end_date),
    KEY blocked_dates_resource_date_idx (resource_id, start_date, end_date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE
);
```

### `email_log` (Audit Trail for Emails)
```sql
CREATE TABLE email_log (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    booking_id CHAR(26),
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    body_preview TEXT,
    template_used VARCHAR(100),
    status VARCHAR(20),                        -- sent|failed|bounced
    error_message TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY email_log_tenant_idx (tenant_id),
    KEY email_log_booking_idx (booking_id),
    KEY email_log_status_idx (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);
```

### `api_keys` (Agent API Authentication)
```sql
CREATE TABLE api_keys (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    key_prefix VARCHAR(20) NOT NULL,           -- voxel_1a2b3c4d (displayed to user)
    key_hash VARCHAR(255) NOT NULL UNIQUE,    -- SHA-256 of full key (stored)
    name VARCHAR(255),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_used_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY api_keys_tenant_active_idx (tenant_id, is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### `rate_limits` (Throttling per IP)
```sql
CREATE TABLE rate_limits (
    id CHAR(26) NOT NULL PRIMARY KEY,
    ip_address VARCHAR(50) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,           -- e.g., "POST /api/{slug}/bookings"
    hits INT UNSIGNED NOT NULL DEFAULT 1,
    window_expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY rate_limits_ip_endpoint_window (ip_address, endpoint, window_expires_at),
    KEY rate_limits_expires_idx (window_expires_at)
);
```

### `business_applications` (Future: Multi-Tenant Approval Flow)
```sql
CREATE TABLE business_applications (
    id CHAR(26) NOT NULL PRIMARY KEY,
    operator_id CHAR(26),
    business_name VARCHAR(255) NOT NULL,
    business_email VARCHAR(255) NOT NULL,
    business_phone VARCHAR(50),
    contact_name VARCHAR(255),
    message TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending|approved|rejected
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY business_applications_status_idx (status),
    FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE SET NULL
);
```

### `tenant_email_templates` (Custom Emails)
```sql
CREATE TABLE tenant_email_templates (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    template_type VARCHAR(50) NOT NULL,       -- booking_confirmation, reminder, cancellation, ...
    subject VARCHAR(255),
    body TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY tenant_email_templates_tenant_type_idx (tenant_id, template_type),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

---

## Indexes

### Performance-Critical Indexes

| Table | Index | Purpose |
|-------|-------|---------|
| `bookings` | `tenant_id, booking_pattern, status` | Dashboard status counts |
| `bookings` | `tenant_id, status, start_datetime` | Calendar queries |
| `bookings` | `customer_id` | Customer booking history |
| `bookings` | `staff_id, start_datetime` | Staff schedule conflicts |
| `bookings` | `resource_id, start_datetime` | Resource availability gaps |
| `availability` | `tenant_id, day_of_week` | Weekly rule lookups |
| `availability` | `staff_id, day_of_week` | Staff override lookups |
| `customers` | `tenant_id, email` | Customer lookup by email |
| `services` | `tenant_id, is_active` | Service listing |
| `staff` | `tenant_id, is_active` | Staff listing |
| `email_log` | `tenant_id, booking_id` | Email history |
| `audit_log` | `entity_type, entity_id` | Audit by entity |
| `reminders` | `sent_at, scheduled_at` | Pending reminder query |

---

## Data Types & Constraints

### ID Generation
- **Primary Keys**: CHAR(26) ASCII collation for ULID (128-bit, sortable)
- **Foreign Keys**: CHAR(26) ASCII, CASCADE ON DELETE for tenant isolation

### Dates & Times
- **Standard**: DATETIME (MySQL 8.0+ precision 6)
- **Never use**: TIMESTAMP (they auto-update on every row touch)
- **Timezone**: Stored as UTC in database, converted to business timezone for display

### Strings
- **Charset**: utf8mb4_unicode_ci (supports emoji and international characters)
- **Validation**: Done in application code, not database constraints

### JSON Fields
- Used for sparse, extensible data:
  - `settings.value` — complex settings values
  - `services.duration_options` — service duration choices
  - `tenants.custom_fields` — dynamic booking form fields
  - `customers.profile_data` — customer preferences
  - `bookings.custom_field_data` — booking form responses
  - `bookings.meta` — extension data
  - `events.exception_dates` — recurring event exceptions
  - `blocked_dates` — flexible reasons

### Constraints
- **UNIQUE**: Prevents duplicates (slug, email per tenant)
- **CHECK**: Validated in application code
- **NOT NULL**: Applied for required fields only
- **DEFAULT**: Sensible defaults for most fields

---

## Migration History

All migrations are sequential (001-028):

1. **001-004**: Core infrastructure (settings, operators, tenants, audit_log)
2. **005-008**: Timeslot pattern (services, staff, service_staff, availability)
3. **009-011**: Tenant infrastructure (resources, api_keys, business_users)
4. **012-020**: Booking pipeline (rate_limits, customers, bookings, email_log, reminders)
5. **021-028**: Extended features (templates, blocked_dates, capacity, events, applications)

Each migration file contains SQL statements; run sequentially by `Migrator` class.

---

## GDPR Compliance

### Consent Evidence (Legal Hold)
- `bookings.consent_given_at` — timestamp when consent was given
- `bookings.consent_text_shown` — exact text customer saw
- **Never anonymized or deleted** (legal evidence)

### Customer Anonymization
- `customers.name` → "Deleted"
- `customers.email` → SHA-256 hash (e.g., "5e884898da28...")
- `customers.phone` → NULL
- `customers.notes` → NULL
- `customers.profile_data` → NULL
- `bookings.notes` → NULL
- `bookings.custom_field_data` → NULL

### Retention & Expiry
- `tenants.data_retention_months` — how long before auto-anonymize
- Cron job (`RetentionJob`) runs daily to anonymize expired customers
- Deleted data: cannot be recovered (append-only audit trail remains)

### Data Export
- Full JSON export of customer data, booking history, consent records
- Accessible via `/book/{slug}/privacy/{customer_id}` (GDPR Art. 20)
