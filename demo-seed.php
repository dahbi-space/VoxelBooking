<?php

declare(strict_types=1);

/**
 * Demo database seeder.
 *
 * Creates a SQLite database at storage/demo/demo.db with fictional data
 * suitable for demonstrating VoxelBooking features.
 *
 * Usage: php demo-seed.php
 *
 * IMPORTANT: All data is fictional. No real PII.
 * Per .ai/23 §6: demo database must contain only fictional customer data.
 *
 * Schema parity: every CREATE TABLE here must mirror the corresponding
 * base migration in app/Migrations/. When a migration changes, this file
 * must be updated to match. See .ai/2001 §Baseline seed parity.
 */

$basePath = __DIR__;
$demoDir  = $basePath . '/storage/demo';
$demoDb   = $demoDir . '/demo.db';

// Ensure directory exists
if (!is_dir($demoDir)) {
    mkdir($demoDir, 0755, true);
}

// Remove existing database
if (file_exists($demoDb)) {
    unlink($demoDb);
}

$pdo = new PDO('sqlite:' . $demoDb);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ══════════════════════════════════════════════════════════════════════
// Schema — mirrors app/Migrations/* (SQLite dialect)
// ══════════════════════════════════════════════════════════════════════

// 001: settings
$pdo->exec("
CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    `key` TEXT NOT NULL UNIQUE,
    value TEXT,
    updated_at TEXT DEFAULT (datetime('now'))
)
");

// 002: operators
$pdo->exec("
CREATE TABLE operators (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    updated_at TEXT DEFAULT (datetime('now'))
)
");

// 003: tenants — mirrors 003_create_tenants.php
$pdo->exec("
CREATE TABLE tenants (
    id TEXT PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT DEFAULT NULL,
    timezone TEXT NOT NULL DEFAULT 'UTC',
    booking_pattern TEXT NOT NULL DEFAULT 'timeslot',
    locale TEXT NOT NULL DEFAULT 'en',
    locale_override TEXT DEFAULT NULL,
    currency TEXT NOT NULL DEFAULT 'EUR',
    brand_color TEXT NOT NULL DEFAULT '#2563EB',
    brand_color_text TEXT NOT NULL DEFAULT '#FFFFFF',
    logo_path TEXT DEFAULT NULL,
    cover_image_path TEXT DEFAULT NULL,
    booking_page_heading TEXT DEFAULT NULL,
    booking_page_description TEXT DEFAULT NULL,
    confirmation_message TEXT DEFAULT NULL,
    cancellation_policy TEXT DEFAULT NULL,
    allow_cancellation INTEGER NOT NULL DEFAULT 1,
    cancellation_hours_before INTEGER NOT NULL DEFAULT 24,
    allow_rescheduling INTEGER NOT NULL DEFAULT 1,
    rescheduling_hours_before INTEGER NOT NULL DEFAULT 24,
    min_advance_hours INTEGER NOT NULL DEFAULT 1,
    max_advance_days INTEGER NOT NULL DEFAULT 90,
    slot_duration_minutes INTEGER NOT NULL DEFAULT 30,
    buffer_minutes INTEGER NOT NULL DEFAULT 0,
    max_bookings_per_customer_per_day INTEGER NOT NULL DEFAULT 3,
    booking_requires_approval INTEGER NOT NULL DEFAULT 0,
    require_phone INTEGER NOT NULL DEFAULT 0,
    custom_fields TEXT DEFAULT NULL,
    notification_email TEXT DEFAULT NULL,
    notify_on_booking INTEGER NOT NULL DEFAULT 1,
    notify_on_cancellation INTEGER NOT NULL DEFAULT 1,
    send_reminders INTEGER NOT NULL DEFAULT 1,
    reminder_hours_before INTEGER NOT NULL DEFAULT 24,
    requires_consent INTEGER NOT NULL DEFAULT 1,
    privacy_policy_url TEXT DEFAULT NULL,
    consent_text TEXT DEFAULT NULL,
    data_retention_months INTEGER NOT NULL DEFAULT 24,
    allowed_embed_domains TEXT DEFAULT NULL,
    embed_button_position TEXT NOT NULL DEFAULT 'bottom-right',
    embed_button_label TEXT NOT NULL DEFAULT 'Book Now',
    status TEXT NOT NULL DEFAULT 'active',
    meta TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)
");

// 004: audit_log
$pdo->exec("
CREATE TABLE audit_log (
    id TEXT PRIMARY KEY,
    tenant_id TEXT DEFAULT NULL,
    actor_type TEXT NOT NULL,
    actor_id TEXT DEFAULT NULL,
    action TEXT NOT NULL,
    entity_type TEXT DEFAULT NULL,
    entity_id TEXT DEFAULT NULL,
    details TEXT DEFAULT '{}',
    ip_address TEXT DEFAULT NULL,
    request_id TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now'))
)
");

// 005: services — mirrors 005_create_services.php
$pdo->exec("
CREATE TABLE services (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    preparation_text TEXT DEFAULT NULL,
    duration_minutes INTEGER NOT NULL DEFAULT 30,
    duration_options TEXT DEFAULT NULL,
    price REAL DEFAULT NULL,
    price_label TEXT DEFAULT NULL,
    category TEXT DEFAULT NULL,
    color TEXT DEFAULT NULL,
    max_per_day INTEGER DEFAULT NULL,
    requires_staff INTEGER NOT NULL DEFAULT 1,
    staff_assignment_strategy TEXT NOT NULL DEFAULT 'first_available',
    is_virtual INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    meta TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)
");

// 006: staff — mirrors 006_create_staff.php
$pdo->exec("
CREATE TABLE staff (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT DEFAULT NULL,
    title TEXT DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    avatar_path TEXT DEFAULT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    meta TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)
");

// 007: service_staff
$pdo->exec("
CREATE TABLE service_staff (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_id TEXT NOT NULL,
    staff_id TEXT NOT NULL,
    UNIQUE(service_id, staff_id)
)
");

// 008: availability — mirrors 008_create_availability.php
$pdo->exec("
CREATE TABLE availability (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    staff_id TEXT DEFAULT NULL,
    day_of_week INTEGER NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    is_available INTEGER NOT NULL DEFAULT 1
)
");

// 009: blocked_dates — mirrors 009_create_blocked_dates.php
$pdo->exec("
CREATE TABLE blocked_dates (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    staff_id TEXT DEFAULT NULL,
    resource_id TEXT DEFAULT NULL,
    start_date TEXT NOT NULL,
    end_date TEXT NOT NULL,
    reason TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now'))
)
");

// 010: api_keys
$pdo->exec("
CREATE TABLE api_keys (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    key_hash TEXT NOT NULL UNIQUE,
    key_prefix TEXT NOT NULL,
    scopes TEXT NOT NULL DEFAULT '[]',
    role TEXT NOT NULL DEFAULT 'viewer',
    is_active INTEGER NOT NULL DEFAULT 1,
    last_used_at TEXT DEFAULT NULL,
    expires_at TEXT DEFAULT NULL,
    created_by TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)
");

// 011: business_users — mirrors 011_create_business_users.php
$pdo->exec("
CREATE TABLE business_users (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'manager',
    is_active INTEGER NOT NULL DEFAULT 1,
    force_password_change INTEGER NOT NULL DEFAULT 0,
    last_login_at TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)
");

// 018: rate_limits
$pdo->exec("
CREATE TABLE rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    `key` TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    expires_at TEXT NOT NULL,
    UNIQUE(`key`)
)
");

// 019: customers — mirrors 019_create_customers.php
$pdo->exec("
CREATE TABLE customers (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    profile_data TEXT DEFAULT NULL,
    booking_count INTEGER NOT NULL DEFAULT 0,
    last_booking_at TEXT DEFAULT NULL,
    is_anonymized INTEGER NOT NULL DEFAULT 0,
    anonymized_at TEXT DEFAULT NULL,
    deletion_requested_at TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)
");

// 020: bookings — mirrors 020_create_bookings.php
$pdo->exec("
CREATE TABLE bookings (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    booking_pattern TEXT NOT NULL DEFAULT 'timeslot',
    service_id TEXT DEFAULT NULL,
    staff_id TEXT DEFAULT NULL,
    resource_id TEXT DEFAULT NULL,
    event_id TEXT DEFAULT NULL,
    customer_id TEXT NOT NULL,
    start_datetime TEXT NOT NULL,
    end_datetime TEXT NOT NULL,
    party_size INTEGER NOT NULL DEFAULT 1,
    status TEXT NOT NULL DEFAULT 'confirmed',
    rescheduled_to_id TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    custom_field_data TEXT DEFAULT NULL,
    consent_given_at TEXT DEFAULT NULL,
    consent_text_shown TEXT DEFAULT NULL,
    customer_timezone TEXT DEFAULT NULL,
    confirmation_sent_at TEXT DEFAULT NULL,
    reminder_sent_at TEXT DEFAULT NULL,
    cancelled_at TEXT DEFAULT NULL,
    cancellation_reason TEXT DEFAULT NULL,
    source TEXT NOT NULL DEFAULT 'web',
    meta TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)
");

// 021: email_log — mirrors 021_create_email_log.php
$pdo->exec("
CREATE TABLE email_log (
    id TEXT PRIMARY KEY,
    tenant_id TEXT DEFAULT NULL,
    booking_id TEXT DEFAULT NULL,
    type TEXT NOT NULL,
    to_email TEXT NOT NULL,
    subject TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'sent',
    error TEXT DEFAULT NULL,
    sent_at TEXT DEFAULT (datetime('now'))
)
");

// 022: tenant_email_templates — mirrors 022_create_tenant_email_templates.php
$pdo->exec("
CREATE TABLE tenant_email_templates (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    type TEXT NOT NULL,
    subject TEXT DEFAULT NULL,
    heading TEXT DEFAULT NULL,
    body_intro TEXT DEFAULT NULL,
    body_outro TEXT DEFAULT NULL,
    cta_label TEXT DEFAULT NULL,
    is_enabled INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    UNIQUE(tenant_id, type)
)
");

// ══════════════════════════════════════════════════════════════════════
// Seed Data
// ══════════════════════════════════════════════════════════════════════

// Settings
$settings = [
    ['installed_at', date('Y-m-d H:i:s')],
    ['db_version', '22'],
    ['app_name', 'VoxelBooking Demo'],
    ['timezone', 'Europe/Amsterdam'],
    ['locale', 'en'],
];

$stmt = $pdo->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?)");
foreach ($settings as [$k, $v]) {
    $stmt->execute([$k, $v]);
}

// Operator (password: demo)
$operatorId = '01JDEMO0001OPERATOR001';
$pdo->prepare("INSERT INTO operators (id, name, email, password_hash) VALUES (?, ?, ?, ?)")
    ->execute([$operatorId, 'Demo Admin', 'demo@voxelbooking.com', password_hash('demo', PASSWORD_BCRYPT)]);

// Tenant
$tenantId = '01JDEMO0001TENANT00001';
$pdo->prepare("
    INSERT INTO tenants (id, name, slug, email, status, timezone, locale, currency, brand_color, booking_pattern,
                         require_phone, requires_consent, consent_text, privacy_policy_url,
                         slot_duration_minutes, buffer_minutes, min_advance_hours, max_advance_days)
    VALUES (?, ?, ?, ?, 'active', 'Europe/Amsterdam', 'en', 'EUR', '#2563EB', 'timeslot',
            0, 1, 'I agree to the processing of my personal data for this booking.', '#',
            30, 0, 1, 90)
")->execute([$tenantId, 'Demo Studio', 'demo', 'hello@demostudio.example']);

// Services
$services = [
    ['01JDEMO0001SERVICE0001', 'Consultation', 'An introductory consultation to discuss your needs and goals.', 'Please bring any relevant documents or previous records.', 60, null, 75.00, '#2563EB'],
    ['01JDEMO0001SERVICE0002', 'Standard Session', 'A standard 45-minute session with your chosen professional.', null, 45, null, 55.00, '#7C3AED'],
    ['01JDEMO0001SERVICE0003', 'Extended Session', 'A deep-dive session for complex topics or follow-up work.', null, 90, '[60, 90, 120]', 120.00, '#059669'],
];

$stmt = $pdo->prepare("INSERT INTO services (id, tenant_id, name, description, preparation_text, duration_minutes, duration_options, price, color, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
foreach ($services as $i => [$id, $name, $desc, $prep, $dur, $durOpts, $price, $color]) {
    $stmt->execute([$id, $tenantId, $name, $desc, $prep, $dur, $durOpts, $price, $color, $i]);
}

// Staff
$staffMembers = [
    ['01JDEMO0001STAFF000001', 'Alice Example', 'Senior Consultant'],
    ['01JDEMO0001STAFF000002', 'Bob Demoson', 'Specialist'],
    ['01JDEMO0001STAFF000003', 'Charlie Fixture', null],
];

$stmt = $pdo->prepare("INSERT INTO staff (id, tenant_id, name, email, title, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
foreach ($staffMembers as $i => [$id, $name, $title]) {
    $email = strtolower(str_replace(' ', '.', $name)) . '@example.com';
    $stmt->execute([$id, $tenantId, $name, $email, $title, $i]);
}

// Service-staff links (all staff can do all services)
$stmt = $pdo->prepare("INSERT INTO service_staff (service_id, staff_id) VALUES (?, ?)");
foreach ($services as [$svcId]) {
    foreach ($staffMembers as [$staffId]) {
        $stmt->execute([$svcId, $staffId]);
    }
}

// Availability (Mon-Fri 9:00-17:00 for all staff — is_available, not is_active)
$stmt = $pdo->prepare("INSERT INTO availability (id, tenant_id, staff_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?, ?, 1)");
$availId = 1;
foreach ($staffMembers as [$staffId]) {
    for ($day = 1; $day <= 5; $day++) {
        $stmt->execute([
            sprintf('01JDEMO0001AVAIL%06d', $availId++),
            $tenantId,
            $staffId,
            $day,
            '09:00',
            '17:00'
        ]);
    }
}

// Customers (fictional — includes booking_count and last_booking_at)
$customers = [
    ['01JDEMO0001CUST0000001', 'Emma Johnson', 'emma.johnson@example.com', '+31 6 0000 0001'],
    ['01JDEMO0001CUST0000002', 'James Smith', 'james.smith@example.com', '+31 6 0000 0002'],
    ['01JDEMO0001CUST0000003', 'Sophie Brown', 'sophie.brown@example.com', null],
];

$stmt = $pdo->prepare("INSERT INTO customers (id, tenant_id, name, email, phone, booking_count) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($customers as $i => [$id, $name, $email, $phone]) {
    $bookingCount = ($i === 0) ? 2 : (($i === 1) ? 2 : 1);
    $stmt->execute([$id, $tenantId, $name, $email, $phone, $bookingCount]);
}

// Bookings (includes booking_pattern, customer_timezone)
$today = new DateTime();
$bookings = [
    ['01JDEMO0001BOOK0000001', $customers[0][0], $services[0][0], $staffMembers[0][0], '+1 day', '10:00', '11:00', 'confirmed'],
    ['01JDEMO0001BOOK0000002', $customers[1][0], $services[1][0], $staffMembers[1][0], '+2 days', '14:00', '14:45', 'confirmed'],
    ['01JDEMO0001BOOK0000003', $customers[2][0], $services[2][0], $staffMembers[2][0], '+3 days', '09:00', '10:30', 'confirmed'],
    ['01JDEMO0001BOOK0000004', $customers[0][0], $services[0][0], $staffMembers[0][0], '-2 days', '11:00', '12:00', 'completed'],
    ['01JDEMO0001BOOK0000005', $customers[1][0], $services[1][0], $staffMembers[1][0], '-5 days', '15:00', '15:45', 'completed'],
];

$stmt = $pdo->prepare("
    INSERT INTO bookings (id, tenant_id, booking_pattern, customer_id, service_id, staff_id,
                          start_datetime, end_datetime, status, source,
                          customer_timezone, consent_given_at, consent_text_shown)
    VALUES (?, ?, 'timeslot', ?, ?, ?, ?, ?, ?, 'web',
            'Europe/Amsterdam', datetime('now'), 'I agree to the processing of my personal data for this booking.')
");
foreach ($bookings as [$id, $custId, $svcId, $staffId, $dateOffset, $start, $end, $status]) {
    $date = (clone $today)->modify($dateOffset)->format('Y-m-d');
    $stmt->execute([$id, $tenantId, $custId, $svcId, $staffId, "{$date} {$start}:00", "{$date} {$end}:00", $status]);
}

// Audit log sample entries
$stmt = $pdo->prepare("INSERT INTO audit_log (id, tenant_id, actor_type, actor_id, action, entity_type, entity_id, details, ip_address, request_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$logEntries = [
    ['01JDEMO0001AUDIT000001', null, 'operator', $operatorId, 'auth.login', 'operator', $operatorId, '{}', '127.0.0.1', 'demo-req-001'],
    ['01JDEMO0001AUDIT000002', $tenantId, 'operator', $operatorId, 'settings.updated', 'settings', null, '{"key":"site_name","old":"VoxelBooking","new":"VoxelBooking Demo"}', '127.0.0.1', 'demo-req-002'],
    ['01JDEMO0001AUDIT000003', $tenantId, 'system', null, 'booking.created', 'booking', '01JDEMO0001BOOK0000001', '{"source":"web"}', '127.0.0.1', 'demo-req-003'],
];
foreach ($logEntries as $log) {
    $stmt->execute($log);
}

// Tenant email templates (one customized, rest use system defaults)
$stmt = $pdo->prepare("
    INSERT INTO tenant_email_templates (id, tenant_id, type, subject, heading, body_intro, body_outro, cta_label, is_enabled)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    '01JDEMO0001EMAILT000001', $tenantId, 'confirmation',
    'Your appointment at {business_name} is confirmed',
    'See you soon!',
    'Hi {customer_name}, your booking is confirmed. Here are the details:',
    'We look forward to seeing you. If you need to make changes, use the links below.',
    'Add to Calendar',
    1,
]);

echo "✓ Demo database seeded at: {$demoDb}\n";
echo "  Operator: demo@voxelbooking.com / demo\n";
echo "  Tenant: Demo Studio (slug: demo)\n";
echo "  Services: " . count($services) . "\n";
echo "  Staff: " . count($staffMembers) . "\n";
echo "  Customers: " . count($customers) . "\n";
echo "  Bookings: " . count($bookings) . "\n";
echo "  Audit entries: " . count($logEntries) . "\n";
