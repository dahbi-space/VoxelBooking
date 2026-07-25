# VoxelBooking API Documentation

## Table of Contents
1. [Public Booking API](#public-booking-api)
2. [Admin API](#admin-api)
3. [Agent API](#agent-api)
4. [Response Formats](#response-formats)
5. [Error Handling](#error-handling)
6. [Rate Limiting](#rate-limiting)
7. [Embed Widget](#embed-widget)

---

## Public Booking API

All endpoints are unauthenticated and per-tenant (identified by `{slug}`).

### Base URL
```
https://yourdomain.com/api/{slug}
```

### Services Endpoint

```http
GET /api/{slug}/services
```

Returns active services for a tenant.

**Response** (200 OK):
```json
{
  "services": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "name": "Haircut",
      "description": "Classic haircut service",
      "duration_minutes": 30,
      "duration_options": [30, 45, 60],
      "price": "25.00",
      "price_label": "From",
      "category": "Hair",
      "preparation_text": "Please arrive 5 minutes early",
      "cover_image_path": "/uploads/services/haircut.jpg",
      "color": "#3B82F6"
    }
  ]
}
```

**Used by**: Booking page to populate service selector.

### Staff Endpoint

```http
GET /api/{slug}/staff?service_id={id}
```

Returns active staff members, optionally filtered by service.

**Query Parameters**:
- `service_id` (optional): Filter to staff assigned to this service

**Response** (200 OK):
```json
{
  "staff": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAU",
      "name": "Alice Johnson",
      "title": "Senior Stylist",
      "avatar_path": "/uploads/staff/alice.jpg"
    }
  ]
}
```

### Availability Endpoint (Timeslot Pattern)

```http
GET /api/{slug}/availability?date={YYYY-MM-DD}&service_id={id}&staff_id={id}
```

Returns available time slots for a given date and service.

**Query Parameters**:
- `date` (required): YYYY-MM-DD format
- `service_id` (optional): Filter to service (for duration lookup)
- `staff_id` (optional): Filter to specific staff member

**Response** (200 OK):
```json
{
  "slots": [
    {
      "time": "09:00",
      "end_time": "09:30",
      "staff_id": null
    },
    {
      "time": "09:30",
      "end_time": "10:00",
      "staff_id": "01ARZ3NDEKTSV4RRFFQ69G5FAU"
    }
  ],
  "date": "2024-12-15",
  "timezone": "Europe/Amsterdam"
}
```

**Response** (400 Bad Request):
```json
{
  "error": "invalid_date",
  "message": "Date must be YYYY-MM-DD format"
}
```

**Response** (409 Conflict):
```json
{
  "error": "no_availability",
  "message": "No availability on this date"
}
```

### Available Dates Endpoint (Timeslot Pattern)

```http
GET /api/{slug}/available-dates?month={YYYY-MM}&service_id={id}
```

Returns calendar of available dates (dates with at least one available slot).

**Query Parameters**:
- `month` (required): YYYY-MM format
- `service_id` (optional): Filter by service

**Response** (200 OK):
```json
{
  "available_dates": [
    "2024-12-15",
    "2024-12-16",
    "2024-12-17",
    "2024-12-18",
    "2024-12-20",
    "2024-12-21"
  ]
}
```

### Resources Endpoint (Resource Pattern)

```http
GET /api/{slug}/resources
```

Returns available resources (rooms, units, etc.).

**Response** (200 OK):
```json
{
  "resources": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FB1",
      "name": "Room 101",
      "description": "Double room with ocean view",
      "price": "120.00",
      "max_guests": 2,
      "capacity": 2,
      "cover_image_path": "/uploads/resources/room101.jpg"
    }
  ]
}
```

### Resource Availability Endpoint (Resource Pattern)

```http
GET /api/{slug}/resources/{id}/availability?checkin={YYYY-MM-DD}&checkout={YYYY-MM-DD}
```

Returns availability grid for a specific resource.

**Response** (200 OK):
```json
{
  "available": true,
  "available_nights": 5,
  "nightly_price": "120.00",
  "total_price": "600.00"
}
```

**Response** (409 Conflict):
```json
{
  "available": false,
  "message": "Not available for these dates"
}
```

### Capacity Slots Endpoint (Capacity Pattern)

```http
GET /api/{slug}/capacity/slots?date={YYYY-MM-DD}
```

Returns available capacity slots for a date.

**Response** (200 OK):
```json
{
  "slots": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FB2",
      "start_datetime": "2024-12-15 12:00:00",
      "end_datetime": "2024-12-15 13:30:00",
      "title": "Lunch Seating",
      "max_capacity": 20,
      "remaining_seats": 8,
      "min_party_size": 1,
      "max_party_size": 6,
      "price": "25.00"
    }
  ]
}
```

### Capacity Available Dates Endpoint (Capacity Pattern)

```http
GET /api/{slug}/capacity/available-dates?month={YYYY-MM}
```

Returns calendar of dates with available capacity.

**Response** (200 OK):
```json
{
  "available_dates": ["2024-12-15", "2024-12-16", ...]
}
```

### Events Endpoint (Event Pattern)

```http
GET /api/{slug}/events?month={YYYY-MM}
```

Returns events for a month, with recurring events expanded.

**Response** (200 OK):
```json
{
  "events": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FB3",
      "name": "Yoga Class - Morning",
      "description": "Beginner-friendly yoga session",
      "start_datetime": "2024-12-15 09:00:00",
      "end_datetime": "2024-12-15 10:30:00",
      "location": "Studio A",
      "price": "15.00",
      "max_participants": 20,
      "remaining_spots": 5,
      "min_spot_count": 1,
      "max_spot_count": 2,
      "allow_waitlist": true,
      "cover_image_path": "/uploads/events/yoga.jpg"
    }
  ]
}
```

### Event Detail Endpoint (Event Pattern)

```http
GET /api/{slug}/events/{id}
```

Returns details for a single event.

**Response** (200 OK): (same as event in list)

### Booking Creation Endpoint

```http
POST /api/{slug}/bookings
Content-Type: application/json
```

Creates a new booking.

**Request Body** (Timeslot Pattern):
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
  "customer_timezone": "Europe/Amsterdam"
}
```

**Request Body** (Resource Pattern):
```json
{
  "resource_id": "01ARZ3NDEKTSV4RRFFQ69G5FB1",
  "start_datetime": "2024-12-15 14:00:00",
  "end_datetime": "2024-12-20 11:00:00",
  "party_size": 2,
  "name": "Jane Smith",
  "email": "jane@example.com",
  "consent_given": true,
  "customer_timezone": "Europe/Amsterdam"
}
```

**Request Body** (Capacity Pattern):
```json
{
  "start_datetime": "2024-12-15 12:00:00",
  "end_datetime": "2024-12-15 13:30:00",
  "party_size": 4,
  "name": "John Doe",
  "email": "john@example.com",
  "consent_given": true,
  "customer_timezone": "Europe/Amsterdam"
}
```

**Response** (201 Created):
```json
{
  "booking_id": "01ARZ3NDEKTSV4RRFFQ69G5FB4",
  "confirmation_message": "Your booking is confirmed!",
  "manage_url": "/book/salon/manage/01ARZ3NDEKTSV4RRFFQ69G5FB4",
  "booking": {
    "id": "01ARZ3NDEKTSV4RRFFQ69G5FB4",
    "start_datetime": "2024-12-15 10:00:00",
    "end_datetime": "2024-12-15 10:30:00",
    "service_name": "Haircut",
    "staff_name": "Alice"
  }
}
```

**Response** (400 Bad Request):
```json
{
  "error": "validation_error",
  "message": "Email is required",
  "errors": {
    "email": "Email is required"
  }
}
```

**Response** (409 Conflict):
```json
{
  "error": "slot_unavailable",
  "message": "This slot is no longer available"
}
```

### Booking Detail Endpoint

```http
GET /api/{slug}/bookings/{id}
```

Returns booking details (unauthenticated; `{id}` is bearer token).

**Response** (200 OK):
```json
{
  "booking": {
    "id": "01ARZ3NDEKTSV4RRFFQ69G5FB4",
    "customer_name": "John Doe",
    "email": "john@example.com",
    "phone": "+31612345678",
    "start_datetime": "2024-12-15 10:00:00",
    "end_datetime": "2024-12-15 10:30:00",
    "service_name": "Haircut",
    "staff_name": "Alice",
    "status": "confirmed",
    "notes": "I have long, curly hair",
    "cancellable": true,
    "reschedulable": true
  }
}
```

### Booking Cancellation Endpoint

```http
POST /api/{slug}/bookings/{id}/cancel
Content-Type: application/json
```

Cancels a booking.

**Request Body**:
```json
{
  "reason": "Plans changed"
}
```

**Response** (200 OK):
```json
{
  "message": "Booking cancelled successfully",
  "booking": { ... }
}
```

**Response** (409 Conflict):
```json
{
  "error": "cannot_cancel",
  "message": "Cannot cancel within 24 hours of booking start time"
}
```

### Booking Reschedule Endpoint

```http
POST /api/{slug}/bookings/{id}/reschedule
Content-Type: application/json
```

Reschedules a booking to a new date/time.

**Request Body**:
```json
{
  "new_start_datetime": "2024-12-16 14:00:00",
  "new_end_datetime": "2024-12-16 14:30:00",
  "reason": "Prefer afternoon"
}
```

**Response** (200 OK):
```json
{
  "message": "Booking rescheduled successfully",
  "new_booking_id": "01ARZ3NDEKTSV4RRFFQ69G5FB5",
  "old_booking_id": "01ARZ3NDEKTSV4RRFFQ69G5FB4"
}
```

---

## Admin API

Admin endpoints require authentication (session-based).

### Bookings Endpoint

```http
GET /admin/bookings?status={status}&from={date}&to={date}&search={term}&page={n}
```

Cross-tenant booking list (operators only).

**Query Parameters**:
- `status` (optional): pending|confirmed|cancelled|completed|no_show|rescheduled|waitlisted
- `from` (optional): YYYY-MM-DD
- `to` (optional): YYYY-MM-DD
- `search` (optional): customer name/email or booking ID
- `page` (optional): 1-indexed pagination

**Response**:
```json
{
  "bookings": [ ... ],
  "pagination": {
    "page": 1,
    "per_page": 50,
    "total": 127,
    "pages": 3
  }
}
```

### Bookings Export Endpoint

```http
GET /admin/bookings/export?status={status}&from={date}&to={date}
```

CSV export of bookings (cross-tenant).

**Response** (200 OK):
```
ID,Date,Customer,Service,Staff,Status
01ARZ3NDEKTSV4RRFFQ69G5FB4,2024-12-15 10:00:00,John Doe,Haircut,Alice,confirmed
...
```

### Tenant Bookings Endpoint

```http
GET /admin/tenants/{tenant_id}/bookings?status={status}&page={n}
```

Tenant-specific booking list (operator or business user for that tenant).

### Calendar Endpoint

```http
GET /admin/tenants/{tenant_id}/calendar?date={YYYY-MM-DD}
```

Calendar view data (month, week, or day).

**Response**:
```json
{
  "bookings": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FB4",
      "customer_name": "John Doe",
      "start_datetime": "2024-12-15 10:00:00",
      "end_datetime": "2024-12-15 10:30:00",
      "service_name": "Haircut",
      "service_color": "#3B82F6",
      "status": "confirmed"
    }
  ]
}
```

### Customers Endpoint

```http
GET /admin/tenants/{tenant_id}/customers?page={n}
```

Tenant-specific customer list.

**Response**:
```json
{
  "customers": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FB5",
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+31612345678",
      "booking_count": 5,
      "last_booking_at": "2024-12-15 10:00:00"
    }
  ]
}
```

### Settings Endpoints

```http
GET /admin/tenants/{tenant_id}/settings
```

Get tenant settings.

**Response**:
```json
{
  "tenant": {
    "id": "...",
    "name": "Salon ABC",
    "timezone": "Europe/Amsterdam",
    "booking_pattern": "timeslot",
    "min_advance_hours": 1,
    "max_advance_days": 90,
    "slot_duration_minutes": 30,
    "require_phone": false,
    "booking_requires_approval": false,
    "allow_cancellation": true,
    "cancellation_hours_before": 24,
    "allow_rescheduling": true,
    "rescheduling_hours_before": 24,
    ...
  }
}
```

```http
POST /admin/tenants/{tenant_id}/settings
```

Update tenant settings.

---

## Agent API

Unauthenticated schema endpoint + authenticated resource endpoints.

### Schema Endpoint (Public)

```http
GET /api/agent/v1/schema
```

Returns OpenAPI-compatible schema for LLM tool discovery.

**Response** (200 OK):
```json
{
  "openapi": "3.0.0",
  "info": {
    "title": "VoxelBooking Agent API",
    "version": "1.0.0"
  },
  "paths": {
    "/api/agent/v1/tenants": {
      "get": {
        "summary": "List tenants",
        "operationId": "getTenants",
        "security": [{ "BearerAuth": [] }],
        "responses": {
          "200": {
            "description": "List of tenants",
            "content": {
              "application/json": {
                "schema": { "$ref": "#/components/schemas/TenantList" }
              }
            }
          }
        }
      }
    }
  },
  "components": {
    "securitySchemes": {
      "BearerAuth": {
        "type": "http",
        "scheme": "bearer"
      }
    },
    "schemas": {
      "Tenant": { ... },
      "Booking": { ... },
      ...
    }
  }
}
```

### Tenants Endpoint (Authenticated)

```http
GET /api/agent/v1/tenants
Authorization: Bearer {api_key}
```

Returns tenants accessible to API key.

**Response** (200 OK):
```json
{
  "tenants": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FBV",
      "name": "Salon ABC",
      "slug": "salon-abc",
      "booking_pattern": "timeslot",
      "timezone": "Europe/Amsterdam",
      "currency": "EUR"
    }
  ]
}
```

### Bookings Endpoint (Authenticated)

```http
GET /api/agent/v1/bookings?tenant_id={id}&status={status}&from={date}&to={date}
Authorization: Bearer {api_key}
```

Returns bookings for a tenant.

**Response** (200 OK):
```json
{
  "bookings": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FB4",
      "tenant_id": "01ARZ3NDEKTSV4RRFFQ69G5FBV",
      "customer_name": "John Doe",
      "customer_email": "john@example.com",
      "service_name": "Haircut",
      "staff_name": "Alice",
      "start_datetime": "2024-12-15 10:00:00",
      "end_datetime": "2024-12-15 10:30:00",
      "status": "confirmed",
      "created_at": "2024-12-01 12:34:56"
    }
  ]
}
```

### Services Endpoint (Authenticated)

```http
GET /api/agent/v1/services?tenant_id={id}
Authorization: Bearer {api_key}
```

Returns services for a tenant.

**Response** (200 OK):
```json
{
  "services": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "name": "Haircut",
      "duration_minutes": 30,
      "price": "25.00",
      "category": "Hair"
    }
  ]
}
```

### Availability Endpoint (Authenticated)

```http
GET /api/agent/v1/availability?tenant_id={id}&date={YYYY-MM-DD}&service_id={id}
Authorization: Bearer {api_key}
```

Returns available slots for a date.

**Response** (200 OK):
```json
{
  "slots": [
    { "time": "09:00", "end_time": "09:30", "staff_id": null },
    { "time": "09:30", "end_time": "10:00", "staff_id": "..." }
  ]
}
```

---

## Response Formats

### Success Response (2xx)

```json
{
  "data": { ... },
  "message": "Operation successful"
}
```

Or direct data for simple endpoints:

```json
{
  "services": [ ... ],
  "staff": [ ... ]
}
```

### Error Response (4xx, 5xx)

```json
{
  "error": "error_code",
  "message": "Human-readable error message",
  "errors": {
    "field_name": "Field-specific error"
  }
}
```

### Common Error Codes

| Code | Status | Meaning |
|------|--------|---------|
| `not_found` | 404 | Resource not found |
| `tenant_not_found` | 404 | Tenant (slug) not found |
| `validation_error` | 400 | Request validation failed |
| `unauthorized` | 401 | Authentication required or failed |
| `forbidden` | 403 | Authenticated but not authorized |
| `slot_unavailable` | 409 | Requested slot is booked or invalid |
| `capacity_exceeded` | 409 | Party size exceeds available capacity |
| `cannot_cancel` | 409 | Cancellation not allowed (too close to start) |
| `cannot_reschedule` | 409 | Rescheduling not allowed |
| `consent_required` | 400 | Customer must agree to terms |
| `rate_limited` | 429 | Too many requests from this IP |
| `server_error` | 500 | Internal server error |

---

## Rate Limiting

### Public API Rate Limits

| Endpoint | Limit | Window |
|----------|-------|--------|
| `GET /api/{slug}/...` | 60 | 1 minute per IP |
| `POST /api/{slug}/bookings` | 10 | 1 minute per IP |
| `GET /book/{slug}` | 60 | 1 minute per IP |

### Admin API Rate Limits

| Endpoint | Limit | Window |
|----------|-------|--------|
| `POST /admin/login` | 5 | 1 minute per IP |
| `POST /admin/login/request-code` | 5 | 1 minute per IP |
| `GET /admin/...` | 100 | 1 minute per IP |

### Rate Limit Headers

Every response includes:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1703000000
```

When rate limit exceeded (429 Too Many Requests):

```json
{
  "error": "rate_limited",
  "message": "Too many requests. Try again after 60 seconds.",
  "retry_after": 60
}
```

---

## Embed Widget

### Widget Initialization

```html
<!-- In customer's website -->
<script src="https://yourdomain.com/embed/{slug}.js"></script>

<script>
  VoxelBookingWidget.init({
    trigger: '#book-button',        // CSS selector for button
    position: 'right',              // or 'left'
    theme: 'light',                 // or 'dark'
    autoOpen: false,
    onBookingComplete: function(booking) {
      console.log('Booking created:', booking);
    }
  });
</script>
```

### Embed Script Endpoint

```http
GET /embed/{slug}.js
```

Returns widget JavaScript (injected into customer's website).

**Response**: JavaScript module with `VoxelBookingWidget` global.

### Embed Config Endpoint

```http
GET /api/{slug}/embed-config
```

Returns configuration for embed widget.

**Response** (200 OK):
```json
{
  "slug": "salon-abc",
  "name": "Salon ABC",
  "timezone": "Europe/Amsterdam",
  "booking_pattern": "timeslot",
  "brand_color": "#2563EB",
  "brand_color_text": "#FFFFFF",
  "booking_page_heading": "Book Your Haircut",
  "allowed_domains": ["example.com", "www.example.com"]
}
```

### Security (CORS & Domain Whitelist)

- Widget can only be embedded on whitelisted domains
- CORS headers: `Access-Control-Allow-Origin: {whitelist}`
- Domain validation in `tenants.allowed_embed_domains`

---

## Webhooks (Future)

Planned webhook endpoints for integrations:

```
POST {customer_webhook_url}

Events:
- booking.created
- booking.cancelled
- booking.rescheduled
- booking.completed
- booking.no_show
- reminder.sent

Payload:
{
  "event": "booking.created",
  "booking_id": "...",
  "timestamp": "2024-12-15T10:00:00Z",
  "data": { ... }
}
```

Will include signature verification (HMAC-SHA256) and retry logic.
