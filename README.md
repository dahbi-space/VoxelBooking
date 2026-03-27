# VoxelBooking

Self-hosted multi-tenant booking system. Four booking patterns (time slots, resources, capacity, events) in one codebase.

## Requirements

- PHP 8.3+
- MySQL 8.0+
- Node.js 20+ (development only)
- Composer (development only)

### Required PHP Extensions

`pdo`, `pdo_mysql`, `curl`, `json`, `mbstring`, `fileinfo`, `openssl`, `gd`

## Installation

1. Upload the ZIP to your server and extract it
2. Point your web server's document root to the `public/` directory
3. Navigate to your domain — the installation wizard starts automatically
4. Follow the 5-step wizard: system check → database → email → operator account → first tenant

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

The app serves via Laravel Herd (or any PHP server) at the domain matching the directory name. The document root must be the `public/` directory.

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

## Stack

- **Backend:** PHP 8.3+ (custom micro-framework, no Laravel/Symfony)
- **Database:** MySQL 8+ (PDO, prepared statements, no ORM)
- **Admin frontend:** Alpine.js 3, Tailwind CSS 4, Lucide icons
- **Booking page frontend:** Vanilla JS, Tailwind CSS 4
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
  Controllers/          Route handlers
  Engine/               Core framework classes
  Middleware/           Request middleware
  Migrations/           Sequential SQL migrations
  Models/               Data models (no ORM)
config/                 Configuration files
lang/                   Translation files (en, nl, de, fr, es)
public/                 Web root (document root)
  assets/               Compiled CSS/JS (built by Vite)
  uploads/              User-uploaded files (logos, covers)
resources/              Source frontend files
  css/                  Source CSS (Tailwind)
  js/                   Source JS (admin + booking)
storage/                Runtime storage
  logs/                 Application logs
  cache/                Cache files
templates/              PHP view templates
tests/                  PHPUnit test suites
```

## Privacy & Compliance Posture

VoxelBooking is built with a privacy-by-design architecture. The following describes what the software does today — not a legal guarantee. Operators are responsible for their own compliance obligations.

### Implemented controls

**Self-hosted by default.** All data stays on your server. VoxelBooking makes zero outbound connections unless you configure SMTP for email delivery. No telemetry, no analytics beacons, no update checks, no CDN dependencies. Every font, icon, and script is bundled.

**No hidden tracking.** VoxelBooking sets one session cookie (`vb_session`, strictly necessary for admin login) and one localStorage preference (`vb-theme` for dark/light mode). Zero analytics cookies. Zero marketing cookies. Zero fingerprinting.

**Structured audit logging.** Authentication events (login, logout, failed login), settings changes, and password changes produce structured, append-only audit log entries. PII is redacted centrally: passwords and tokens are never logged, email addresses are stored as SHA-256 prefixes, and email-like detail fields are automatically hashed. The audit log is viewable read-only from the admin UI.

**Consent fields on tenant schema.** Each tenant has configurable `consent_text`, `privacy_policy_url`, and `requires_consent` settings.

**Consent evidence recording.** The `BookingService::createBooking()` method captures GDPR consent evidence: the exact `consent_text_shown` the customer agreed to (verbatim at booking time) and the `consent_given_at` timestamp. Changing the tenant's consent text does not retroactively change recorded consents. Consent records are legal-hold data and are never anonymized or deleted (GDPR Art. 7(1)). `BookingService::recordConsent()` supports post-hoc consent capture for admin-created bookings.

**Customer anonymization.** The `CustomerAnonymizer` engine performs transactional PII removal: customer name → "Deleted", email → SHA-256 hash, phone/notes → NULL. Booking structure and consent evidence are preserved. The engine supports both manual anonymization and automated batch processing via the retention cron.

**Data export.** The `DataExporter` engine generates machine-readable JSON exports of all customer data (personal details, booking history, consent records, email log) for GDPR Art. 20 portability requests.

**Retention cron.** The `RetentionJob` engine orchestrates automated cleanup: per-tenant customer anonymization based on `data_retention_months`, audit log cleanup, email log cleanup, and rate limit cleanup. Accessible via `GET /cron/retention?token={cron_token}`.

**Privacy endpoint.** `GET /book/{slug}/privacy/{customer-ulid}` displays the customer's personal data, booking history, and consent records (GDPR Art. 15). `POST` with `action=export` returns a JSON download (Art. 20). `POST` with `action=delete` logs a deletion request and notifies the customer that the business will process it (Art. 17). The customer ULID acts as a bearer token (128-bit entropy).

**Operator deletion queue.** The admin deletion queue (`/admin/deletion-queue`) displays all pending customer deletion requests. The operator can confirm (triggers `CustomerAnonymizer` to permanently remove PII) or dismiss (clears the request, retains data). Both decisions are audit-logged with the operator's identity. The queue uses a no-JS two-step confirmation pattern to prevent accidental anonymization.

**Email engine.** The `Mailer` engine wraps PHPMailer and supports three transport modes, configurable via `mail_transport` in Admin → Settings → Email:

- **`smtp`** (default) — delivers via operator-configured SMTP server. Credentials, host, port, and encryption are loaded from the settings table. If SMTP is unconfigured, no outbound connections are made and emails fail immediately.
- **`mailpit`** — delivers to `127.0.0.1:1025` with no authentication and no encryption, overriding any production SMTP settings. Intended for local development with [Mailpit](https://mailpit.axllent.org/) or similar SMTP capture tools.
- **`log`** — records emails to the `email_log` table with status `sent` without making any outbound connection. Suitable for environments without SMTP infrastructure or for testing email flows without actual delivery.

All send attempts are logged to `email_log` regardless of transport. SMTP credentials are never logged — error messages are automatically redacted. The privacy flow calls the Mailer directly: data exports trigger a customer acknowledgment email, deletion requests trigger both a customer confirmation and an operator notification (sent to `notification_email` on the tenant, falling back to the tenant's contact email). Email dispatch is synchronous within the HTTP request (10-second SMTP timeout); the privacy action always succeeds regardless of email outcome, but the HTTP response may be delayed by the SMTP round-trip. The `sendTest()` method allows operators to verify their configuration from the admin panel.

### What operators should know

VoxelBooking provides the technical controls for privacy compliance. Operators are responsible for:

- Providing a legally reviewed privacy policy appropriate for their jurisdiction
- Setting appropriate consent text for their tenants
- Responding to data-subject requests within legal timeframes
- Establishing Data Processing Agreements with their hosting and SMTP providers
- Server-level security (TLS certificates, backups, access control)

The software does not claim GDPR compliance, ISO certification, or any legal guarantee. It provides privacy-by-design controls that support compliance — the organizational and legal obligations remain with the operator.

## License

Proprietary. See LICENSE file.
