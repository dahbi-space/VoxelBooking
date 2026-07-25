# VoxelBooking Booking Flow

## Table of Contents
1. [Overview](#overview)
2. [Timeslot Booking Flow](#timeslot-booking-flow)
3. [Resource Booking Flow](#resource-booking-flow)
4. [Capacity Booking Flow](#capacity-booking-flow)
5. [Event Booking Flow](#event-booking-flow)
6. [Confirmation & Reminders](#confirmation--reminders)
7. [Rescheduling & Cancellation](#rescheduling--cancellation)
8. [Admin-Initiated Bookings](#admin-initiated-bookings)

---

## Overview

All four booking patterns follow this high-level flow:

```
┌─────────────────────────────────────────────────────────────┐
│ Customer visits /book/{slug}                                │
└────────────────────┬────────────────────────────────────────┘
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ Booking Page renders Alpine.js app                          │
│ - Loads tenant config                                       │
│ - Detects browser timezone                                  │
│ - Shows pattern-specific UI                                 │
└────────────────────┬────────────────────────────────────────┘
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ Customer provides input                                      │
│ - Availability API calls fetch slots/resources/capacity     │
│ - Customer fills form (name, email, custom fields)          │
│ - Pattern-specific validation (dates, capacity, etc.)       │
└────────────────────┬────────────────────────────────────────┘
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ POST /api/{slug}/bookings (pattern-specific)                │
│ - Server validates availability (re-check)                  │
│ - Creates customer if not exists                            │
│ - Creates booking with BookingService                       │
│ - Captures GDPR consent evidence                            │
│ - Queues confirmation email                                 │
│ - Creates reminder task                                     │
│ - Logs audit trail                                          │
└────────────────────┬────────────────────────────────────────┘
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ Return JSON { booking_id, confirmation_message }            │
│ - Alpine.js shows success page                              │
│ - Customer receives confirmation email                      │
│ - Staff receives notification (if configured)               │
└─────────────────────────────────────────────────────────────┘
```

---

## Timeslot Booking Flow

### 1. Page Load & Tenant Resolution

**Request**: `GET /book/{slug}`

```
BookingPageController::show()
  ├─ Resolve tenant by slug
  ├─ Verify tenant.status == 'active'
  ├─ Resolve locale (Accept-Language → business default → 'en')
  ├─ Render template: booking/index.php
  ├─ Alpine app initializes with:
  │  ├─ tenant config (name, timezone, rules, colors)
  │  ├─ booking_pattern = 'timeslot'
  │  └─ __VB_FMT__ (formatting: date, time, currency, symbols)
  └─ Return HTML 200
```

**Response**:
```html
<!DOCTYPE html>
<html>
  <head>
    <title>Book with [Business Name]</title>
    <script>
      window.__VB_CONFIG__ = { slug: "...", booking_pattern: "timeslot", ... };
      window.__VB_FMT__ = { date_format: "Y-m-d", time_format: "24h", ... };
    </script>
  </head>
  <body>
    <div id="booking-app"><!-- Alpine.js app mounts here --></div>
  </body>
</html>
```

### 2. Customer Selects Service

**Request**: `GET /api/{slug}/services`

```
BookingApiController::services()
  ├─ Resolve tenant
  ├─ Query: SELECT * FROM services WHERE tenant_id = ? AND is_active = 1
  └─ Return JSON
```

**Response**:
```json
{
  "services": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "name": "Haircut",
      "description": "Classic haircut",
      "duration_minutes": 30,
      "price": "25.00",
      "color": "#3B82F6"
    },
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAW",
      "name": "Color & Highlights",
      "description": "Full color service",
      "duration_minutes": 90,
      "price": "85.00",
      "color": "#EC4899"
    }
  ]
}
```

### 3. Customer Selects Date & Gets Availability

**Request**: `GET /api/{slug}/availability?date=2024-12-15&service_id=01ARZ3NDEKTSV4RRFFQ69G5FAV&staff_id=null`

```
BookingApiController::availability()
  ├─ Resolve tenant
  ├─ Validate date format (YYYY-MM-DD)
  ├─ Resolve service duration (30 min)
  ├─ Delegate to TimeSlotCalculator::getAvailableSlots()
  │  ├─ Get day of week (Monday = 0, Sunday = 6)
  │  ├─ Query: SELECT * FROM availability
  │  │          WHERE tenant_id = ? AND day_of_week = ?
  │  │          AND (staff_id IS NULL OR staff_id = ?)
  │  ├─ Check if date is blocked: SELECT 1 FROM blocked_dates
  │  │  WHERE tenant_id = ? AND start_date <= ? AND end_date >= ?
  │  ├─ Enforce min_advance_hours and max_advance_days
  │  ├─ Query: SELECT * FROM bookings
  │  │          WHERE tenant_id = ? AND DATE(start_datetime) = ?
  │  │          AND status NOT IN ('cancelled', 'rescheduled')
  │  ├─ Calculate available time windows (subtract bookings + buffer)
  │  ├─ Generate slot times (e.g., 09:00, 09:30, 10:00, ...)
  │  └─ Return slots []
  └─ Return JSON
```

**Response**:
```json
{
  "slots": [
    { "time": "09:00", "end_time": "09:30", "staff_id": null },
    { "time": "09:30", "end_time": "10:00", "staff_id": null },
    { "time": "10:00", "end_time": "10:30", "staff_id": null },
    { "time": "10:30", "end_time": "11:00", "staff_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV" }
  ],
  "date": "2024-12-15",
  "timezone": "Europe/Amsterdam"
}
```

### 4. Customer Selects Staff (Optional)

**Request**: `GET /api/{slug}/staff?service_id=01ARZ3NDEKTSV4RRFFQ69G5FAV`

```
BookingApiController::staff()
  ├─ Resolve tenant
  ├─ Query: SELECT s.* FROM staff s
  │          JOIN service_staff ss ON ss.staff_id = s.id
  │          WHERE ss.service_id = ? AND s.tenant_id = ?
  │          AND s.is_active = 1
  └─ Return JSON
```

**Response**:
```json
{
  "staff": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAU",
      "name": "Alice",
      "title": "Senior Stylist",
      "avatar_path": "/uploads/staff/alice.jpg"
    },
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "name": "Bob",
      "title": "Stylist",
      "avatar_path": "/uploads/staff/bob.jpg"
    }
  ]
}
```

### 5. Customer Submits Booking

**Request**: `POST /api/{slug}/bookings`

```json
{
  "service_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "staff_id": "01ARZ3NDEKTSV4RRFFQ69G5FAU",
  "start_datetime": "2024-12-15 10:00:00",
  "end_datetime": "2024-12-15 10:30:00",
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+31612345678",
  "notes": "I have long, curly hair",
  "custom_field_data": { "preferred_color": "brown" },
  "consent_given": true,
  "customer_timezone": "Europe/Amsterdam",
  "source": "web"
}
```

```
BookingApiController::createBooking()
  ├─ Resolve tenant
  ├─ Validate request data (required fields, email format, etc.)
  ├─ Parse datetime strings to DATETIME format
  ├─ Call Validator::validateBooking() for tenant-specific rules
  │  ├─ Check min_advance_hours (now + 1 hour ≤ start_datetime)
  │  ├─ Check max_advance_days (start_datetime ≤ now + 90 days)
  │  ├─ Check max_bookings_per_customer_per_day
  │  └─ Check service duration ≤ slot duration
  ├─ Call CustomerService::getOrCreate()
  │  ├─ Query: SELECT * FROM customers
  │  │          WHERE tenant_id = ? AND email = ?
  │  ├─ If found: use existing customer
  │  ├─ If not found:
  │  │  └─ INSERT new customer with name, email, phone
  │  └─ Return customer { id: "...", ... }
  ├─ Call BookingService::createBooking()
  │  ├─ Validate required fields
  │  ├─ Check consent requirement:
  │  │  └─ If tenant.requires_consent && !consent_given && source != 'admin'
  │  │     └─ THROW exception
  │  ├─ Generate ULID for booking
  │  ├─ Capture consent evidence:
  │  │  ├─ consent_given_at = NOW()
  │  │  └─ consent_text_shown = tenant.consent_text (fallback to default)
  │  ├─ Build INSERT statement with:
  │  │  ├─ bookings.id
  │  │  ├─ bookings.tenant_id
  │  │  ├─ bookings.customer_id
  │  │  ├─ bookings.booking_pattern = 'timeslot'
  │  │  ├─ bookings.service_id
  │  │  ├─ bookings.staff_id
  │  │  ├─ bookings.start_datetime
  │  │  ├─ bookings.end_datetime
  │  │  ├─ bookings.notes
  │  │  ├─ bookings.custom_field_data (JSON)
  │  │  ├─ bookings.consent_given_at
  │  │  ├─ bookings.consent_text_shown
  │  │  ├─ bookings.customer_timezone
  │  │  ├─ bookings.status = 'confirmed' (or 'pending' if booking_requires_approval)
  │  │  └─ bookings.source = 'web'
  │  ├─ INSERT booking record
  │  ├─ Log to audit trail:
  │  │  └─ AuditLog::log('booking.created', 'booking', booking_id,
  │  │     { booking_pattern, source, consent_given, consent_recorded })
  │  └─ Return { id: booking_id, consent_recorded: true }
  ├─ If booking.status == 'pending':
  │  └─ Send "Booking requires approval" email to customer
  ├─ If booking.status == 'confirmed':
  │  ├─ If tenant.send_reminders:
  │  │  ├─ Calculate reminder_time = start_datetime - reminder_hours_before
  │  │  ├─ INSERT into reminders:
  │  │  │  ├─ id = ULID
  │  │  │  ├─ booking_id
  │  │  │  ├─ scheduled_at = reminder_time
  │  │  │  └─ sent_at = NULL
  │  │  └─ (Cron will send at scheduled_at)
  │  └─ Queue confirmation email (sent async or immediately):
  │     ├─ To: customer.email
  │     ├─ Template: booking_confirmation (from tenant_email_templates or default)
  │     ├─ Body: Include booking details, timezone-converted times, cancellation link
  │     └─ Log to email_log table
  ├─ If tenant.notify_on_booking:
  │  └─ Send notification to tenant.notification_email with booking details
  └─ Return JSON { booking_id, confirmation_message, manage_url }
```

**Response**:
```json
{
  "booking_id": "01ARZ3NDEKTSV4RRFFQ69G5FB0",
  "confirmation_message": "Your booking is confirmed!",
  "manage_url": "/book/salon/manage/01ARZ3NDEKTSV4RRFFQ69G5FB0",
  "booking": {
    "id": "01ARZ3NDEKTSV4RRFFQ69G5FB0",
    "start_datetime": "2024-12-15 10:00:00",
    "end_datetime": "2024-12-15 10:30:00",
    "service_name": "Haircut",
    "staff_name": "Alice"
  }
}
```

---

## Resource Booking Flow

### High-Level Differences

1. **No services or staff** — booking is directly against resource (room)
2. **Multi-day availability** — customer selects check-in and check-out dates
3. **Day-level constraints** — check-in/check-out restricted to specific days of week

### Key Steps

```
1. Load available resources
   GET /api/{slug}/resources
   → Returns list of rooms with descriptions, prices

2. Select resource + check-in/check-out dates
   GET /api/{slug}/resources/{id}/availability?checkin=2024-12-15&checkout=2024-12-20
   → ResourceCalculator checks for overlapping bookings
   → Validates check-in/check-out day constraints
   → Returns availability grid

3. Submit booking
   POST /api/{slug}/bookings
   {
     "resource_id": "...",
     "start_datetime": "2024-12-15 14:00:00",  // check-in
     "end_datetime": "2024-12-20 11:00:00",    // check-out
     "party_size": 2,
     "name": "...",
     "email": "...",
     "consent_given": true
   }
   → BookingService creates booking with resource_id
   → Email confirmation includes night count, total price
```

---

## Capacity Booking Flow

### High-Level Differences

1. **Slot-based** — predefined time slots with fixed capacity
2. **Party size** — customer specifies group size (1–10 people)
3. **Waitlist** — if full, can add to waitlist (status='waitlisted')

### Key Steps

```
1. Load capacity slots
   GET /api/{slug}/capacity/slots
   → Returns predefined slots (e.g., Lunch 12:00–13:30, Dinner 19:00–21:00)
   → Includes max_capacity, min/max_party_size, price

2. Customer selects date
   GET /api/{slug}/capacity/available-dates?month=2024-12
   → Returns calendar of available dates (dates with remaining capacity)

3. Customer selects slot + party size
   POST /api/{slug}/bookings
   {
     "capacity_slot_id": "...",  // Not explicitly sent; resolved via start_datetime
     "start_datetime": "2024-12-15 19:00:00",
     "end_datetime": "2024-12-15 21:00:00",
     "party_size": 4,
     "name": "...",
     "email": "...",
     "consent_given": true
   }
   → CapacityCalculator checks:
     └─ min_party_size ≤ party_size ≤ max_party_size?
     └─ party_size ≤ remaining_capacity?
   → If yes: status='confirmed'
   → If no && allow_waitlist: status='waitlisted'
   → Else: reject with error
```

---

## Event Booking Flow

### High-Level Differences

1. **Recurring events** — single event can occur multiple times (RRULE)
2. **Spot count** — customer books N spots (vs. 1 timeslot)
3. **Waitlist** — full events can accept waitlisted bookings
4. **Cancellation rules** — may differ from timeslot/resource patterns

### Key Steps

```
1. Load events
   GET /api/{slug}/events
   → Returns list of events (recurring expanded by EventCalculator)
   → Includes max_participants, price, rrule, exception_dates

2. Customer selects event + number of spots
   GET /api/{slug}/events/{id}
   → Returns single event with calculated remaining_spots

3. Submit booking
   POST /api/{slug}/bookings
   {
     "event_id": "01ARZ3NDEKTSV4RRFFQ69G5FC0",
     "start_datetime": "2024-12-20 10:00:00",
     "end_datetime": "2024-12-20 12:00:00",
     "party_size": 2,  // Spot count for this event
     "name": "...",
     "email": "...",
     "consent_given": true
   }
   → EventCalculator checks:
     └─ party_size ≥ event.min_spot_count?
     └─ party_size ≤ event.max_spot_count? (if set)
     └─ party_size ≤ event.remaining_spots?
   → If yes: status='confirmed'
   → If no && event.allow_waitlist: status='waitlisted'
   → Else: reject with error
```

---

## Confirmation & Reminders

### Confirmation Email (Async)

**When**: Immediately after booking creation (success response sent to customer first)

**Process**:
```
Mailer::send() [or queued job]
  ├─ Query tenant_email_templates for 'booking_confirmation'
  ├─ Fallback to hardcoded default template if not customized
  ├─ Render template with:
  │  ├─ booking details (date, time, service, staff, etc.)
  │  ├─ timezone-converted times (booking.customer_timezone)
  │  ├─ customer details (name, email, phone)
  │  ├─ cancellation link (/book/{slug}/manage/{booking_id})
  │  ├─ business details (name, logo, contact info)
  │  └─ custom fields (consent text, custom field values)
  ├─ Send via SMTP (configured in Admin → Settings → Email)
  ├─ Log to email_log with:
  │  ├─ recipient
  │  ├─ subject
  │  ├─ status (sent|failed)
  │  └─ error_message (if failed)
  └─ Retry on failure (exponential backoff)
```

**Template Variables**:
```
{{ booking.id }}
{{ booking.start_datetime }}
{{ booking.end_datetime }}
{{ booking.customer_name }}
{{ booking.service_name }}
{{ booking.staff_name }}
{{ booking.resource_name }}
{{ booking.event_name }}
{{ booking.party_size }}
{{ booking.notes }}
{{ tenant.name }}
{{ tenant.email }}
{{ tenant.phone }}
{{ manage_url }}
{{ cancel_url }}
{{ timezone }}
```

### Reminder Email (Scheduled)

**When**: At `reminders.scheduled_at` (= start_datetime - reminder_hours_before)

**Process** (Cron job `/cron/run`):
```
ReminderJob::run()
  ├─ Query: SELECT * FROM reminders
  │          WHERE sent_at IS NULL AND scheduled_at <= NOW()
  │          LIMIT 100 (process in batches)
  ├─ For each pending reminder:
  │  ├─ Query booking + customer + tenant
  │  ├─ Check booking status (skip if cancelled, completed, no_show)
  │  ├─ Render reminder template
  │  ├─ Send email
  │  ├─ If success:
  │  │  └─ UPDATE reminders SET sent_at = NOW(), attempts = attempts + 1
  │  ├─ If failure:
  │  │  ├─ If attempts < max_retry:
  │  │  │  └─ Reschedule: UPDATE reminders SET scheduled_at = NOW() + 1 hour
  │  │  ├─ Else:
  │  │  │  └─ Log error, mark as failed (sent_at = NULL, last_error = "...")
  │  └─ Log to email_log
  └─ Done
```

---

## Rescheduling & Cancellation

### Customer-Initiated Rescheduling

**Flow**:
```
1. Customer visits /book/{slug}/manage/{booking_id}
   ├─ No authentication required (booking_id is bearer token)
   ├─ Display booking details + rescheduling form
   └─ If allow_rescheduling && rescheduling_hours_before honored:
      └─ Show available dates/times to reschedule to

2. Customer selects new date/time
   POST /api/{slug}/bookings/{id}/reschedule
   {
     "new_start_datetime": "2024-12-16 10:00:00",
     "new_end_datetime": "2024-12-16 10:30:00",
     "reason": "Prefer afternoon"
   }

3. Server validates:
   ├─ Original booking exists
   ├─ Original booking status == 'confirmed' (not cancelled, no_show, etc.)
   ├─ Now + rescheduling_hours_before ≤ original start_datetime?
   ├─ New time slot is available
   └─ All tenant rules satisfied (min_advance, max_advance, etc.)

4. Create new booking:
   ├─ INSERT new booking with same customer, service, staff, etc.
   ├─ Copy consent evidence from original booking
   ├─ Set status = 'confirmed'
   ├─ Set custom_field_data from original
   └─ Send confirmation email for new booking

5. Update original booking:
   ├─ UPDATE bookings SET status = 'rescheduled'
   ├─ UPDATE bookings SET rescheduled_to_id = {new_booking_id}
   ├─ Cancel/delete reminder for original booking
   └─ Send rescheduling confirmation email

6. Log to audit trail:
   ├─ AuditLog::log('booking.rescheduled', 'booking', original_id,
   │   { new_booking_id, new_start_datetime, reason })
```

### Customer-Initiated Cancellation

**Flow**:
```
1. Customer visits /book/{slug}/manage/{booking_id}
   └─ Show cancellation form (if allow_cancellation && hours before honored)

2. Customer submits cancellation
   POST /api/{slug}/bookings/{id}/cancel
   {
     "reason": "Plans changed"
   }

3. Server validates:
   ├─ Booking status == 'confirmed' (not already cancelled)
   ├─ Now + cancellation_hours_before ≤ start_datetime?
   └─ (Any other business logic)

4. Update booking:
   ├─ UPDATE bookings SET status = 'cancelled'
   ├─ UPDATE bookings SET cancelled_at = NOW()
   ├─ UPDATE bookings SET cancellation_reason = ?
   ├─ Cancel reminder (UPDATE reminders SET sent_at = NULL for safety)
   ├─ Send cancellation confirmation email to customer
   └─ If notify_on_cancellation: send to tenant.notification_email

5. Log to audit trail:
   ├─ AuditLog::log('booking.cancelled', 'booking', booking_id,
   │   { reason, cancelled_by: 'customer' })

6. GDPR: Cancellation ≠ deletion
   └─ Booking record remains (legal evidence of consent)
   └─ Customer data remains (can re-book later)
   └─ Only anonymization (after data_retention_months) clears PII
```

### Admin-Initiated Cancellation

Same flow but:
- No time window restriction (admin can cancel any time)
- Source logged as 'admin' (vs. 'customer')
- May include refund/credit logic (not yet implemented)

---

## Admin-Initiated Bookings

### Bypass Consent Requirement

**Flow**:
```
POST /admin/tenants/{tenant_id}/bookings/create
{
  "service_id": "...",
  "staff_id": "...",
  "customer_email": "john@example.com",
  "start_datetime": "2024-12-15 10:00:00",
  "notes": "Phone booking"
}

BookingsController::tenantStore()
  ├─ Verify auth (admin or business owner/manager for this tenant)
  ├─ Resolve or create customer by email
  ├─ Call BookingService::createBooking()
  │  ├─ Detect source = 'admin'
  │  ├─ Skip consent enforcement (source == 'admin')
  │  ├─ consent_given_at = NULL (not given by customer)
  │  └─ consent_text_shown = NULL
  ├─ Create booking with status = 'confirmed'
  ├─ Send confirmation email to customer (with note: "Phone booking")
  └─ Redirect to booking detail
```

**Key**: Admin bookings never require consent (they're staff-initiated, not customer-initiated).

---

## Error Handling & Validation

### Client-Side Validation (Alpine.js)

Before submitting to server:
- Date/time format validation
- Email format validation
- Required field checks
- Custom field validation (based on tenant.custom_fields schema)

### Server-Side Validation (Validator class)

After receiving POST request:
```
Validator::validateBooking($data, $tenant)
  ├─ Required fields: name, email, booking_pattern, start_datetime, end_datetime
  ├─ Email format: filter_var(..., FILTER_VALIDATE_EMAIL)
  ├─ Phone format: if require_phone, validate
  ├─ Datetime parsing: try DateTimeImmutable(), catch on failure
  ├─ Datetime logic:
  │  ├─ end_datetime > start_datetime?
  │  ├─ start_datetime ≥ NOW() + min_advance_hours?
  │  ├─ start_datetime ≤ NOW() + max_advance_days?
  │  └─ duration = end_datetime - start_datetime ≤ reasonable max (24 hours)?
  ├─ Tenant rules:
  │  ├─ max_bookings_per_customer_per_day exceeded?
  │  ├─ require_phone set and phone empty?
  │  └─ booking_requires_approval enabled?
  ├─ Custom fields:
  │  └─ Validate against tenant.custom_fields schema
  └─ Return: (valid: bool, errors: array)
```

### Common Validation Errors

| Error | Status | Message |
|-------|--------|---------|
| Invalid date format | 400 | "Date must be YYYY-MM-DD" |
| Slot not available | 409 | "This slot is no longer available" |
| Too soon | 400 | "Booking must be at least 1 hour in advance" |
| Too far out | 400 | "Booking must be within 90 days" |
| Capacity exceeded | 409 | "No availability for this party size" |
| Consent required | 400 | "You must agree to the privacy policy" |
| Tenant not active | 404 | "Booking page is temporarily unavailable" |
| Rate limited | 429 | "Too many requests from this IP" |

---

## Audit Trail for Bookings

Every state change is logged:

```sql
INSERT INTO audit_log (
  entity_type = 'booking',
  entity_id = '{booking_id}',
  action = 'booking.created',
  old_values = NULL,
  new_values = JSON {
    service_id, staff_id, customer_id, start_datetime, end_datetime,
    status, consent_given, source
  },
  changes = "Created new booking",
  ip_address = '{remote_addr}',
  created_at = NOW()
);
```

Actions logged:
- `booking.created` — initial creation
- `booking.status_changed` — status update (confirmed→no_show, etc.)
- `booking.rescheduled` — rescheduling
- `booking.cancelled` — cancellation
- `booking.approved` — if booking_requires_approval

---

## Performance Notes

### Database Queries

Critical queries for availability:
- **Timeslot**: `SELECT * FROM availability WHERE tenant_id = ? AND day_of_week = ?`
- **Timeslot**: `SELECT * FROM bookings WHERE tenant_id = ? AND DATE(start_datetime) = ? AND status NOT IN (...)`
- **Resource**: `SELECT * FROM bookings WHERE resource_id = ? AND start_datetime < ? AND end_datetime > ?`
- **Capacity**: `SELECT SUM(party_size) FROM bookings WHERE capacity_slot_id = ? AND status = 'confirmed'`

All have appropriate indexes for < 10ms response times.

### Caching Strategy

No distributed cache needed. Frontend caches:
- Availability slots (5-min TTL in Alpine.js)
- Service list (10-min TTL)
- Staff list (10-min TTL)

Server-side caching:
- Tenant config (loaded once per request, no persistent cache)

### Concurrent Bookings

Race condition risk:
1. Two customers simultaneously book the last available slot
2. Both clients see availability
3. First submits, booking created
4. Second submits, slot no longer available

**Mitigation**:
- TimeSlotCalculator queries live availability on each POST request (not cached)
- Booking is created in single INSERT transaction
- If slot is taken, second request gets 409 "Slot not available"
- Frontend handles 409 by refreshing availability and showing error

No row-level locking needed (single-threaded web context, InnoDB default locking).
