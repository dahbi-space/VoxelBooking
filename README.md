# VoxelBooking

Self-hosted multi-business booking system. Four booking patterns (time slots, resources, capacity, events) in one codebase. No SaaS lock-in, no outbound telemetry, no CDN dependencies.

## Requirements

- PHP 8.3+
- MySQL 8.0+
- Node.js 20+ (development only)
- Composer (development only)

### Required PHP Extensions

`pdo`, `pdo_mysql`, `curl`, `json`, `mbstring`, `fileinfo`, `openssl`, `gd`, `zip`

## Installation

### Standard setup (Apache, most hosting)

1. Upload the ZIP to your server and extract it into your web root (e.g., `public_html/`)
2. Confirm `mod_rewrite` is enabled (most hosts enable it by default)
3. Navigate to your domain — the root `.htaccess` rewrites traffic into `public/` and blocks access to application directories
4. Follow the 5-step wizard: system check → database → email → operator account → first business

### Document root setup (VPS, dedicated hosting)

If you have full server control, you can point Apache's document root directly to the `public/` directory. This is functionally identical to the standard setup but removes the need for root-level rewrites.

## Local Development

```bash
# Clone the repository
cd /path/to/voxelbooking-app

# Install PHP dependencies
composer install

# Install JS build dependencies
npm install

# Copy environment file
cp .env.example .env
# Edit .env with your local database credentials

# Build assets (production)
npm run build

# Start Vite dev server (HMR)
npm run dev
```

### Local Environment

The app serves via Laravel Herd (or any PHP server) at the domain matching the directory name. The document root must be the `public/` directory (Herd and Valet handle this automatically).

**Database:** MySQL on `127.0.0.1:3309` (default Herd port). Create a database called `voxelbooking`.

```bash
mysql -u root --host=127.0.0.1 --port=3309 -e "CREATE DATABASE IF NOT EXISTS voxelbooking;"
```

### Schema Refresh During Development

The migration files are the source of truth during development. When the data model changes, refresh the local database from scratch instead of adding compatibility layers around stale tables.

```bash
mysql -u root --host=127.0.0.1 --port=3309 -e "DROP DATABASE IF EXISTS voxelbooking; CREATE DATABASE voxelbooking;"
```

Then rerun the installer or your local migration bootstrap against the empty database.

VoxelBooking standardizes on `DATETIME` for persisted date-time columns. Do not introduce new `TIMESTAMP` columns in migrations or schema docs.

### Testing

```bash
# Run full test suite
vendor/bin/phpunit --testdox

# Run only unit tests
vendor/bin/phpunit --testsuite Unit

# Run only integration tests
vendor/bin/phpunit --testsuite Integration

# Run event pattern tests only
vendor/bin/phpunit --filter EventBookingFlowTest

# Run capacity pattern tests only
vendor/bin/phpunit --filter CapacityBookingFlowTest
```

### Build

```bash
# Production build (compiled CSS + JS)
npm run build

# Dev server with hot module replacement
npm run dev
```

## Version

The canonical product version lives in the root [`VERSION`](VERSION) file. This single file is the source of truth — do not hardcode version strings elsewhere.

- The installer seeds `settings.version` from this file
- Admin UI, diagnostics, and update checks read version via `Version::get()`
- Bump this file when cutting a release

## Booking Patterns

Each business is configured with one booking pattern. All four share the same bookings table, admin UI, and email pipeline.

| Pattern | Use Case | Key Engine |
|---------|----------|------------|
| **Timeslot** | Salon, dentist, consultant | `TimeSlotCalculator` — service/staff/slot grid |
| **Resource** | Hotel, rental, co-working | `ResourceCalculator` — per-unit nightly availability, day-of-week check-in/out restrictions |
| **Capacity** | Restaurant, group class, gym | `CapacityCalculator` — party-size against slot min/max, remaining capacity |
| **Event** | Workshop, concert, yoga class | `EventCalculator` — RRULE expansion, waitlist, per-booking spot limits |

## Demo Mode

Create a `.demo` sentinel file in the project root to activate demo mode:

```bash
touch .demo          # activate
php demo-seed.php    # regenerate the demo SQLite database
rm .demo             # deactivate
```

When active:
- `Database::connect()` switches to the pre-seeded SQLite at `storage/demo/demo.db`
- All POST/PUT/DELETE requests are blocked (except login/logout)
- Admin UI shows a persistent "Demo Mode" banner
- Form submissions trigger a toast notification instead of writing
- The login page shows clickable credential cards for all demo accounts
- The booking page shows a notice that submissions are disabled

### Demo accounts

All use password `welcome3210`:

| Account | Email | Role | Sees |
|---------|-------|------|------|
| **Operator** | `demo@voxelbooking.com` | System admin | All 4 businesses |
| **Demo Studio** | `owner@demo-studio.test` | Business owner | Timeslot business only — Services, Staff, Availability |
| **Hotel Marina** | `owner@hotel-marina.test` | Business owner | Resource business only — Resources |
| **Trattoria Roma** | `owner@trattoria-roma.test` | Business owner | Capacity business only — Capacity Slots |
| **Workshop Studio** | `owner@workshop-studio.test` | Business owner | Event business only — Events |

The operator sees all four businesses on the dashboard. Each business user is auto-redirected to their own business and sees only pattern-specific menus. Only the timeslot business has Services; the other patterns show their own management pages.

### MySQL showcase seed (local development)

For local development with a live MySQL database, you can import the same showcase dataset directly:

```bash
php demo-seed-mysql.php    # seed into the MySQL database from .env (DESTRUCTIVE)
```

This drops and recreates all tables in the configured MySQL database, runs all migrations, then seeds the same 4-business dataset. Useful for testing the full write path, not just read-only demo mode.

## Stack

- **Backend:** PHP 8.3+ (custom micro-framework, no Laravel/Symfony)
- **Database:** MySQL 8+ (PDO, prepared statements, no ORM)
- **Admin frontend:** Alpine.js 3 (CSP build), Tailwind CSS 4, Lucide icons
- **Booking page frontend:** Alpine.js 3 (CSP build), Tailwind CSS 4, Lucide icons
- **Build:** Vite 6 with dual entry points (admin + booking)

## Composer Dependencies (runtime)

| Package | Purpose |
|---------|---------|
| `phpmailer/phpmailer` | SMTP email delivery |
| `robinvdvleuten/ulid` | ULID primary key generation |
| `rlanvin/php-rrule` | Recurring event rule expansion |
| `nikic/fast-route` | HTTP route dispatching |

## Directory Structure

```
app/                    PHP application code
  Controllers/          Route handlers (Admin, Booking, Auth, API)
  Engine/               Core framework classes + calculators
  Middleware/           Request middleware (CSRF, Auth, Demo, Rate limit)
  Migrations/           Sequential SQL migrations (001–028)
  Models/               Data models (no ORM)
config/                 Configuration files (locale registry)
lang/                   Translation files (en shipped; nl, de, es, fr, id, it, ja, pt, pl, tr, ar registry-ready)
  en/                   English translations (booking, auth, admin, email, etc.)
public/                 Web root (document root)
  assets/               Compiled CSS/JS (built by Vite)
  uploads/              User-uploaded files (logos, covers)
resources/              Source frontend files
  css/                  Source CSS (Tailwind 4 + design tokens)
  js/                   Source JS (admin + booking)
storage/                Runtime storage
  logs/                 Application logs
  cache/                Cache files
  sessions/             PHP session files (8h expiry)
  demo/                 Demo SQLite database (demo-seed.php output)
templates/              PHP view templates
tests/                  PHPUnit test suites
  Integration/          HTTP-level flow tests (booking, capacity, events)
  Unit/                 Engine and model unit tests
```
## Localization

VoxelBooking is internationalization-ready from its foundation. English ships as the only complete translation; 12 locales are registered with formatting rules (en, nl, de, es, fr, id, it, ja, pt, pl, tr, ar). Arabic (ar) is RTL — the platform sets `dir="rtl"` on `<html>` and uses CSS logical properties for full right-to-left layout.

### Architecture

- **`config/locales.php`** — locale registry with per-locale formatting rules (date, time, number, currency, week start)
- **`app/Engine/Locale.php`** — centralized i18n engine: translation lookup, locale negotiation, formatting functions
- **`app/helpers.php`** — global helpers: `__()`, `__p()`, `__n()`, `__c()`, `__d()`, `__dl()`, `__t()`, `locale_dir()`
- **`lang/en/`** — English translation files (booking, auth, admin, validation, email, privacy)

### Resolution rules

| Context | Locale source | Timezone source |
|---------|--------------|----------------|
| Booking page | Explicit business override → browser `Accept-Language` (if translations exist) → business default → `en` | Storage: business timezone (authoritative). Display: browser timezone (JS-side) |
| Privacy pages | Same as booking page (resolved per business) | Business `timezone` |
| Admin panel | Business locale (in business context) → `APP_LOCALE` (.env) → `en` | Session (browser-detected) |
| Emails | Resolved active locale at send time | Business `timezone` |

### Adding a locale

1. Add the locale entry to `config/locales.php`
2. Create `lang/{locale}/` with translation files
3. No code changes required

### JS integration

The booking page injects `window.__VB_I18N__` (flat key→value translations) and `window.__VB_FMT__` (formatting config). The JS `t(key, replace)` function resolves translations at runtime.

## Privacy & Compliance Posture

VoxelBooking is built with a privacy-by-design architecture. The following describes what the software does today — not a legal guarantee. Operators are responsible for their own compliance obligations.

### Implemented controls

**Self-hosted by default.** All data stays on your server. VoxelBooking makes zero outbound connections unless you configure SMTP for email delivery. No telemetry, no analytics beacons, no update checks, no CDN dependencies. Every font, icon, and script is bundled.

**No hidden tracking.** VoxelBooking sets one session cookie (`vb_session`, strictly necessary for admin login) and one localStorage preference (`vb-theme` for dark/light mode). Zero analytics cookies. Zero marketing cookies. Zero fingerprinting.

**Structured audit logging.** Authentication events (login, logout, failed login), settings changes, and password changes produce structured, append-only audit log entries. PII is redacted centrally: passwords and tokens are never logged, email addresses are stored as SHA-256 prefixes, and email-like detail fields are automatically hashed. The audit log is viewable read-only from the admin UI.

**Consent fields on business schema.** Each business has configurable `consent_text`, `privacy_policy_url`, and `requires_consent` settings.

**Consent evidence recording.** The `BookingService::createBooking()` method captures GDPR consent evidence: the exact `consent_text_shown` the customer agreed to (verbatim at booking time) and the `consent_given_at` timestamp. Changing the business's consent text does not retroactively change recorded consents. Consent records are legal-hold data and are never anonymized or deleted (GDPR Art. 7(1)). `BookingService::recordConsent()` supports post-hoc consent capture for admin-created bookings.

**Customer anonymization.** The `CustomerAnonymizer` engine performs transactional PII removal: customer name → "Deleted", email → SHA-256 hash, phone/notes → NULL. Booking structure and consent evidence are preserved. The engine supports both manual anonymization and automated batch processing via the retention cron.

**Data export.** The `DataExporter` engine generates machine-readable JSON exports of all customer data (personal details, booking history, consent records, email log) for GDPR Art. 20 portability requests.

**Retention cron.** The `RetentionJob` engine orchestrates automated cleanup: per-business customer anonymization based on `data_retention_months`, audit log cleanup, email log cleanup, and rate limit cleanup. Accessible via `GET /cron/run?token={cron_token}` (legacy alias `/cron/retention` still works).

**Privacy endpoint.** `GET /book/{slug}/privacy/{customer-ulid}` displays the customer's personal data, booking history, and consent records (GDPR Art. 15). `POST` with `action=export` returns a JSON download (Art. 20). `POST` with `action=delete` logs a deletion request and notifies the customer that the business will process it (Art. 17). The customer ULID acts as a bearer token (128-bit entropy).

**Operator deletion queue.** The admin deletion queue (`/admin/deletion-queue`) displays all pending customer deletion requests. The operator can confirm (triggers `CustomerAnonymizer` to permanently remove PII) or dismiss (clears the request, retains data). Both decisions are audit-logged with the operator's identity. The queue uses a no-JS two-step confirmation pattern to prevent accidental anonymization.

**Email engine.** The `Mailer` engine wraps PHPMailer and supports three transport modes, configurable via `mail_transport` in Admin → Settings → Email:

- **`smtp`** (default) — delivers via operator-configured SMTP server. Credentials, host, port, and encryption are loaded from the settings table. If SMTP is unconfigured, no outbound connections are made and emails fail immediately.
- **`mailpit`** — delivers to `127.0.0.1:1025` with no authentication and no encryption, overriding any production SMTP settings. Intended for local development with [Mailpit](https://mailpit.axllent.org/) or similar SMTP capture tools.
- **`log`** — records emails to the `email_log` table with status `sent` without making any outbound connection. Suitable for environments without SMTP infrastructure or for testing email flows without actual delivery.

All send attempts are logged to `email_log` regardless of transport. SMTP credentials are never logged — error messages are automatically redacted. The privacy flow calls the Mailer directly: data exports trigger a customer acknowledgment email, deletion requests trigger both a customer confirmation and an operator notification (sent to `notification_email` on the business, falling back to the business's contact email). Email dispatch is synchronous within the HTTP request (10-second SMTP timeout); the privacy action always succeeds regardless of email outcome, but the HTTP response may be delayed by the SMTP round-trip. The `sendTest()` method allows operators to verify their configuration from the admin panel.

### What operators should know

VoxelBooking provides the technical controls for privacy compliance. Operators are responsible for:

- Providing a legally reviewed privacy policy appropriate for their jurisdiction
- Setting appropriate consent text for their businesses
- Responding to data-subject requests within legal timeframes
- Establishing Data Processing Agreements with their hosting and SMTP providers
- Server-level security (TLS certificates, backups, access control)

The software does not claim GDPR compliance, ISO certification, or any legal guarantee. It provides privacy-by-design controls that support compliance — the organizational and legal obligations remain with the operator.

## License

Proprietary. See LICENSE file.
