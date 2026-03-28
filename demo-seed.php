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

// ── Schema ──

$pdo->exec("
CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    `key` TEXT NOT NULL UNIQUE,
    value TEXT,
    updated_at TEXT DEFAULT (datetime('now'))
)
");

$pdo->exec("
CREATE TABLE operators (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    updated_at TEXT DEFAULT (datetime('now'))
)
");

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

$pdo->exec("
CREATE TABLE tenants (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'active',
    timezone TEXT NOT NULL DEFAULT 'Europe/Amsterdam',
    locale TEXT NOT NULL DEFAULT 'en',
    locale_override TEXT DEFAULT NULL,
    currency TEXT NOT NULL DEFAULT 'EUR',
    brand_color TEXT DEFAULT '#2563EB',
    booking_pattern TEXT NOT NULL DEFAULT 'service_first',
    require_phone INTEGER NOT NULL DEFAULT 0,
    requires_consent INTEGER NOT NULL DEFAULT 1,
    consent_text TEXT DEFAULT NULL,
    privacy_policy_url TEXT DEFAULT NULL,
    custom_fields TEXT DEFAULT '[]',
    data_retention_months INTEGER NOT NULL DEFAULT 24,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)
");

$pdo->exec("
CREATE TABLE services (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    duration_minutes INTEGER NOT NULL DEFAULT 60,
    price REAL DEFAULT NULL,
    price_label TEXT DEFAULT NULL,
    color TEXT DEFAULT '#2563EB',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)
");

$pdo->exec("
CREATE TABLE staff (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT,
    avatar_url TEXT DEFAULT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)
");

$pdo->exec("
CREATE TABLE service_staff (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_id TEXT NOT NULL,
    staff_id TEXT NOT NULL,
    UNIQUE(service_id, staff_id)
)
");

$pdo->exec("
CREATE TABLE availability (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    staff_id TEXT DEFAULT NULL,
    day_of_week INTEGER NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1
)
");

$pdo->exec("
CREATE TABLE blocked_dates (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    staff_id TEXT DEFAULT NULL,
    blocked_date TEXT NOT NULL,
    reason TEXT DEFAULT NULL
)
");

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

$pdo->exec("
CREATE TABLE customers (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    deletion_requested_at TEXT DEFAULT NULL,
    anonymized_at TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)
");

$pdo->exec("
CREATE TABLE bookings (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    customer_id TEXT NOT NULL,
    service_id TEXT NOT NULL,
    staff_id TEXT DEFAULT NULL,
    start_datetime TEXT NOT NULL,
    end_datetime TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'confirmed',
    source TEXT NOT NULL DEFAULT 'web',
    notes TEXT DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    custom_field_data TEXT DEFAULT NULL,
    consent_given_at TEXT DEFAULT NULL,
    consent_text_shown TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)
");

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

$pdo->exec("
CREATE TABLE email_log (
    id TEXT PRIMARY KEY,
    tenant_id TEXT DEFAULT NULL,
    to_email TEXT NOT NULL,
    subject TEXT NOT NULL,
    template TEXT DEFAULT NULL,
    status TEXT NOT NULL DEFAULT 'sent',
    error TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now'))
)
");

$pdo->exec("
CREATE TABLE rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    `key` TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    expires_at TEXT NOT NULL,
    UNIQUE(`key`)
)
");

// ── Seed Data ──

// Settings
$settings = [
    ['installed_at', date('Y-m-d H:i:s')],
    ['db_version', '21'],
    ['site_name', 'VoxelBooking Demo'],
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
    INSERT INTO tenants (id, name, slug, status, timezone, locale, currency, brand_color, booking_pattern, require_phone, requires_consent, consent_text, privacy_policy_url)
    VALUES (?, ?, ?, 'active', 'Europe/Amsterdam', 'en', 'EUR', '#2563EB', 'service_first', 0, 1, 'I agree to the processing of my personal data for this booking.', '#')
")->execute([$tenantId, 'Demo Studio', 'demo']);

// Services
$services = [
    ['01JDEMO0001SERVICE0001', 'Consultation', 'An introductory consultation to discuss your needs and goals.', 60, 75.00, '#2563EB'],
    ['01JDEMO0001SERVICE0002', 'Standard Session', 'A standard 45-minute session with your chosen professional.', 45, 55.00, '#7C3AED'],
    ['01JDEMO0001SERVICE0003', 'Extended Session', 'A deep-dive session for complex topics or follow-up work.', 90, 120.00, '#059669'],
];

$stmt = $pdo->prepare("INSERT INTO services (id, tenant_id, name, description, duration_minutes, price, color, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
foreach ($services as $i => [$id, $name, $desc, $dur, $price, $color]) {
    $stmt->execute([$id, $tenantId, $name, $desc, $dur, $price, $color, $i]);
}

// Staff
$staffMembers = [
    ['01JDEMO0001STAFF000001', 'Alice Example'],
    ['01JDEMO0001STAFF000002', 'Bob Demoson'],
    ['01JDEMO0001STAFF000003', 'Charlie Fixture'],
];

$stmt = $pdo->prepare("INSERT INTO staff (id, tenant_id, name, email, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");
foreach ($staffMembers as $i => [$id, $name]) {
    $email = strtolower(str_replace(' ', '.', $name)) . '@example.com';
    $stmt->execute([$id, $tenantId, $name, $email, $i]);
}

// Service-staff links (all staff can do all services)
$stmt = $pdo->prepare("INSERT INTO service_staff (service_id, staff_id) VALUES (?, ?)");
foreach ($services as [$svcId]) {
    foreach ($staffMembers as [$staffId]) {
        $stmt->execute([$svcId, $staffId]);
    }
}

// Availability (Mon-Fri 9:00-17:00 for all staff)
$stmt = $pdo->prepare("INSERT INTO availability (id, tenant_id, staff_id, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
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

// Customers (fictional)
$customers = [
    ['01JDEMO0001CUST0000001', 'Emma Johnson', 'emma.johnson@example.com', '+31 6 0000 0001'],
    ['01JDEMO0001CUST0000002', 'James Smith', 'james.smith@example.com', '+31 6 0000 0002'],
    ['01JDEMO0001CUST0000003', 'Sophie Brown', 'sophie.brown@example.com', null],
];

$stmt = $pdo->prepare("INSERT INTO customers (id, tenant_id, name, email, phone) VALUES (?, ?, ?, ?, ?)");
foreach ($customers as [$id, $name, $email, $phone]) {
    $stmt->execute([$id, $tenantId, $name, $email, $phone]);
}

// Bookings (a few demo bookings — future dates relative to today)
$today = new DateTime();
$bookings = [
    ['01JDEMO0001BOOK0000001', $customers[0][0], $services[0][0], $staffMembers[0][0], '+1 day', '10:00', '11:00', 'confirmed'],
    ['01JDEMO0001BOOK0000002', $customers[1][0], $services[1][0], $staffMembers[1][0], '+2 days', '14:00', '14:45', 'confirmed'],
    ['01JDEMO0001BOOK0000003', $customers[2][0], $services[2][0], $staffMembers[2][0], '+3 days', '09:00', '10:30', 'confirmed'],
    ['01JDEMO0001BOOK0000004', $customers[0][0], $services[0][0], $staffMembers[0][0], '-2 days', '11:00', '12:00', 'completed'],
    ['01JDEMO0001BOOK0000005', $customers[1][0], $services[1][0], $staffMembers[1][0], '-5 days', '15:00', '15:45', 'completed'],
];

$stmt = $pdo->prepare("
    INSERT INTO bookings (id, tenant_id, customer_id, service_id, staff_id, start_datetime, end_datetime, status, source, consent_given_at, consent_text_shown)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'web', datetime('now'), 'I agree to the processing of my personal data for this booking.')
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

echo "✓ Demo database seeded at: {$demoDb}\n";
echo "  Operator: demo@voxelbooking.com / demo\n";
echo "  Tenant: Demo Studio (slug: demo)\n";
echo "  Services: " . count($services) . "\n";
echo "  Staff: " . count($staffMembers) . "\n";
echo "  Customers: " . count($customers) . "\n";
echo "  Bookings: " . count($bookings) . "\n";
echo "  Audit entries: " . count($logEntries) . "\n";
