# VoxelBooking Authentication & Authorization

## Table of Contents
1. [Authentication Methods](#authentication-methods)
2. [Session Management](#session-management)
3. [Authorization Hierarchy](#authorization-hierarchy)
4. [Admin Login Flows](#admin-login-flows)
5. [Business User Management](#business-user-management)
6. [API Authentication (Agent API)](#api-authentication-agent-api)
7. [Security Measures](#security-measures)

---

## Authentication Methods

VoxelBooking supports three authentication mechanisms:

### 1. Password-Based Authentication
Traditional email + password login for operators and business users.

### 2. Magic Link / Passwordless Authentication
Email-based token generation for users who forget passwords or prefer passwordless flow.

### 3. API Key Authentication
Bearer token for LLM agents and programmatic access (Agent API).

---

## Session Management

### Session Configuration

```php
// PHP session settings (8-hour expiry)
session.name = 'vb_session'
session.save_path = storage/sessions/
session.gc_maxlifetime = 28800  // 8 hours
session.cookie_lifetime = 0      // Browser session (expires on close)
session.cookie_secure = true     // HTTPS only (on production)
session.cookie_httponly = true   // Not accessible to JavaScript
session.cookie_samesite = 'Lax'  // CSRF protection
```

### Session Lifecycle

```
1. User visits /admin/login
   └─ No session yet, login form shown

2. User submits credentials (POST /admin/login)
   ├─ Verify email + password
   ├─ If invalid: redirect to /admin/login with error
   ├─ If valid:
   │  ├─ Call Auth::startSession()
   │  │  ├─ session_start()
   │  │  ├─ $_SESSION['user_id'] = $operator['id']
   │  │  ├─ $_SESSION['email'] = $operator['email']
   │  │  ├─ $_SESSION['role'] = 'operator' or 'business_user'
   │  │  ├─ $_SESSION['tenant_id'] = $current_tenant (if business user)
   │  │  └─ $_SESSION['logged_in_at'] = NOW()
   │  ├─ Log to audit trail: AuditLog::log('login.success', ...)
   │  └─ Redirect to /admin dashboard

3. User visits admin pages (with valid session)
   ├─ AuthMiddleware checks session_id and $_SESSION['user_id']
   ├─ If not set: redirect to /admin/login
   ├─ If set: pass through to controller
   └─ User stays logged in (session renewed on each request)

4. User logs out (POST /auth/logout)
   ├─ Log to audit trail: AuditLog::log('logout', ...)
   ├─ session_destroy()
   ├─ vb_session cookie cleared
   └─ Redirect to /admin/login

5. Session expires (8 hours, no activity)
   └─ User visits admin page → AuthMiddleware redirect to /admin/login
```

### Auth::startSession() Helper

```php
Auth::startSession()
  ├─ if (!session_id()) session_start()
  └─ // Session now active, can read/write $_SESSION

Auth::user()
  ├─ if (!$_SESSION['user_id']) return null
  ├─ Query operators or business_users table
  └─ Return user record or null

Auth::logout()
  ├─ Log to audit trail
  ├─ session_destroy()
  └─ header('Location: /admin/login')
```

---

## Authorization Hierarchy

### Role-Based Access Control (RBAC)

Three tiers of authorization:

#### 1. Operators (Super Admins)
- Email: stored in `operators` table
- Password: bcrypt hash
- Access: All businesses, system settings, user management, updates

```
Operator permissions:
├─ Dashboard (all tenants)
├─ Tenant management (create, edit, archive)
├─ Settings (system-wide email, updates, cron)
├─ Bookings (cross-tenant view)
├─ Customers (cross-tenant view)
├─ Audit log (full access)
├─ Deletion queue (GDPR review)
├─ Business users (invite/deactivate per tenant)
├─ Applications (approve/reject multi-tenant requests)
└─ Impersonation (can become any business user)
```

**Routing**: Operators reach `/admin` and see all tenants.

#### 2. Business Users (Tenant Staff)

Stored in `business_users` table with `role` column:

**Owner**:
- Email: registered in business_users
- Password: set during invitation or first login
- Access: Full control of own business

```
Owner permissions:
├─ Dashboard (own tenant only)
├─ Bookings (full CRUD)
├─ Calendar (all views)
├─ Services (create, edit, activate/deactivate)
├─ Staff (create, edit, activate/deactivate)
├─ Resources (create, edit, activate/deactivate)
├─ Events (create, edit, recurring)
├─ Capacity slots (create, edit)
├─ Availability rules (set weekly + staff overrides)
├─ Blocked dates (create, delete)
├─ Customers (view only, export)
├─ Settings (full: branding, booking rules, emails, privacy, notifications)
├─ Business users (invite/deactivate other staff)
└─ Billing (future: payments, invoices)
```

**Manager**:
- Same role membership as owner but restricted actions (enforced in controller)

```
Manager permissions:
├─ Dashboard (own tenant)
├─ Bookings (read, create, reschedule, cancel)
├─ Calendar (view only, month/week/day)
├─ Services (view only)
├─ Staff (view only)
├─ Customers (view only, export)
└─ Availability (view only)
```

**Staff**:
- View bookings, manage own schedule (future)

**Routing**: Business users reach `/admin` → AuthMiddleware detects business_user → redirects to `/admin/tenants/{tenant_id}` (tenant-scoped dashboard).

#### 3. Public Customers
- No authentication required for booking page
- Booking ID = bearer token (128-bit ULID entropy)

```
Customer capabilities:
├─ View business booking page (/book/{slug})
├─ Create booking (POST /api/{slug}/bookings)
├─ View own booking details (GET /book/{slug}/manage/{booking_id})
├─ Cancel/reschedule booking (POST /api/{slug}/bookings/{id}/cancel or /reschedule)
├─ Access privacy page (/book/{slug}/privacy/{customer_id})
└─ Export personal data (GET /book/{slug}/privacy/{customer_id})
```

### Authorization Checks (Examples)

**In Controller**:
```php
// Example: ServiceController::update()
public function update(Request $request): Response
{
    $tenantId = $request->getAttribute('tenant_id');
    $user = Auth::user();
    
    // Check 1: Is user logged in?
    if (!$user) {
        return Response::redirect('/admin/login');
    }
    
    // Check 2: Is user an operator OR owner of this tenant?
    if ($user['role'] !== 'operator' && $user['tenant_id'] !== $tenantId) {
        return Response::forbidden();
    }
    
    // Check 3: Is user an operator OR owner (not manager)?
    if ($user['role'] === 'manager') {
        return Response::forbidden();
    }
    
    // Proceed with update...
}
```

**In Middleware**:
```php
// AuthMiddleware: Protect admin routes
class AuthMiddleware {
    public function handle(Request $request, callable $next): Response {
        Auth::startSession();
        if (!Auth::user()) {
            return Response::redirect('/admin/login');
        }
        return $next($request);
    }
}
```

---

## Admin Login Flows

### Password-Based Login

**Request**:
```
GET /admin/login
├─ Render login form (email + password)
└─ Show demo accounts (if demo mode enabled)

POST /admin/login
{
  "email": "admin@voxelbooking.com",
  "password": "secret123"
}
```

**Process** (`AuthController::login()`):
```
1. Validate input
   ├─ email is valid email format?
   ├─ password is not empty?
   └─ csrf_token is valid?

2. Query user
   ├─ Try operators table first
   │  └─ SELECT * FROM operators WHERE email = ? LIMIT 1
   ├─ If not found, try business_users
   │  └─ SELECT * FROM business_users WHERE email = ? AND tenant_id = ?

3. Verify password
   ├─ password_verify($input_password, $user['password_hash'])?
   ├─ If NO: redirect to login with error, log failed attempt
   └─ If YES: proceed

4. Create session
   ├─ Auth::startSession()
   ├─ $_SESSION['user_id'] = $user['id']
   ├─ $_SESSION['email'] = $user['email']
   ├─ $_SESSION['role'] = 'operator' or 'business_user'
   ├─ $_SESSION['tenant_id'] = $user['tenant_id'] (if business user)
   └─ Log to audit trail: AuditLog::log('login.success', ...)

5. Redirect
   ├─ If operator: /admin (cross-tenant dashboard)
   └─ If business user: /admin/tenants/{tenant_id} (tenant-scoped dashboard)
```

### Passwordless Login (Magic Link)

**Request 1: Email Submission**:
```
POST /admin/login/request-code
{
  "email": "admin@voxelbooking.com"
}
```

**Process**:
```
1. Query user (operators only; business users use password)
2. Generate LoginToken
   ├─ LoginToken::generate()
   ├─ Token = random 32 bytes, base64-encoded
   ├─ Hash = SHA-256(token)
   ├─ Store hash + operator_id + expires_at (15 min from now) in temporary table
   └─ Return token
3. Send email with verification link
   ├─ To: admin@voxelbooking.com
   ├─ Link: /admin/login/verify?token={token}
   ├─ Expires: 15 minutes
4. Return "Check your email"
```

**Request 2: Magic Link Verification**:
```
GET /admin/login/verify?token=abc123def456...
```

**Process**:
```
1. Query token from temporary table
   ├─ Hash the provided token
   ├─ Find hash in table
   ├─ Check expires_at > NOW()?
   ├─ If not found/expired: show error
   └─ If found and valid: proceed

2. Look up operator
   ├─ Query operators WHERE id = ?

3. Create session (same as password flow)

4. Delete token (one-time use)

5. Redirect to /admin dashboard
```

### Passwordless Login (OTP via Email)

Alternative to magic link (not yet fully implemented):
```
POST /admin/login/request-code
{
  "email": "admin@voxelbooking.com"
}
→ Generates 6-digit code, sends via email

GET /admin/login/verify-code
→ Form to enter code

POST /admin/login/verify-code
{
  "code": "123456"
}
→ Validates, creates session
```

### Password Reset

**Request 1: Forgot Password Form**:
```
GET /admin/forgot-password
├─ Render form: email input
└─ Submit: POST /admin/forgot-password
```

**Process**:
```
1. Query user by email
2. Generate LoginToken (same as magic link)
3. Send email with reset link
   ├─ To: user@example.com
   ├─ Link: /admin/reset-password?token={token}
   ├─ Expires: 1 hour
4. Return "Check your email"
```

**Request 2: Reset Password Form**:
```
GET /admin/reset-password?token=abc123...
├─ Validate token
├─ Render form: new_password, confirm_password
└─ Submit: POST /admin/reset-password

POST /admin/reset-password
{
  "token": "abc123...",
  "password": "NewPassword123!",
  "password_confirm": "NewPassword123!"
}
```

**Process**:
```
1. Validate token (same as magic link)
2. Validate password
   ├─ Length ≥ 8 characters
   ├─ Contains uppercase, lowercase, number (enforced client-side only)
3. Hash password: password_hash($password, PASSWORD_BCRYPT)
4. Update operator/business_user
   ├─ UPDATE operators SET password_hash = ? WHERE id = ?
   ├─ Log to audit trail: AuditLog::log('password.reset', ...)
5. Delete token (one-time use)
6. Auto-login? 
   ├─ Optional: create session
   └─ Or: return "Password updated, please log in"
```

---

## Business User Management

### Invitation Flow

**Operator/Owner invites new business user**:

```
GET /admin/tenants/{tenant_id}/users/invite
├─ Render form: email, role (owner|manager|staff)

POST /admin/tenants/{tenant_id}/users/invite
{
  "email": "newuser@company.com",
  "role": "manager"
}
```

**Process**:
```
1. Verify auth (operator or owner)
2. Validate email format
3. Check if user already exists in this tenant
   ├─ SELECT * FROM business_users WHERE tenant_id = ? AND email = ?
   ├─ If exists: return error
4. Create business_user record
   ├─ id = ULID
   ├─ tenant_id = ?
   ├─ email = ?
   ├─ role = ?
   ├─ password_hash = NULL (not yet set)
   ├─ is_active = 1
   ├─ created_at = NOW()
5. Generate invitation token
   ├─ LoginToken::generate()
   ├─ Store with business_user_id + expires_at (7 days)
6. Send invitation email
   ├─ To: newuser@company.com
   ├─ Link: /admin/login/verify?token={token}
   ├─ Body: "You've been invited to manage [Business Name]"
7. Log to audit trail
   ├─ AuditLog::log('business_user.invited', 'business_user', ...)
8. Return success: "Invitation sent"
```

### First Login (Set Password)

When new business user visits invitation link:

```
GET /admin/login/verify?token={token}
├─ Validate token
├─ Detect this is a business_user (password_hash IS NULL)
├─ Render set-password form (instead of auto-login)

POST (implicit in form submission)
├─ Validate token
├─ Validate password
├─ Hash password
├─ UPDATE business_users SET password_hash = ?
├─ Delete invitation token
├─ Create session
└─ Redirect to tenant dashboard
```

### Deactivation

**Owner/Operator deactivates team member**:

```
POST /admin/tenants/{tenant_id}/users/{id}/deactivate
├─ Verify auth
├─ UPDATE business_users SET is_active = 0 WHERE id = ?
├─ Log to audit trail: AuditLog::log('business_user.deactivated', ...)
├─ Invalidate any active sessions (kill session for that user ID)
└─ Return success
```

User can no longer log in. Can be reactivated by owner.

---

## API Authentication (Agent API)

### API Key Generation

**Operator creates API key for tenant**:

```
GET /admin/tenants/{tenant_id}/settings/api-keys
├─ Show existing keys + "Generate New Key" button

POST /admin/tenants/{tenant_id}/settings/api-keys/generate
├─ Verify auth (operator)
├─ Generate API key:
│  ├─ key_prefix = "voxel_" + first 8 chars of base64(random)
│  ├─ full_key = prefix + random 32 bytes
│  ├─ key_hash = SHA-256(full_key)
│  ├─ Store: api_keys (key_prefix, key_hash, name, tenant_id, is_active)
│  └─ Return: full_key (shown once, not stored plain-text)
```

### API Key Authentication

Every authenticated Agent API request:

```
GET /api/agent/v1/tenants
├─ Header: Authorization: Bearer voxel_abcd1234ef567890...

AgentAuthMiddleware::handle()
├─ Extract Bearer token from Authorization header
├─ Verify token format (starts with "voxel_")
├─ Hash token: SHA-256(token)
├─ Query api_keys WHERE key_hash = ? AND is_active = 1
├─ If found:
│  ├─ UPDATE api_keys SET last_used_at = NOW()
│  ├─ Set request.api_key_id and request.tenant_id in session
│  └─ Pass through to controller
├─ If not found:
│  └─ Return 401 Unauthorized
```

### Agent API Endpoints

All require `AgentAuthMiddleware` (API key validation):

```
GET /api/agent/v1/tenants
├─ Return list of tenants accessible to API key
└─ [{id, name, booking_pattern, timezone}, ...]

GET /api/agent/v1/bookings?tenant_id=...
├─ Filter by tenant_id (from API key scope)
└─ [{id, customer_name, service_name, start_datetime, status}, ...]

GET /api/agent/v1/services?tenant_id=...
└─ [{ id, name, duration_minutes, price }, ...]

GET /api/agent/v1/availability?tenant_id=...&date=...&service_id=...
└─ Available slots for date
```

**Use Case**: LLM agents can call these endpoints to:
1. List available services
2. Check availability
3. Create bookings programmatically
4. Retrieve booking details

---

## Security Measures

### CSRF Protection

**CsrfMiddleware**: Validates token on state-changing requests (POST, PUT, DELETE)

```php
// All HTML forms include hidden CSRF token
<input type="hidden" name="csrf_token" value="<?= \App\Middleware\CsrfMiddleware::generateToken() ?>">

// On form submission, middleware verifies:
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    return Response::forbidden("CSRF token mismatch");
}
```

### Rate Limiting

**ThrottleMiddleware**: Per-IP rate limiting

```
Booking page endpoints:
  - GET /book/{slug}: 60 requests/min/IP
  - POST /api/{slug}/bookings: 10 requests/min/IP

Admin endpoints:
  - POST /admin/login: 5 requests/min/IP
  - POST /admin/login/request-code: 5 requests/min/IP

Cron:
  - GET /cron/run: 1 request/min/IP
```

**Storage**: `rate_limits` table with sliding window counters

### Password Hashing

```php
// On password creation/reset
$hash = password_hash($password, PASSWORD_BCRYPT);
  // Default cost = 10
  // Uses constant-time comparison internally

// On login verification
password_verify($submitted, $stored_hash)
  // Returns true/false (constant-time)
```

### Session Security

```
Cookie settings:
  - httponly = true (JS cannot access)
  - secure = true (HTTPS only on production)
  - samesite = 'Lax' (prevents cross-site requests)
  - path = / (entire domain)

Session storage:
  - File-based in storage/sessions/
  - Must NOT be web-accessible
  - .htaccess blocks access
  - Permissions: 0700 (rwx------)

Timeout:
  - 28800 seconds (8 hours)
  - No "remember me" (time-based only)
```

### XSS Prevention

All user input is escaped in templates:

```php
// Unsafe (DO NOT USE)
<p><?= $_POST['name'] ?></p>

// Safe (HTML escape)
<p><?= htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8') ?></p>

// Safe (attribute escape)
<input value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
```

Alpine.js context: content is automatically escaped by Alpine (not interpolated as HTML by default).

### SQL Injection Prevention

All database queries use prepared statements:

```php
// Safe (parameterized)
Database::query(
    "SELECT * FROM operators WHERE email = ? AND is_active = 1",
    [$email]
);

// Unsafe (DO NOT USE)
Database::query("SELECT * FROM operators WHERE email = '$email'");
```

PDO binding is used throughout; no dynamic SQL construction.

### API Key Security

```
Key storage:
  - key_hash (SHA-256) stored in database
  - Full key never re-displayed or logged
  - Shown only once at generation time

Key rotation:
  - Old key can be deactivated without deleting
  - New key generated with different hash
  - Gradual migration for integrations

Audit:
  - last_used_at tracked
  - API key usage logged to audit_log
```

### Audit Logging

Critical actions logged:
- Login/logout (success and failure)
- Password changes + resets
- User creation/invitation/deactivation
- Settings changes (email, retention, consent)
- Booking creation/cancellation/rescheduling
- API key usage

```sql
INSERT INTO audit_log (
  operator_id, entity_type, entity_id, action,
  old_values, new_values, ip_address, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, NOW());
```

Passwords and tokens never logged (nullified before storage).

### GDPR Compliance (Auth Context)

**Email Storage**:
- Stored as-is for login
- Can be anonymized/deleted per GDPR Art. 17

**Session Data**:
- No tracking cookies (only `vb_session`, functional only)
- localStorage stores `vb-theme` (dark/light preference, no PII)
- No analytics or marketing cookies

**Audit Trail**:
- Email addresses stored as SHA-256 prefix (partially anonymized)
- Useful for audit queries without exposing full email
- Enables GDPR data-subject request verification

---

## Authorization Enforcement Patterns

### Pattern 1: Operator-Only Routes

```php
// In controller
if (Auth::user()['role'] !== 'operator') {
    return Response::forbidden();
}
// OR: hardcoded in router (future)
```

Example routes:
- `/admin/settings` (system-wide config)
- `/admin/tenants` (create, list, archive)
- `/admin/applications` (approve/reject)
- `/admin/updates` (apply updates)

### Pattern 2: Tenant-Scoped Routes

```php
// In controller
$tenantId = $request->getAttribute('tenant_id');
$user = Auth::user();

if ($user['role'] === 'operator') {
    // Operator can access any tenant
} else if ($user['role'] === 'business_user') {
    // Business user can access only their tenant
    if ($user['tenant_id'] !== $tenantId) {
        return Response::forbidden();
    }
} else {
    return Response::forbidden();
}
```

Example routes:
- `/admin/tenants/{tenant_id}/services`
- `/admin/tenants/{tenant_id}/bookings`
- `/admin/tenants/{tenant_id}/customers`

### Pattern 3: Role-Specific Actions

```php
// In controller
$user = Auth::user();

if ($user['role'] === 'operator') {
    // Full access
} else if ($user['role'] === 'business_user' && $user['role_type'] === 'owner') {
    // Owner can edit settings
} else if ($user['role'] === 'business_user' && $user['role_type'] === 'manager') {
    // Manager can only view
    return Response::forbidden();
}
```

Example actions:
- Only owners can edit tenant settings
- Managers can view bookings but not create
- Staff can view own schedule only (future)

---

## Future Enhancements

Planned auth features:

1. **Two-Factor Authentication (2FA)**
   - TOTP (Google Authenticator, Authy)
   - SMS codes
   - Recovery codes

2. **Single Sign-On (SSO)**
   - OAuth 2.0 integration (Google, Microsoft)
   - SAML 2.0 for enterprise

3. **Invite-Only Bookings**
   - Customers must provide code to access booking page
   - Whitelist by email domain

4. **Booking Approvals**
   - Customers must wait for staff approval
   - Webhook notifications for approvals

5. **Role-Based Permissions**
   - Custom roles per business
   - Fine-grained permissions (create booking, edit pricing, etc.)
