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

## License

Proprietary. See LICENSE file.
