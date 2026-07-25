# VoxelBooking Module Reference

Complete list of every module, class, and its responsibility.

---

## Controllers

Controllers handle HTTP requests and return responses. All follow this pattern:

```php
public function methodName(Request $request): Response
```

### Admin\DashboardController

**File**: `app/Controllers/Admin/DashboardController.php`

Serves operator and business user dashboards.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin` | Operator dashboard (cross-tenant view) |
| `tenantDashboard()` | `GET /admin/tenants/{tenant_id}` | Tenant-specific dashboard (business users) |

**Responsibilities**:
- Load dashboard widgets (upcoming bookings, statistics)
- Count bookings by status
- Display booking patterns and metrics

### Admin\BookingsController

**File**: `app/Controllers/Admin/BookingsController.php`

CRUD operations for bookings (cross-tenant and tenant-scoped).

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/bookings` | Cross-tenant booking list (operator) |
| `show()` | `GET /admin/bookings/{id}` | Cross-tenant booking detail |
| `updateStatus()` | `POST /admin/bookings/{id}/status` | Change booking status |
| `reschedule()` | `POST /admin/bookings/{id}/reschedule` | Admin reschedule |
| `tenantIndex()` | `GET /admin/tenants/{tenant_id}/bookings` | Tenant booking list |
| `tenantCreate()` | `GET /admin/tenants/{tenant_id}/bookings/create` | Manual booking form |
| `tenantStore()` | `POST /admin/tenants/{tenant_id}/bookings/create` | Create booking from admin |
| `tenantShow()` | `GET /admin/tenants/{tenant_id}/bookings/{id}` | Tenant booking detail |
| `tenantUpdateStatus()` | `POST /admin/tenants/{tenant_id}/bookings/{id}/status` | Tenant status update |
| `tenantReschedule()` | `POST /admin/tenants/{tenant_id}/bookings/{id}/reschedule` | Tenant reschedule |
| `export()` | `GET /admin/bookings/export` | CSV export (cross-tenant) |
| `tenantExport()` | `GET /admin/tenants/{tenant_id}/bookings/export` | CSV export (tenant) |

**Responsibilities**:
- Query bookings with filters/pagination
- Validate and update booking status
- Handle rescheduling logic
- Export bookings to CSV
- Enforce authorization (operator vs. business user)

### Admin\ServiceController

**File**: `app/Controllers/Admin/ServiceController.php`

Timeslot pattern: services management.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/tenants/{tenant_id}/services` | Service list |
| `create()` | `GET /admin/tenants/{tenant_id}/services/create` | Service form |
| `store()` | `POST /admin/tenants/{tenant_id}/services` | Create service |
| `edit()` | `GET /admin/tenants/{tenant_id}/services/{id}/edit` | Service form |
| `update()` | `POST /admin/tenants/{tenant_id}/services/{id}` | Update service |
| `activate()` | `POST /admin/tenants/{tenant_id}/services/{id}/activate` | Activate service |
| `deactivate()` | `POST /admin/tenants/{tenant_id}/services/{id}/deactivate` | Deactivate service |
| `reorder()` | `POST /admin/tenants/{tenant_id}/services/{id}/reorder` | Drag-drop reordering |

**Responsibilities**:
- CRUD for services
- Manage service properties (duration, price, category)
- Link services to staff (service_staff table)
- Validate duration and pricing

### Admin\StaffController

**File**: `app/Controllers/Admin/StaffController.php`

Timeslot pattern: staff management.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/tenants/{tenant_id}/staff` | Staff list |
| `create()` | `GET /admin/tenants/{tenant_id}/staff/create` | Staff form |
| `store()` | `POST /admin/tenants/{tenant_id}/staff/create` | Create staff |
| `edit()` | `GET /admin/tenants/{tenant_id}/staff/{id}/edit` | Staff form |
| `update()` | `POST /admin/tenants/{tenant_id}/staff/{id}/edit` | Update staff |
| `activate()` | `POST /admin/tenants/{tenant_id}/staff/{id}/activate` | Activate staff |
| `deactivate()` | `POST /admin/tenants/{tenant_id}/staff/{id}/deactivate` | Deactivate staff |
| `reorder()` | `POST /admin/tenants/{tenant_id}/staff/{id}/reorder` | Drag-drop reordering |

**Responsibilities**:
- CRUD for staff members
- Manage staff details (name, email, avatar)
- Validate email uniqueness per tenant

### Admin\AvailabilityController

**File**: `app/Controllers/Admin/AvailabilityController.php`

Timeslot pattern: weekly availability rules.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/tenants/{tenant_id}/availability` | Availability matrix view |
| `save()` | `POST /admin/tenants/{tenant_id}/availability` | Save weekly rules |
| `staffOverride()` | `GET /admin/tenants/{tenant_id}/availability/staff/{id}` | Staff-specific rules |
| `saveStaffOverride()` | `POST /admin/tenants/{tenant_id}/availability/staff/{id}` | Save staff rules |
| `resetStaffOverride()` | `POST /admin/tenants/{tenant_id}/availability/staff/{id}/reset` | Clear staff overrides |

**Responsibilities**:
- Manage day-of-week availability (Mon–Sun)
- Store tenant-level defaults + staff overrides
- Validate time windows (start < end)
- Render calendar UI for rule editing

### Admin\BlockedDatesController

**File**: `app/Controllers/Admin/BlockedDatesController.php`

Blackout periods (vacation, maintenance).

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/tenants/{tenant_id}/blocked-dates` | Blocked dates list |
| `store()` | `POST /admin/tenants/{tenant_id}/blocked-dates` | Create blocked period |
| `delete()` | `POST /admin/tenants/{tenant_id}/blocked-dates/{id}/delete` | Delete blocked period |

**Responsibilities**:
- Create date ranges when no bookings allowed
- Scope to tenant, staff, or resource
- Validate date ranges (start ≤ end)

### Admin\ResourceController

**File**: `app/Controllers/Admin/ResourceController.php`

Resource pattern: rooms/units management.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/tenants/{tenant_id}/resources` | Resources list |
| `create()` | `GET /admin/tenants/{tenant_id}/resources/create` | Resource form |
| `store()` | `POST /admin/tenants/{tenant_id}/resources` | Create resource |
| `edit()` | `GET /admin/tenants/{tenant_id}/resources/{id}/edit` | Resource form |
| `update()` | `POST /admin/tenants/{tenant_id}/resources/{id}` | Update resource |
| `activate()` | `POST /admin/tenants/{tenant_id}/resources/{id}/activate` | Activate resource |
| `deactivate()` | `POST /admin/tenants/{tenant_id}/resources/{id}/deactivate` | Deactivate resource |
| `reorder()` | `POST /admin/tenants/{tenant_id}/resources/{id}/reorder` | Drag-drop reordering |

**Responsibilities**:
- CRUD for resources
- Manage check-in/check-out day constraints
- Validate capacity and pricing

### Admin\CapacitySlotsController

**File**: `app/Controllers/Admin/CapacitySlotsController.php`

Capacity pattern: slot management.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/tenants/{tenant_id}/capacity-slots` | Slots list |
| `store()` | `POST /admin/tenants/{tenant_id}/capacity-slots` | Create/update slot |
| `toggleActive()` | `POST /admin/tenants/{tenant_id}/capacity-slots/{id}/toggle` | Enable/disable slot |
| `delete()` | `POST /admin/tenants/{tenant_id}/capacity-slots/{id}/delete` | Delete slot |

**Responsibilities**:
- Create recurring or one-off slots
- Manage capacity, party size limits, pricing
- Calculate remaining seats in real-time

### Admin\EventsController

**File**: `app/Controllers/Admin/EventsController.php`

Event pattern: events with RRULE support.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/tenants/{tenant_id}/events` | Events list |
| `create()` | `GET /admin/tenants/{tenant_id}/events/create` | Event form |
| `store()` | `POST /admin/tenants/{tenant_id}/events` | Create event |
| `edit()` | `GET /admin/tenants/{tenant_id}/events/{id}/edit` | Event form |
| `update()` | `POST /admin/tenants/{tenant_id}/events/{id}` | Update event |
| `toggleActive()` | `POST /admin/tenants/{tenant_id}/events/{id}/toggle` | Enable/disable event |
| `delete()` | `POST /admin/tenants/{tenant_id}/events/{id}/delete` | Delete event |

**Responsibilities**:
- CRUD for events
- Handle RRULE parsing and expansion
- Manage exception dates (canceled occurrences)
- Validate recurring event configuration

### Admin\CalendarController

**File**: `app/Controllers/Admin/CalendarController.php`

Calendar views for bookings.

| Method | Route | Purpose |
|--------|-------|---------|
| `month()` | `GET /admin/tenants/{tenant_id}/calendar` | Month view (default) |
| `week()` | `GET /admin/tenants/{tenant_id}/calendar/week` | Week view |
| `day()` | `GET /admin/tenants/{tenant_id}/calendar/day` | Day view |

**Responsibilities**:
- Query bookings for date range
- Aggregate by staff/resource
- Format for calendar UI
- Handle timezone conversions

### Admin\CustomersController

**File**: `app/Controllers/Admin/CustomersController.php`

Customer management and GDPR data access.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/tenants/{tenant_id}/customers` | Customer list |
| `show()` | `GET /admin/tenants/{tenant_id}/customers/{id}` | Customer detail + booking history |
| `export()` | `GET /admin/tenants/{tenant_id}/customers/export` | CSV export |

**Responsibilities**:
- Query customers with pagination
- Show booking history
- Export customer data
- Handle anonymization status display

### Admin\TenantsController

**File**: `app/Controllers/Admin/TenantsController.php`

Operator-only: tenant (business) management.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/tenants` | Tenant list |
| `export()` | `GET /admin/tenants/export` | CSV export |
| `create()` | `GET /admin/tenants/create` | Tenant creation form |
| `store()` | `POST /admin/tenants/create` | Create tenant |
| `edit()` | `GET /admin/tenants/{id}/edit` | Tenant form |
| `update()` | `POST /admin/tenants/{id}/edit` | Update tenant |
| `archive()` | `POST /admin/tenants/{id}/archive` | Pause/archive tenant |
| `activate()` | `POST /admin/tenants/{id}/activate` | Reactivate tenant |

**Responsibilities**:
- CRUD for businesses
- Validate booking pattern selection
- Set default locale/timezone/currency
- Manage tenant status (active/paused/archived)

### Admin\TenantSettingsController

**File**: `app/Controllers/Admin/TenantSettingsController.php`

Tenant configuration (owner/operator access).

| Method | Route | Purpose |
|--------|-------|---------|
| `general()` | `GET /admin/tenants/{tenant_id}/settings` | General settings form |
| `saveGeneral()` | `POST /admin/tenants/{tenant_id}/settings` | Save general |
| `branding()` | `GET /admin/tenants/{tenant_id}/settings/branding` | Branding form |
| `saveBranding()` | `POST /admin/tenants/{tenant_id}/settings/branding` | Save branding |
| `bookingPage()` | `GET /admin/tenants/{tenant_id}/settings/bookingpage` | Booking page content |
| `saveBookingPage()` | `POST /admin/tenants/{tenant_id}/settings/bookingpage` | Save content |
| `booking()` | `GET /admin/tenants/{tenant_id}/settings/booking` | Booking rules |
| `saveBooking()` | `POST /admin/tenants/{tenant_id}/settings/booking` | Save rules |
| `privacy()` | `GET /admin/tenants/{tenant_id}/settings/privacy` | GDPR/consent settings |
| `savePrivacy()` | `POST /admin/tenants/{tenant_id}/settings/privacy` | Save privacy |
| `notifications()` | `GET /admin/tenants/{tenant_id}/settings/notifications` | Email config |
| `saveNotifications()` | `POST /admin/tenants/{tenant_id}/settings/notifications` | Save email |
| `emails()` | `GET /admin/tenants/{tenant_id}/settings/emails` | Custom email templates |
| `saveEmails()` | `POST /admin/tenants/{tenant_id}/settings/emails` | Save templates |
| `embed()` | `GET /admin/tenants/{tenant_id}/settings/embed` | Embed widget config |
| `saveEmbed()` | `POST /admin/tenants/{tenant_id}/settings/embed` | Save embed |

**Responsibilities**:
- Manage all tenant-level settings
- Validate business rules
- Handle image uploads (logo, cover)
- Validate color/timezone/locale values

### Admin\BusinessUsersController

**File**: `app/Controllers/Admin/BusinessUsersController.php`

Tenant staff management.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/tenants/{tenant_id}/users` | Business users list |
| `invite()` | `GET /admin/tenants/{tenant_id}/users/invite` | Invitation form |
| `store()` | `POST /admin/tenants/{tenant_id}/users/invite` | Send invitation |
| `activate()` | `POST /admin/tenants/{tenant_id}/users/{id}/activate` | Reactivate user |
| `deactivate()` | `POST /admin/tenants/{tenant_id}/users/{id}/deactivate` | Deactivate user |

**Responsibilities**:
- Invite staff to tenant
- Generate invitation tokens
- Send invitation emails
- Manage user activation/deactivation

### Admin\SettingsController

**File**: `app/Controllers/Admin/SettingsController.php`

Operator-only: system settings.

| Method | Route | Purpose |
|--------|-------|---------|
| `general()` | `GET /admin/settings` | System settings form |
| `saveGeneral()` | `POST /admin/settings` | Save settings |
| `email()` | `GET /admin/settings/email` | SMTP configuration |
| `saveEmail()` | `POST /admin/settings/email` | Save SMTP |
| `cron()` | `GET /admin/settings/cron` | Cron token + status |
| `cronRunNow()` | `POST /admin/settings/cron/run` | Trigger cron manually |
| `logs()` | `GET /admin/settings/logs` | View logs |
| `audit()` | `GET /admin/settings/audit` | Audit log view |
| `auditExport()` | `GET /admin/settings/audit/export` | Audit log CSV |

**Responsibilities**:
- Manage SMTP email configuration
- Display cron token
- Show system logs
- Display audit trail

### Admin\UpdateController

**File**: `app/Controllers/Admin/UpdateController.php`

Operator-only: software updates.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/updates` | Updates dashboard |
| `upload()` | `POST /admin/updates/upload` | Upload release archive |
| `applyLocal()` | `POST /admin/updates/apply` | Apply uploaded update |
| `gitStatus()` | `GET /admin/updates/git-status` | Check Git status |
| `gitUpdate()` | `POST /admin/updates/git-update` | Fetch and apply Git update |

**Responsibilities**:
- Detect installed version
- Check for updates (Git or archive)
- Apply updates + run migrations

### Admin\DeletionQueueController

**File**: `app/Controllers/Admin/DeletionQueueController.php`

GDPR Art. 17 deletion request processing.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/deletion-queue` | Pending deletions list |
| `confirm()` | `POST /admin/deletion-queue/confirm` | Anonymize customer |
| `dismiss()` | `POST /admin/deletion-queue/dismiss` | Reject deletion request |

**Responsibilities**:
- Show customers with deletion_requested_at set
- Anonymize customer on confirmation
- Log to audit trail

### Admin\ImpersonationController

**File**: `app/Controllers/Admin/ImpersonationController.php`

Operator-only: assume role of business user.

| Method | Route | Purpose |
|--------|-------|---------|
| `start()` | `POST /admin/tenants/{tenant_id}/impersonate` | Start impersonation |
| `exit()` | `POST /admin/impersonate/exit` | Return to operator role |

**Responsibilities**:
- Store impersonation state in session
- Enforce operator-only access
- Log impersonation to audit trail

### Admin\ApplicationsController

**File**: `app/Controllers/Admin/ApplicationsController.php`

Operator-only: multi-tenant application approvals (future).

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /admin/applications` | Pending applications |
| `approve()` | `POST /admin/applications/{id}/approve` | Approve and create tenant |
| `reject()` | `POST /admin/applications/{id}/reject` | Reject application |

**Responsibilities**:
- Review business applications
- Create tenant on approval
- Send approval/rejection emails

### Admin\AccountController

**File**: `app/Controllers/Admin/AccountController.php`

User account settings (operator and business users).

| Method | Route | Purpose |
|--------|-------|---------|
| `show()` | `GET /admin/account` | Account form |
| `save()` | `POST /admin/account` | Update name, avatar, password |

**Responsibilities**:
- Edit user profile (name, avatar)
- Change password
- Validate password requirements

### Booking\BookingPageController

**File**: `app/Controllers/Booking/BookingPageController.php`

Public-facing booking page.

| Method | Route | Purpose |
|--------|-------|---------|
| `show()` | `GET /book/{slug}` | Booking page shell |
| `manage()` | `GET /book/{slug}/manage/{booking_id}` | Booking management (no auth) |

**Responsibilities**:
- Resolve tenant by slug
- Render booking page Alpine app
- Resolve locale
- Show booking details for self-management

### Booking\BookingApiController

**File**: `app/Controllers/Booking/BookingApiController.php`

Public booking API (no authentication).

| Method | Route | Purpose |
|--------|-------|---------|
| `services()` | `GET /api/{slug}/services` | List services |
| `staff()` | `GET /api/{slug}/staff` | List staff (per service) |
| `availability()` | `GET /api/{slug}/availability` | Get available slots |
| `availableDates()` | `GET /api/{slug}/available-dates` | Calendar of available dates |
| `resources()` | `GET /api/{slug}/resources` | List resources |
| `resourceAvailability()` | `GET /api/{slug}/resources/{id}/availability` | Resource availability grid |
| `capacityAvailableDates()` | `GET /api/{slug}/capacity/available-dates` | Capacity calendar |
| `capacitySlots()` | `GET /api/{slug}/capacity/slots` | Get capacity slots |
| `events()` | `GET /api/{slug}/events` | List events |
| `eventDetail()` | `GET /api/{slug}/events/{id}` | Event detail |
| `createBooking()` | `POST /api/{slug}/bookings` | Create booking |
| `bookingDetail()` | `GET /api/{slug}/bookings/{id}` | Get booking (no auth) |
| `cancelBookingAction()` | `POST /api/{slug}/bookings/{id}/cancel` | Cancel booking (no auth) |
| `rescheduleBookingAction()` | `POST /api/{slug}/bookings/{id}/reschedule` | Reschedule booking (no auth) |

**Responsibilities**:
- Delegate to appropriate Calculator per pattern
- Validate availability server-side
- Create/update/cancel bookings
- Send confirmation emails
- Return JSON for Alpine.js frontend

### Booking\PrivacyController

**File**: `app/Controllers/Booking/PrivacyController.php`

GDPR data subject access rights.

| Method | Route | Purpose |
|--------|-------|---------|
| `show()` | `GET /book/{slug}/privacy/{customer_id}` | Privacy page (no auth) |
| `action()` | `POST /book/{slug}/privacy/{customer_id}` | Export or request deletion |

**Responsibilities**:
- Load customer data without authentication
- Render privacy controls
- Export personal data as JSON
- Request customer deletion

### Booking\EmbedController

**File**: `app/Controllers/Booking/EmbedController.php`

Embed widget for external websites.

| Method | Route | Purpose |
|--------|-------|---------|
| `script()` | `GET /embed/{slug}.js` | Widget JavaScript |
| `config()` | `GET /api/{slug}/embed-config` | Widget configuration |

**Responsibilities**:
- Return minified embed script
- Return JSON config (domain-validated)

### Auth\AuthController

**File**: `app/Controllers/Auth/AuthController.php`

Authentication endpoints.

| Method | Route | Purpose |
|--------|-------|---------|
| `showLogin()` | `GET /admin/login` | Login form |
| `login()` | `POST /admin/login` | Process login |
| `logout()` | `POST /auth/logout` | End session |
| `showForgotPassword()` | `GET /admin/forgot-password` | Forgot password form |
| `forgotPassword()` | `POST /admin/forgot-password` | Request reset token |
| `showResetPassword()` | `GET /admin/reset-password` | Reset password form |
| `resetPassword()` | `POST /admin/reset-password` | Validate token + set password |
| `requestCode()` | `POST /admin/login/request-code` | Request OTP/magic link |
| `showVerifyCode()` | `GET /admin/login/verify-code` | OTP verification form |
| `verifyCode()` | `POST /admin/login/verify-code` | Verify OTP |
| `verifyMagicLink()` | `GET /admin/login/verify` | Verify magic link |

**Responsibilities**:
- Validate login credentials
- Create sessions
- Generate and validate tokens
- Send reset/verification emails

### AgentApi\ResourceController

**File**: `app/Controllers/AgentApi/ResourceController.php`

LLM agent API resource endpoints.

| Method | Route | Purpose |
|--------|-------|---------|
| `tenants()` | `GET /api/agent/v1/tenants` | List tenants |
| `bookings()` | `GET /api/agent/v1/bookings` | List bookings |
| `services()` | `GET /api/agent/v1/services` | List services |
| `availability()` | `GET /api/agent/v1/availability` | Get availability |

**Responsibilities**:
- Return resources scoped to API key
- Implement pagination
- Return JSON for LLM consumption

### AgentApi\SchemaController

**File**: `app/Controllers/AgentApi/SchemaController.php`

OpenAPI schema endpoint (public).

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /api/agent/v1/schema` | Return OpenAPI schema |

**Responsibilities**:
- Return OpenAPI 3.0 schema
- Document all agent API endpoints

### Install\WizardController

**File**: `app/Controllers/Install/WizardController.php`

Installation wizard (pre-installation only).

| Method | Route | Purpose |
|--------|-------|---------|
| `show()` | `GET /install` | Wizard UI (step 1) |
| `stepTwo()` | `POST /install/step/2` | Database configuration |
| `stepThree()` | `POST /install/step/3` | Email configuration |
| `stepFour()` | `POST /install/step/4` | Operator account creation |
| `stepFive()` | `POST /install/step/5` | First business creation |
| `complete()` | `POST /install/complete` | Finalize installation |

**Responsibilities**:
- Verify system requirements
- Validate database connection
- Run migrations
- Write .env file
- Create operator + first business

### HomeController

**File**: `app/Controllers/HomeController.php`

Root URL and public pages.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /` | Redirect (to /admin or /install) |
| `submitRequest()` | `POST /request-access` | Contact form submission |

### HealthController

**File**: `app/Controllers/HealthController.php`

Health check endpoint.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /health` | Health check |

**Response**: `{ status: "ok", timestamp: ... }`

### SeoController

**File**: `app/Controllers/SeoController.php`

SEO endpoints.

| Method | Route | Purpose |
|--------|-------|---------|
| `robots()` | `GET /robots.txt` | robots.txt |
| `sitemap()` | `GET /sitemap.xml` | sitemap.xml |

### CronController

**File**: `app/Controllers/CronController.php`

Background jobs trigger.

| Method | Route | Purpose |
|--------|-------|---------|
| `run()` | `GET /cron/run` | Execute pending jobs |

**Responsibilities**:
- Trigger ReminderJob
- Trigger RetentionJob

---

## Engine Classes (Business Logic)

### Core Framework

**App.php**: Application kernel. Boots environment, initializes logger, dispatcher.

**Router.php**: HTTP router. Dispatches requests through middleware pipeline.

**Request.php**: HTTP request object. Wraps $_GET, $_POST, $_SERVER, headers.

**Response.php**: HTTP response object. Manages status, headers, body, redirects.

**Database.php**: PDO wrapper. Executes prepared statements, no query builder.

**Migrator.php**: Runs SQL migrations from `app/Migrations/`.

**EnvLoader.php**: Loads `.env` file into `$_ENV`.

**EnvWriter.php**: Writes `.env` file (for installer).

**Logger.php**: File-based logging to `storage/logs/`.

**Version.php**: Version management (reads from `VERSION` file).

### Availability Calculators

**TimeSlotCalculator.php**:
- `getAvailableSlots()`: Returns available time slots for a date
- `resolveEligibleStaff()`: Get staff for service
- `resolveAvailabilityWindows()`: Query weekly rules
- `generateSlotTimes()`: Create 30-min slots

**ResourceCalculator.php**:
- `getAvailableResources()`: List available resources for dates
- `checkResourceAvailability()`: Is resource free during range?
- `validateCheckInCheckOut()`: Day-of-week constraints

**CapacityCalculator.php**:
- `getAvailableSlots()`: Slots with remaining capacity
- `calculateRemainingSeats()`: Capacity - confirmed bookings

**EventCalculator.php**:
- `getUpcomingEvents()`: Recurring events expanded by RRULE
- `expandRecurringEvent()`: Use rlanvin/php-rrule library
- `applyExceptionDates()`: Remove canceled occurrences

### Services

**BookingService.php**:
- `createBooking()`: Central booking creation with GDPR consent capture
- `resolveConsentText()`: Get exact consent text for evidence

**CustomerService.php**:
- `getOrCreate()`: Lookup or create customer by email
- `anonymize()`: Clear PII (GDPR Art. 17)
- `requestDeletion()`: Mark for manual review

**Mailer.php**:
- `send()`: SMTP delivery with retry
- `renderTemplate()`: PHP template rendering
- `logToDatabase()`: Record email in email_log

**ReminderJob.php**:
- `run()`: Send pending reminders (triggered by cron)

**RetentionJob.php**:
- `run()`: Anonymize customers after data_retention_months (cron)

**DataExporter.php**:
- `export()`: JSON export of customer data (GDPR Art. 20)

**CustomerAnonymizer.php**:
- `anonymizeCustomer()`: Clear PII, preserve consent records

### Authentication

**Auth.php**:
- `startSession()`: PHP session_start()
- `user()`: Get current logged-in user
- `logout()`: Destroy session
- `requireLogin()`: Middleware check

**AgentAuth.php**:
- `verifyApiKey()`: Validate Bearer token

**LoginToken.php**:
- `generate()`: Create time-limited reset/verification token
- `verify()`: Check token validity and expiry

### Utilities

**Ulid.php**:
- `generate()`: Create 128-bit ULID
- Uses robinvdvleuten/ulid library

**AuditLog.php**:
- `log()`: Append entry to audit_log table
- `nullifyPassword()`: Don't log passwords

**Locale.php**:
- `resolveForBooking()`: Accept-Language chain for booking page
- `resolveForAdmin()`: Session locale for admin panel
- `getFormatting()`: Date/time/number format rules

**View.php**:
- `response()`: Render PHP template to Response
- `render()`: Render and return string

**Validator.php**:
- `validateBooking()`: Validate booking data against tenant rules
- `validateEmail()`, `validatePhone()`: Format checks

**ImageUpload.php**:
- `handle()`: Process logo/avatar upload
- `validate()`: Check file type/size
- `store()`: Save to public/uploads/

**FormState.php**:
- `restore()`: Re-populate form after validation error

**Flash.php**:
- `put()`: Store message in session
- `get()`: Retrieve and clear message

**BrandColorHelper.php**:
- `isDarkBrand()`: Luminance calculation for text contrast

**GitUpdater.php**:
- `checkStatus()`: `git status` output
- `fetch()`: `git fetch --tags`
- `update()`: `git checkout tag` + run migrations

---

## Middleware

All middleware implement:

```php
public function handle(Request $request, callable $next): Response
```

**SecurityMiddleware.php**:
- Set security headers (X-Frame-Options, X-Content-Type-Options)
- Force HTTPS redirect (on production)
- Set charset UTF-8

**InstalledMiddleware.php**:
- Check if app is installed (via settings.installed_at)
- Redirect to /install if not

**AuthMiddleware.php**:
- Verify session exists
- Redirect to /admin/login if not
- Auto-redirect business users to /admin/tenants/{tenant_id}

**CsrfMiddleware.php**:
- Generate token in session
- Validate token on POST/PUT/DELETE
- Reject if mismatch (403 Forbidden)

**ThrottleMiddleware.php**:
- Track requests per IP/endpoint
- Reject if over limit (429 Too Many Requests)

**DemoMiddleware.php**:
- Block writes (POST/PUT/DELETE) if demo mode active
- Except login/logout

**AgentAuthMiddleware.php**:
- Validate API key in Authorization header
- Reject if invalid (401 Unauthorized)

---

## Models (Data Access)

All models provide static query methods, no ORM.

**Booking.php**:
- `find()`: Single booking by ID
- `all()`: Cross-tenant list with filters
- `forTenant()`: Per-tenant list
- `forTenantDate()`: Bookings on single date
- `forTenantDateRange()`: Bookings in range
- `statusCounts()`: Count by status
- `updateStatus()`: Change status
- `countAll()`, `countForTenant()`: Aggregate

**Customer.php**:
- `find()`: Single customer
- `getOrCreate()`: Lookup or insert
- `all()`: Per-tenant list
- `count()`: Total count
- `anonymize()`: Clear PII

**Tenant.php**:
- `find()`: Single tenant by ID
- `findBySlug()`: Resolve slug to tenant
- `all()`: List (operator-only)
- `create()`: Insert new tenant
- `update()`: Modify settings
- `archive()`, `activate()`: Change status

---

## Data Validation

**Config Files**

**config/locales.php**:
- Registry of supported locales
- Formatting rules (date, time, number, week_start)
- ISO 639-1 codes (en, es, de, fr, ...)

**config/currencies.php**:
- ISO 4217 code → symbol mapping
- USD → $, EUR → €, etc.

---

## Frontend (Client-Side)

### JavaScript (Alpine.js)

**resources/js/booking/app.js**:
- Alpine component for booking page
- Date/time picker, service selector, availability loader
- Form validation + CSRF token
- Timezone detection

**resources/js/booking/timezone.js**:
- Detect browser timezone
- Convert times to/from business timezone

**resources/js/booking/party-size.js**:
- Validate party size constraints (capacity/event patterns)

**resources/js/admin/app.js**:
- Alpine components for admin (modal, tooltips, form actions)
- Calendar month/week/day view
- Drag-drop for reordering

**resources/js/admin/form-validator.js**:
- Client-side form validation
- Show inline errors

**resources/js/admin/tooltips.js**:
- Tooltip popovers on hover

### CSS (Tailwind 4)

**resources/css/admin.css**:
- Admin panel styling
- Tailwind + custom tokens

**resources/css/booking.css**:
- Booking page styling

---

## Templates (PHP Views)

**templates/booking/index.php**:
- Booking page shell
- Alpine app mounts here

**templates/admin/layout.php**:
- Admin master layout
- Topbar, sidebar, main content area

**templates/admin/dashboard.php**:
- Operator/business user dashboard
- Widgets, bookings list, metrics

**templates/admin/bookings/index.php**:
- Booking list with filters
- Pagination, status columns

**templates/admin/services/**, **templates/admin/staff/**, etc.:
- CRUD forms for each resource type

---

## Testing

**tests/Unit/**:
- `TimeSlotCalculatorTest.php`: Slot availability logic
- `CapacityCalculatorTest.php`: Capacity logic
- `CustomerAnonymizerTest.php`: GDPR anonymization

**tests/Integration/**:
- `TimeslotBookingFlowTest.php`: End-to-end booking
- `CapacityBookingFlowTest.php`: E2E capacity
- `EventBookingFlowTest.php`: E2E event pattern

All tests use phpunit with assertions.

---

## Summary Table

| Component | Type | Responsibility |
|-----------|------|-----------------|
| `App.php` | Core | Boot, initialize, dispatch |
| `Router.php` | Core | Route matching, middleware pipeline |
| `Request/Response` | Core | HTTP abstraction |
| `Database.php` | Core | PDO queries, prepared statements |
| `TimeSlotCalculator` | Engine | Timeslot availability |
| `BookingService` | Engine | Booking creation, consent capture |
| `Mailer` | Engine | Email delivery |
| `Auth` | Engine | Session management |
| `Booking` | Model | Booking queries |
| `BookingApiController` | Controller | Public booking API |
| `BookingsController` | Controller | Admin booking CRUD |
| `AuthMiddleware` | Middleware | Session validation |
| `booking/app.js` | Frontend | Booking UI (Alpine) |
| `admin/app.js` | Frontend | Admin UI (Alpine) |

All 40+ classes work together to provide booking infrastructure across four patterns.
