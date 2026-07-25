# Perka Architecture Design

Complete modular architecture for Perka SaaS platform, preserving VoxelBooking core with additive-only changes.

## Table of Contents
1. [Architectural Principles](#architectural-principles)
2. [Module 1: Loyalty Engine](#module-1-loyalty-engine)
3. [Module 2: Rewards Engine](#module-2-rewards-engine)
4. [Module 3: Memberships](#module-3-memberships)
5. [Module 4: Prepaid Passes](#module-4-prepaid-passes)
6. [Module 5: Gift Cards](#module-5-gift-cards)
7. [Module 6: Wallet](#module-6-wallet)
8. [Module 7: Paid Plans (SaaS Billing)](#module-7-paid-plans-saas-billing)
9. [Module 8: Marketplace](#module-8-marketplace)
10. [Module 9: WhatsApp Integration](#module-9-whatsapp-integration)
11. [Module 10: AI Assistant](#module-10-ai-assistant)
12. [Module 11: Reviews](#module-11-reviews)
13. [Module 12: Public Business Profiles](#module-12-public-business-profiles)
14. [Module 13: Multi-location](#module-13-multi-location)
15. [Module 14: Analytics](#module-14-analytics)
16. [Event System](#event-system)
17. [Cross-Module Dependencies](#cross-module-dependencies)
18. [Database Migration Strategy](#database-migration-strategy)
19. [Feature Flags & Tenants Settings](#feature-flags--tenant-settings)

---

## Architectural Principles

### 1. VoxelBooking Core is Immutable
- **Never modify** existing VoxelBooking tables (tenants, bookings, customers, services, staff, resources, events, availability, etc.)
- **Never add columns** to existing tables
- **Never change** existing APIs or controllers
- Use only `meta` JSON fields for extensions

### 2. Additive-Only Architecture
- New features = new tables + new services + new controllers
- Existing code paths remain untouched
- Feature flags enable/disable per tenant
- Modular: can enable loyalty without memberships, etc.

### 3. Event-Driven Loose Coupling
- Booking creation triggers `booking.created` event
- Loyalty service listens and earns points
- Rewards service listens and applies discounts
- No direct coupling between services

### 4. Upstream Compatibility
- VoxelBooking updates pull cleanly (no conflicts)
- Perka features live in `/app/Perka/*` namespace
- Perka migrations in `app/Perka/Migrations/*` (separate from core)
- Core remains at root, Perka at `/perka/` in URLs

### 5. Modularity & Isolation
- Each module has its own service, controller, models
- Modules can be disabled at feature flag level
- Admin UI only shows enabled features
- Public API only exposes enabled features

---

## Module 1: Loyalty Engine

### Purpose
Track customer loyalty points, tiers, and engagement.

### Database Changes

**New Tables**:

```sql
-- Loyalty account per customer per tenant
CREATE TABLE loyalty_accounts (
    id CHAR(26) NOT NULL PRIMARY KEY,
    customer_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    points_balance INT NOT NULL DEFAULT 0,
    lifetime_points INT NOT NULL DEFAULT 0,
    tier VARCHAR(50) NOT NULL DEFAULT 'bronze',
    tier_started_at DATETIME,
    tier_reached_at DATETIME,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    meta JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY loyalty_accounts_customer_tenant (customer_id, tenant_id),
    KEY loyalty_accounts_tenant_tier_idx (tenant_id, tier),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Point transaction ledger (immutable)
CREATE TABLE loyalty_transactions (
    id CHAR(26) NOT NULL PRIMARY KEY,
    loyalty_account_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    booking_id CHAR(26),
    transaction_type VARCHAR(50) NOT NULL,  -- earn|redeem|bonus|admin_adjustment|expiry
    points_delta INT NOT NULL,  -- +100 or -50
    reason VARCHAR(255),
    metadata JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY loyalty_transactions_account_idx (loyalty_account_id),
    KEY loyalty_transactions_booking_idx (booking_id),
    KEY loyalty_transactions_type_idx (transaction_type),
    FOREIGN KEY (loyalty_account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);

-- Tier configuration per tenant
CREATE TABLE loyalty_tiers (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    tier_name VARCHAR(50) NOT NULL,
    tier_level INT NOT NULL,  -- 1=bronze, 2=silver, 3=gold, 4=platinum
    min_lifetime_points INT NOT NULL,
    max_lifetime_points INT,  -- NULL = no max
    points_multiplier DECIMAL(3, 2) NOT NULL DEFAULT 1.0,  -- 1.5x for gold
    discount_percent DECIMAL(5, 2),  -- Automatic % discount
    benefits JSON,  -- [{name, description, value}, ...]
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY loyalty_tiers_tenant_name (tenant_id, tier_name),
    KEY loyalty_tiers_tenant_level_idx (tenant_id, tier_level),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Loyalty configuration per tenant
CREATE TABLE loyalty_settings (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    points_per_dollar DECIMAL(5, 2) NOT NULL DEFAULT 1.0,
    points_per_booking INT NOT NULL DEFAULT 10,
    points_per_minute INT DEFAULT NULL,  -- For time-based earning
    max_points_per_booking INT,
    expiry_months INT,  -- Points expire after N months (NULL = no expiry)
    enable_tier_benefits TINYINT(1) NOT NULL DEFAULT 1,
    enable_referral_bonus TINYINT(1) NOT NULL DEFAULT 0,
    referral_bonus_points INT DEFAULT 50,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY loyalty_settings_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### New Services

**Engine/Perka/LoyaltyService.php**:
- `createAccount(customer_id, tenant_id)`: Initialize loyalty account
- `earnPoints(booking_id, customer_id, amount, reason)`: Award points
- `redeemPoints(loyalty_account_id, amount, reason)`: Debit points
- `calculateTier(lifetime_points): string`: Determine tier from points
- `applyTierMultiplier(points, tier): int`: Apply multiplier
- `checkExpiringPoints(tenant_id)`: Find points nearing expiry
- `expirePoints(loyalty_account_id, amount)`: Mark as expired
- `getPointsMultiplier(customer_id, tenant_id): float`: Get current multiplier
- `getAccountBalance(customer_id, tenant_id): array`: {points_balance, tier, multiplier, ...}

### New Controllers

**Admin/Perka/LoyaltyController.php**:
- `settingsForm()`: `GET /admin/tenants/{tenant_id}/perka/loyalty/settings`
- `saveSettings()`: `POST /admin/tenants/{tenant_id}/perka/loyalty/settings`
- `tiersIndex()`: `GET /admin/tenants/{tenant_id}/perka/loyalty/tiers`
- `tiersCreate()`: `GET /admin/tenants/{tenant_id}/perka/loyalty/tiers/create`
- `tiersStore()`: `POST /admin/tenants/{tenant_id}/perka/loyalty/tiers`
- `tiersEdit()`: `GET /admin/tenants/{tenant_id}/perka/loyalty/tiers/{id}/edit`
- `tiersUpdate()`: `POST /admin/tenants/{tenant_id}/perka/loyalty/tiers/{id}`
- `customersView()`: `GET /admin/tenants/{tenant_id}/perka/loyalty/customers` (paginated list with tier/points)
- `customerDetail()`: `GET /admin/tenants/{tenant_id}/perka/loyalty/customers/{customer_id}`

### APIs

**Public Booking API**:
```
GET /api/{slug}/perka/loyalty/account
→ { points_balance, tier, tier_benefits, multiplier, points_expiry_date }

POST /api/{slug}/perka/loyalty/account
→ Create account if not exists (called on first booking)
```

### Events Fired

```php
// Fired from BookingService::createBooking()
Event::dispatch('booking.created', {
    booking_id, customer_id, tenant_id, 
    service_id, amount, booking_pattern
});

// Loyalty service listens and calls earnPoints()
Event::listen('booking.created', LoyaltyService::class);

// Fired when tier changes
Event::dispatch('loyalty.tier_changed', {
    customer_id, tenant_id, old_tier, new_tier
});
```

### Webhooks

```json
POST {tenant_webhook_url}
{
  "event": "loyalty.points_earned",
  "customer_id": "...",
  "points": 50,
  "new_balance": 250,
  "tier": "silver",
  "timestamp": "2024-12-15T10:00:00Z"
}
```

### UI Changes

**Booking Page**:
- Add loyalty badge showing current tier, points, multiplier
- Show "You'll earn 50 points on this booking" before submit
- Display tier benefits info

**Admin Dashboard**:
- New "Loyalty" section in sidebar (if enabled)
- Tier configuration panel
- Customer loyalty export (CSV)

### Dependencies
- None (self-contained)
- Listens to: booking.created event
- Used by: Rewards Engine, Memberships

---

## Module 2: Rewards Engine

### Purpose
Define and manage reward catalog, redemptions, and point-based discounts.

### Database Changes

**New Tables**:

```sql
-- Reward catalog per tenant
CREATE TABLE loyalty_rewards (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    reward_type VARCHAR(50) NOT NULL,  -- discount_percent|discount_amount|free_service|credit|other
    points_cost INT NOT NULL,  -- 500 points = reward
    discount_percent DECIMAL(5, 2),  -- OR percentage discount
    discount_amount DECIMAL(10, 2),  -- OR fixed currency discount
    service_id CHAR(26),  -- Reward applies to this service (NULL = all)
    value_credits INT,  -- For credit/credit rewards
    max_redemptions INT,  -- NULL = unlimited
    redeemed_count INT NOT NULL DEFAULT 0,
    validity_days INT,  -- How long redemption code valid
    tier_min VARCHAR(50),  -- NULL = all tiers, 'silver' = silver+ only
    tier_max VARCHAR(50),  -- NULL = unlimited
    display_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    expires_at DATETIME,
    meta JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY loyalty_rewards_tenant_active_idx (tenant_id, is_active),
    KEY loyalty_rewards_tier_idx (tier_min),
    KEY loyalty_rewards_type_idx (reward_type),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);

-- Redemption records (immutable)
CREATE TABLE loyalty_redemptions (
    id CHAR(26) NOT NULL PRIMARY KEY,
    reward_id CHAR(26) NOT NULL,
    loyalty_account_id CHAR(26) NOT NULL,
    customer_id CHAR(26) NOT NULL,
    booking_id CHAR(26),
    tenant_id CHAR(26) NOT NULL,
    redemption_code VARCHAR(50) UNIQUE,
    points_spent INT NOT NULL,
    discount_applied DECIMAL(10, 2),
    status VARCHAR(50) NOT NULL DEFAULT 'pending',  -- pending|applied|expired|cancelled
    applied_at DATETIME,
    expires_at DATETIME,
    meta JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY loyalty_redemptions_account_idx (loyalty_account_id),
    KEY loyalty_redemptions_booking_idx (booking_id),
    KEY loyalty_redemptions_code_idx (redemption_code),
    KEY loyalty_redemptions_status_idx (status),
    FOREIGN KEY (reward_id) REFERENCES loyalty_rewards(id) ON DELETE CASCADE,
    FOREIGN KEY (loyalty_account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);
```

### New Services

**Engine/Perka/RewardsService.php**:
- `createReward(tenant_id, data)`: Create reward in catalog
- `listAvailableRewards(loyalty_account_id)`: Rewards customer can afford (tier-filtered)
- `redeemReward(reward_id, loyalty_account_id, booking_id)`: Create redemption
- `applyRedemption(booking_id, redemption_id): discount`: Calculate discount to apply
- `expireRedemption(redemption_id)`: Mark as expired
- `getRedemptionCode(loyalty_account_id, reward_id)`: Generate unique code
- `validateRedemptionCode(code): {valid, reward, discount}`

### New Controllers

**Admin/Perka/RewardsController.php**:
- `index()`: `GET /admin/tenants/{tenant_id}/perka/rewards` (list)
- `create()`: `GET /admin/tenants/{tenant_id}/perka/rewards/create`
- `store()`: `POST /admin/tenants/{tenant_id}/perka/rewards`
- `edit()`: `GET /admin/tenants/{tenant_id}/perka/rewards/{id}/edit`
- `update()`: `POST /admin/tenants/{tenant_id}/perka/rewards/{id}`
- `deactivate()`: `POST /admin/tenants/{tenant_id}/perka/rewards/{id}/deactivate`
- `redemptionHistory()`: `GET /admin/tenants/{tenant_id}/perka/rewards/history`

### APIs

**Public Booking API**:
```
GET /api/{slug}/perka/rewards
→ [{ id, name, points_cost, discount_percent, tier_required, ... }]

POST /api/{slug}/perka/bookings/{id}/redeem
{
  "reward_id": "...",
  "loyalty_account_id": "..."
}
→ { redemption_id, discount_applied, points_spent, new_balance }
```

### Events Fired

```php
Event::dispatch('reward.created', { reward_id, tenant_id });
Event::dispatch('reward.redeemed', { 
    reward_id, customer_id, points_spent, discount_applied 
});
Event::dispatch('reward.expired', { redemption_id });
```

### Webhooks

```json
{
  "event": "reward.redeemed",
  "reward_id": "...",
  "customer_id": "...",
  "points_spent": 100,
  "discount_value": "15.00",
  "timestamp": "2024-12-15T10:00:00Z"
}
```

### UI Changes

**Booking Page**:
- Show available rewards (scrollable list)
- Display point cost and discount value
- "Redeem reward" button → generates code or applies immediately
- Show redemption confirmation

**Admin**:
- Rewards catalog with filter by tier, service, type
- Redemption history with customer name, discount applied

### Dependencies
- Requires: Loyalty Engine
- Used by: Checkout flow (apply discount), Bookings

---

## Module 3: Memberships

### Purpose
Recurring subscriptions for unlimited or limited bookings.

### Database Changes

**New Tables**:

```sql
-- Membership plan per tenant
CREATE TABLE perka_memberships (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,  -- "Unlimited Monthly"
    description TEXT,
    interval VARCHAR(50) NOT NULL,  -- monthly|quarterly|annual
    price DECIMAL(10, 2) NOT NULL,
    booking_limit INT,  -- NULL = unlimited
    booking_limit_period VARCHAR(50),  -- monthly|lifetime
    services_included JSON,  -- [service_id, ...] or NULL = all
    resources_included JSON,  -- [resource_id, ...] or NULL = all
    cancellation_notice_days INT DEFAULT 7,
    trial_days INT DEFAULT 0,
    setup_fee DECIMAL(10, 2),
    benefits JSON,  -- [{name, description}, ...]
    priority_level INT DEFAULT 0,  -- Display order
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    meta JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_memberships_tenant_active_idx (tenant_id, is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Customer subscription
CREATE TABLE perka_subscriptions (
    id CHAR(26) NOT NULL PRIMARY KEY,
    customer_id CHAR(26) NOT NULL,
    membership_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',  -- active|paused|cancelled|expired
    current_period_start DATETIME NOT NULL,
    current_period_end DATETIME NOT NULL,
    trial_end DATETIME,
    bookings_this_period INT NOT NULL DEFAULT 0,
    stripe_subscription_id VARCHAR(255),  -- Stripe subscription ID
    cancelled_at DATETIME,
    cancellation_reason VARCHAR(255),
    renewal_date DATETIME,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_subscriptions_customer_idx (customer_id),
    KEY perka_subscriptions_tenant_idx (tenant_id),
    KEY perka_subscriptions_status_idx (status),
    KEY perka_subscriptions_renewal_idx (renewal_date),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (membership_id) REFERENCES perka_memberships(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Subscription invoice/billing
CREATE TABLE perka_subscription_invoices (
    id CHAR(26) NOT NULL PRIMARY KEY,
    subscription_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    status VARCHAR(50) NOT NULL,  -- pending|paid|failed|refunded|cancelled
    invoice_number VARCHAR(50) UNIQUE,
    billing_period_start DATETIME,
    billing_period_end DATETIME,
    due_date DATETIME,
    paid_at DATETIME,
    stripe_invoice_id VARCHAR(255),
    error_message TEXT,
    retry_count INT DEFAULT 0,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_subscription_invoices_subscription_idx (subscription_id),
    KEY perka_subscription_invoices_status_idx (status),
    KEY perka_subscription_invoices_due_idx (due_date),
    FOREIGN KEY (subscription_id) REFERENCES perka_subscriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### New Services

**Engine/Perka/MembershipService.php**:
- `createMembership(tenant_id, data)`: Define plan
- `subscribeCustomer(customer_id, membership_id)`: Create subscription
- `renewSubscription(subscription_id)`: Charge and extend period
- `pauseSubscription(subscription_id, reason)`: Pause (retain benefits)
- `resumeSubscription(subscription_id)`: Resume after pause
- `cancelSubscription(subscription_id, reason)`: End subscription
- `checkBookingLimit(subscription_id): {allowed, remaining}`
- `getActiveSubscription(customer_id, tenant_id): ?subscription`

### New Controllers

**Admin/Perka/MembershipsController.php**:
- `index()`: `GET /admin/tenants/{tenant_id}/perka/memberships`
- `create()`: `GET /admin/tenants/{tenant_id}/perka/memberships/create`
- `store()`: `POST /admin/tenants/{tenant_id}/perka/memberships`
- `edit()`: `GET /admin/tenants/{tenant_id}/perka/memberships/{id}/edit`
- `update()`: `POST /admin/tenants/{tenant_id}/perka/memberships/{id}`
- `customers()`: `GET /admin/tenants/{tenant_id}/perka/memberships/customers`
- `cancelCustomer()`: `POST /admin/tenants/{tenant_id}/perka/subscriptions/{id}/cancel`

### APIs

**Public API**:
```
GET /api/{slug}/perka/memberships
→ [{ id, name, price, interval, booking_limit, benefits, ... }]

POST /api/{slug}/perka/memberships/{id}/subscribe
→ { stripe_session_url | subscription_id }

GET /api/{slug}/perka/subscription/status
→ { status, current_period_end, bookings_remaining, ... }
```

### Events Fired

```php
Event::dispatch('membership.created', { membership_id, tenant_id });
Event::dispatch('subscription.started', { 
    subscription_id, customer_id, membership_id 
});
Event::dispatch('subscription.renewed', { 
    subscription_id, amount, period_end 
});
Event::dispatch('subscription.cancelled', { 
    subscription_id, reason 
});
Event::dispatch('subscription.booking_limit_reached', { 
    subscription_id, customer_id 
});
```

### Webhooks

```json
{
  "event": "subscription.started",
  "subscription_id": "...",
  "customer_id": "...",
  "membership_name": "Unlimited Monthly",
  "next_billing_date": "2025-01-15",
  "timestamp": "2024-12-15T10:00:00Z"
}
```

### UI Changes

**Booking Page**:
- Show "You have X bookings remaining this month" if subscribed
- Disable booking if limit reached
- Show membership benefits info

**Admin**:
- Memberships configuration panel
- List of active subscribers
- Invoice history and payment status

### Dependencies
- Requires: Payment Processing (Stripe integration)
- Used by: Booking creation (enforce limits)

---

## Module 4: Prepaid Passes

### Purpose
Customer prepayment for credit/booking bundles.

### Database Changes

**New Tables**:

```sql
-- Pass/package definition
CREATE TABLE perka_pass_packages (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,  -- "10-Pack Haircuts"
    description TEXT,
    service_id CHAR(26),  -- NULL = any service
    credits INT NOT NULL,  -- 10 bookings
    validity_days INT,  -- Expires after 90 days (NULL = no expiry)
    price DECIMAL(10, 2) NOT NULL,
    discount_percent DECIMAL(5, 2),  -- Bulk discount
    per_booking_credit_limit INT,  -- Max per booking (e.g., 1)
    reusable TINYINT(1) DEFAULT 1,  -- Can use multiple times
    max_per_customer INT,  -- Max purchases per customer
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    meta JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_pass_packages_tenant_active_idx (tenant_id, is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);

-- Customer purchase of package
CREATE TABLE perka_pass_purchases (
    id CHAR(26) NOT NULL PRIMARY KEY,
    customer_id CHAR(26) NOT NULL,
    package_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    credits_purchased INT NOT NULL,
    credits_remaining INT NOT NULL,
    credits_used INT NOT NULL DEFAULT 0,
    price_paid DECIMAL(10, 2),
    discount_applied DECIMAL(10, 2),
    purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    is_expired TINYINT(1) NOT NULL DEFAULT 0,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_pass_purchases_customer_idx (customer_id),
    KEY perka_pass_purchases_tenant_idx (tenant_id),
    KEY perka_pass_purchases_expired_idx (is_expired),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES perka_pass_packages(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Credit usage per booking (immutable log)
CREATE TABLE perka_pass_usage (
    id CHAR(26) NOT NULL PRIMARY KEY,
    purchase_id CHAR(26) NOT NULL,
    booking_id CHAR(26) NOT NULL,
    credits_used INT NOT NULL,
    used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_pass_usage_purchase_idx (purchase_id),
    KEY perka_pass_usage_booking_idx (booking_id),
    FOREIGN KEY (purchase_id) REFERENCES perka_pass_purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);
```

### New Services

**Engine/Perka/PassService.php**:
- `purchasePass(customer_id, package_id)`: Create purchase
- `useCredits(booking_id, customer_id, credits_needed)`: Debit from active purchase
- `getAvailableCredits(customer_id, tenant_id): int`: Sum unused across all purchases
- `checkPassEligibility(customer_id, service_id): {can_use, credits_available}`
- `expireCredits(tenant_id, days_old)`: Mark expired
- `autoRefundExpired(purchase_id)`: Handle expiry (refund or credit)

### New Controllers

**Admin/Perka/PassPackagesController.php**:
- `index()`: List packages
- `create()`, `store()`, `edit()`, `update()`: CRUD
- `customerPurchases()`: View customer purchases

### APIs

**Public API**:
```
GET /api/{slug}/perka/pass-packages
→ [{ id, name, credits, price, validity_days, discount, ... }]

GET /api/{slug}/perka/pass/balance
→ { credits_available, purchases: [...] }

POST /api/{slug}/perka/pass/purchase
{ package_id }
→ { stripe_session_url | purchase_id }
```

### Events Fired

```php
Event::dispatch('pass.purchased', { purchase_id, customer_id, credits });
Event::dispatch('pass.used', { purchase_id, booking_id, credits_used });
Event::dispatch('pass.expired', { purchase_id });
```

### Webhooks

```json
{
  "event": "pass.purchased",
  "purchase_id": "...",
  "customer_id": "...",
  "credits": 10,
  "price": "90.00",
  "expires_at": "2025-03-15",
  "timestamp": "2024-12-15T10:00:00Z"
}
```

### UI Changes

**Booking Page**:
- Show "Use pass credits? 10 available" selector
- Deduct from pass before charging card
- Show remaining after booking

**Admin**:
- Pass package CRUD
- Customer purchase history

### Dependencies
- Requires: Payment Processing
- Used by: Booking creation (apply credits)

---

## Module 5: Gift Cards

### Purpose
Physical and digital gift cards with codes.

### Database Changes

**New Tables**:

```sql
-- Gift card product/template
CREATE TABLE perka_gift_card_products (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,  -- "Spa Relaxation Package"
    description TEXT,
    value_type VARCHAR(50) NOT NULL,  -- fixed|variable
    fixed_value DECIMAL(10, 2),  -- €50 card
    min_value DECIMAL(10, 2),  -- Variable: €25-100
    max_value DECIMAL(10, 2),
    currency VARCHAR(3) NOT NULL,
    expiry_days INT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    meta JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_gift_card_products_tenant_idx (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Issued gift card instance
CREATE TABLE perka_gift_cards (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    product_id CHAR(26) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,  -- Alphanumeric code for redemption
    buyer_customer_id CHAR(26),  -- Who bought it
    recipient_email VARCHAR(255),  -- Who it's for
    recipient_name VARCHAR(255),
    value DECIMAL(10, 2) NOT NULL,
    balance_remaining DECIMAL(10, 2) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',  -- active|redeemed|expired|cancelled
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    redeemed_at DATETIME,
    expires_at DATETIME,
    personal_message TEXT,
    is_digital TINYINT(1) NOT NULL DEFAULT 1,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY perka_gift_cards_code (code, tenant_id),
    KEY perka_gift_cards_tenant_idx (tenant_id),
    KEY perka_gift_cards_status_idx (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES perka_gift_card_products(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- Gift card usage (immutable)
CREATE TABLE perka_gift_card_usage (
    id CHAR(26) NOT NULL PRIMARY KEY,
    gift_card_id CHAR(26) NOT NULL,
    booking_id CHAR(26) NOT NULL,
    customer_id CHAR(26) NOT NULL,
    amount_used DECIMAL(10, 2) NOT NULL,
    used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_gift_card_usage_card_idx (gift_card_id),
    KEY perka_gift_card_usage_booking_idx (booking_id),
    FOREIGN KEY (gift_card_id) REFERENCES perka_gift_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);
```

### New Services

**Engine/Perka/GiftCardService.php**:
- `issueGiftCard(tenant_id, product_id, value, buyer, recipient)`: Create card
- `validateCode(code, tenant_id): {valid, card, balance}`
- `redeemGiftCard(code, amount, booking_id): success`
- `checkBalance(code)`: Remaining balance
- `expireCards(tenant_id, days_old)`: Mark expired
- `sendDigitalCard(card_id, email)`: Email with code

### New Controllers

**Admin/Perka/GiftCardsController.php**:
- `products()`: List products
- `issue()`: Manually issue card
- `search()`: Find card by code
- `usage()`: View redemption history

**Booking/GiftCardApi.php**:
- `POST /api/{slug}/perka/gift-card/redeem`: Redeem code

### APIs

**Public**:
```
POST /api/{slug}/perka/gift-card/validate
{ "code": "ABC123XYZ" }
→ { valid: true, balance: "45.00", expires_at: "2025-12-15" }

POST /api/{slug}/perka/bookings/{id}/apply-gift-card
{ "code": "ABC123XYZ" }
→ { discount: "45.00", balance_remaining: "0.00" }
```

### Events Fired

```php
Event::dispatch('gift_card.issued', { gift_card_id, value });
Event::dispatch('gift_card.redeemed', { gift_card_id, amount_used });
Event::dispatch('gift_card.expired', { gift_card_id });
```

### Webhooks

```json
{
  "event": "gift_card.issued",
  "gift_card_id": "...",
  "code": "ABC123XYZ",
  "value": "50.00",
  "recipient_email": "recipient@example.com",
  "timestamp": "2024-12-15T10:00:00Z"
}
```

### UI Changes

**Booking Page**:
- "Have a gift card?" input field
- Validate code and show balance
- Apply to booking

**Admin**:
- Issue gift cards form
- Gift card history and balance tracking
- Send digital card email

### Dependencies
- Requires: Payment Processing (for purchase)
- Used by: Booking checkout (apply to payment)

---

## Module 6: Wallet

### Purpose
Customer financial account for credits, balance, and payment methods.

### Database Changes

**New Tables**:

```sql
-- Customer wallet balance
CREATE TABLE perka_wallets (
    id CHAR(26) NOT NULL PRIMARY KEY,
    customer_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) NOT NULL,
    total_charged DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total_refunded DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY perka_wallets_customer_tenant (customer_id, tenant_id),
    KEY perka_wallets_tenant_idx (tenant_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Wallet transaction ledger (immutable)
CREATE TABLE perka_wallet_transactions (
    id CHAR(26) NOT NULL PRIMARY KEY,
    wallet_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    transaction_type VARCHAR(50) NOT NULL,  -- topup|charge|refund|adjustment|payout
    amount DECIMAL(10, 2) NOT NULL,
    description VARCHAR(255),
    booking_id CHAR(26),  -- If related to booking
    payment_id CHAR(26),  -- If related to payment
    balance_before DECIMAL(10, 2) NOT NULL,
    balance_after DECIMAL(10, 2) NOT NULL,
    metadata JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_wallet_transactions_wallet_idx (wallet_id),
    KEY perka_wallet_transactions_type_idx (transaction_type),
    KEY perka_wallet_transactions_booking_idx (booking_id),
    FOREIGN KEY (wallet_id) REFERENCES perka_wallets(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);

-- Saved payment methods
CREATE TABLE perka_wallet_payment_methods (
    id CHAR(26) NOT NULL PRIMARY KEY,
    wallet_id CHAR(26) NOT NULL,
    customer_id CHAR(26) NOT NULL,
    payment_method_type VARCHAR(50) NOT NULL,  -- card|bank_account|apple_pay|google_pay
    display_name VARCHAR(255),  -- "Visa ending in 4242"
    stripe_payment_method_id VARCHAR(255),
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_wallet_payment_methods_wallet_idx (wallet_id),
    FOREIGN KEY (wallet_id) REFERENCES perka_wallets(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);
```

### New Services

**Engine/Perka/WalletService.php**:
- `getWallet(customer_id, tenant_id): wallet`
- `topupWallet(wallet_id, amount, payment_method)`: Add funds
- `chargeWallet(wallet_id, amount, reason)`: Debit for booking
- `refundWallet(wallet_id, amount, reason)`: Refund
- `getBalance(customer_id, tenant_id): decimal`
- `transferBetweenCustomers(from_customer, to_customer, amount)`: P2P
- `addPaymentMethod(wallet_id, stripe_token)`: Save card
- `setDefaultPaymentMethod(wallet_id, payment_method_id)`: Set default

### New Controllers

**Booking/WalletApi.php**:
- `GET /api/{slug}/perka/wallet`: Get balance and methods
- `POST /api/{slug}/perka/wallet/topup`: Add funds
- `POST /api/{slug}/perka/wallet/payment-method`: Save card

**Admin/Perka/WalletController.php**:
- `customerWallet()`: View customer wallet
- `manualAdjustment()`: Admin credit/charge
- `transactionHistory()`: View ledger

### APIs

**Public**:
```
GET /api/{slug}/perka/wallet
→ { balance: "50.00", currency: "EUR", payment_methods: [...] }

POST /api/{slug}/perka/wallet/topup
{ amount: "50.00", payment_method_id: "..." }
→ { new_balance, transaction_id }
```

### Events Fired

```php
Event::dispatch('wallet.topped_up', { wallet_id, amount });
Event::dispatch('wallet.charged', { wallet_id, amount, reason });
Event::dispatch('wallet.refunded', { wallet_id, amount, reason });
```

### Webhooks

```json
{
  "event": "wallet.topped_up",
  "wallet_id": "...",
  "customer_id": "...",
  "amount": "50.00",
  "new_balance": "150.00",
  "timestamp": "2024-12-15T10:00:00Z"
}
```

### UI Changes

**Booking Page**:
- Show wallet balance before checkout
- Option to pay from wallet
- "Low balance" warning

**Admin**:
- View customer wallet and transactions
- Manual adjustment (admin credit)

### Dependencies
- Requires: Payment Processing
- Used by: Booking checkout

---

## Module 7: Paid Plans (SaaS Billing)

### Purpose
Perka's own SaaS subscription tiers for businesses.

### Database Changes

**New Tables**:

```sql
-- Perka's SaaS plans
CREATE TABLE perka_saas_plans (
    id CHAR(26) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,  -- "Professional", "Enterprise"
    description TEXT,
    interval VARCHAR(50) NOT NULL,  -- monthly|annual
    price DECIMAL(10, 2) NOT NULL,
    setup_fee DECIMAL(10, 2),
    booking_limit INT,  -- NULL = unlimited
    features JSON,  -- {loyalty_enabled, marketplace_enabled, ...}
    max_staff INT,  -- max_staff_users = team seats
    max_customers INT,  -- max_customers_tracked
    storage_gb INT,
    api_requests_per_month INT,
    support_level VARCHAR(50),  -- basic|priority|dedicated
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id)
);

-- Tenant's current plan subscription
CREATE TABLE perka_tenant_subscriptions (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    plan_id CHAR(26) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',  -- active|past_due|cancelled
    current_period_start DATETIME NOT NULL,
    current_period_end DATETIME NOT NULL,
    trial_end DATETIME,
    stripe_subscription_id VARCHAR(255),
    stripe_customer_id VARCHAR(255),
    cancelled_at DATETIME,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY perka_tenant_subscriptions_tenant (tenant_id),
    KEY perka_tenant_subscriptions_status_idx (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES perka_saas_plans(id)
);

-- Usage tracking for overage charges
CREATE TABLE perka_usage_tracking (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_subscription_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    metric VARCHAR(50) NOT NULL,  -- bookings|api_requests|storage_gb
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    usage_amount INT NOT NULL,
    limit_amount INT,
    overage_charged DECIMAL(10, 2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_usage_tracking_tenant_idx (tenant_id),
    FOREIGN KEY (tenant_subscription_id) REFERENCES perka_tenant_subscriptions(id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### New Services

**Engine/Perka/SaasBillingService.php**:
- `subscribeTenantToPlan(tenant_id, plan_id, payment_method)`: Onboard business
- `upgradePlan(tenant_subscription_id, new_plan_id)`: Upgrade/downgrade
- `cancelSubscription(tenant_subscription_id, reason)`: Cancel
- `trackUsage(tenant_id, metric, amount)`: Log usage
- `chargeOverage(tenant_subscription_id, amount)`: Charge extras
- `renewSubscription(tenant_subscription_id)`: Automatic renewal
- `getActivePlan(tenant_id): plan`
- `checkFeatureAvailable(tenant_id, feature): bool`

### New Controllers

**Admin/Perka/SaasBillingController.php**:
- `plans()`: Manage SaaS plans (operator only)
- `tenantSubscription()`: View tenant's subscription
- `upgradeDowngrade()`: Change tenant's plan
- `invoices()`: Billing history

**Booking/SaasApi.php**:
- `POST /api/{slug}/perka/upgrade-plan`: Change plan (tenant admin)

### APIs

**Admin API** (operator only):
```
GET /admin/perka/saas/plans
→ [{ id, name, price, features, booking_limit, ... }]

POST /admin/perka/saas/tenants/{tenant_id}/subscription
{ plan_id }
→ { stripe_session_url | subscription_id }

GET /admin/perka/saas/tenants/{tenant_id}/billing
→ { current_plan, next_billing_date, usage, invoices, ... }
```

### Events Fired

```php
Event::dispatch('saas.subscribed', { tenant_id, plan_id, amount });
Event::dispatch('saas.upgraded', { tenant_id, old_plan, new_plan });
Event::dispatch('saas.cancelled', { tenant_id, reason });
Event::dispatch('saas.feature_unavailable', { tenant_id, feature });
```

### Webhooks

```json
{
  "event": "saas.subscribed",
  "tenant_id": "...",
  "plan_name": "Professional",
  "price": "79.00",
  "next_billing_date": "2025-01-15",
  "timestamp": "2024-12-15T10:00:00Z"
}
```

### UI Changes

**Admin Panel** (operator):
- Plans management
- Tenant billing view
- Usage tracking
- Invoice history

**Booking Page** (tenant admin):
- Upgrade/downgrade CTA if free trial
- "Upgrade to unlock feature X" prompt

### Dependencies
- Requires: Payment Processing (Stripe)
- Used by: Feature flags (enforce feature availability)

---

## Module 8: Marketplace

### Purpose
Peer-to-peer booking and credit resale.

### Database Changes

**New Tables**:

```sql
-- Marketplace listing (credits, passes, gift cards for resale)
CREATE TABLE perka_marketplace_listings (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    seller_id CHAR(26),  -- customer_id (who's selling)
    listing_type VARCHAR(50) NOT NULL,  -- pass|gift_card|booking_slot
    original_item_id CHAR(26),  -- perka_pass_purchases.id, perka_gift_cards.id, etc.
    quantity_available INT NOT NULL,
    price_per_unit DECIMAL(10, 2) NOT NULL,  -- Markup pricing
    original_price DECIMAL(10, 2),  -- For reference
    expiry_date DATE,
    status VARCHAR(50) NOT NULL DEFAULT 'active',  -- active|sold_out|expired|delisted
    delisted_reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_marketplace_listings_tenant_idx (tenant_id),
    KEY perka_marketplace_listings_seller_idx (seller_id),
    KEY perka_marketplace_listings_status_idx (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- Marketplace transaction (immutable)
CREATE TABLE perka_marketplace_transactions (
    id CHAR(26) NOT NULL PRIMARY KEY,
    listing_id CHAR(26) NOT NULL,
    buyer_id CHAR(26) NOT NULL,
    seller_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    quantity INT NOT NULL,
    price_total DECIMAL(10, 2) NOT NULL,
    platform_fee DECIMAL(10, 2) NOT NULL,  -- 10%
    seller_payout DECIMAL(10, 2) NOT NULL,  -- 90%
    status VARCHAR(50) NOT NULL DEFAULT 'pending',  -- pending|completed|disputed|refunded
    delivery_method VARCHAR(50),  -- instant|manual
    completed_at DATETIME,
    dispute_reason TEXT,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_marketplace_transactions_buyer_idx (buyer_id),
    KEY perka_marketplace_transactions_seller_idx (seller_id),
    KEY perka_marketplace_transactions_status_idx (status),
    FOREIGN KEY (listing_id) REFERENCES perka_marketplace_listings(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Marketplace seller reputation
CREATE TABLE perka_marketplace_sellers (
    id CHAR(26) NOT NULL PRIMARY KEY,
    customer_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    rating DECIMAL(2, 1),  -- 1.0-5.0
    review_count INT DEFAULT 0,
    sales_count INT DEFAULT 0,
    total_sales DECIMAL(10, 2) DEFAULT 0,
    is_suspended TINYINT(1) DEFAULT 0,
    suspension_reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY perka_marketplace_sellers_customer_tenant (customer_id, tenant_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Marketplace reviews
CREATE TABLE perka_marketplace_reviews (
    id CHAR(26) NOT NULL PRIMARY KEY,
    transaction_id CHAR(26) NOT NULL,
    reviewer_id CHAR(26) NOT NULL,
    seller_id CHAR(26) NOT NULL,
    rating INT NOT NULL,  -- 1-5
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_marketplace_reviews_seller_idx (seller_id),
    FOREIGN KEY (transaction_id) REFERENCES perka_marketplace_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES customers(id) ON DELETE CASCADE
);
```

### New Services

**Engine/Perka/MarketplaceService.php**:
- `listItem(customer_id, item_type, item_id, quantity, price)`: Create listing
- `purchaseListing(listing_id, buyer_id, quantity)`: Buy item
- `completeTransaction(transaction_id)`: Transfer ownership
- `refundTransaction(transaction_id, reason)`: Reverse sale
- `reviewSeller(transaction_id, reviewer_id, rating, comment)`: Leave review
- `suspendSeller(seller_id, reason)`: Ban from marketplace
- `payoutSeller(seller_id, amount)`: Weekly payout

### New Controllers

**Booking/MarketplaceApi.php**:
- `GET /api/{slug}/perka/marketplace/listings`: Search
- `GET /api/{slug}/perka/marketplace/listings/{id}`: Detail
- `POST /api/{slug}/perka/marketplace/listings`: Create (seller)
- `POST /api/{slug}/perka/marketplace/purchase`: Buy (buyer)
- `POST /api/{slug}/perka/marketplace/review`: Leave review

**Admin/Perka/MarketplaceController.php**:
- `listings()`: Browse all listings
- `disputes()`: Handle disputes
- `suspendSeller()`: Moderation

### APIs

**Public**:
```
GET /api/{slug}/perka/marketplace/listings?type=pass&sort=price
→ [{ id, seller, quantity, price, rating, ... }]

POST /api/{slug}/perka/marketplace/purchase
{ listing_id, quantity }
→ { transaction_id, completion_time }
```

### Events Fired

```php
Event::dispatch('marketplace.listing_created', { listing_id, seller_id });
Event::dispatch('marketplace.item_sold', { transaction_id, quantity });
Event::dispatch('marketplace.transaction_completed', { transaction_id });
Event::dispatch('marketplace.review_posted', { review_id, seller_id, rating });
```

### Webhooks

```json
{
  "event": "marketplace.item_sold",
  "transaction_id": "...",
  "seller_id": "...",
  "quantity": 5,
  "revenue": "45.00",
  "platform_fee": "5.00",
  "seller_payout": "40.00",
  "timestamp": "2024-12-15T10:00:00Z"
}
```

### UI Changes

**Booking Page**:
- "Browse used credits" link
- Marketplace search/filter
- Seller ratings and reviews
- Purchase flow

**Admin**:
- Marketplace moderation
- Dispute resolution
- Seller management

### Dependencies
- Requires: Wallet, Payment Processing
- Used by: Pass/Gift Card resale

---

## Module 9: WhatsApp Integration

### Purpose
Send booking confirmations, reminders, and messages via WhatsApp.

### Database Changes

**New Tables**:

```sql
-- WhatsApp business account configuration
CREATE TABLE perka_whatsapp_integrations (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    twilio_account_sid VARCHAR(255),
    twilio_auth_token VARCHAR(255),
    twilio_phone_number VARCHAR(50),  -- +1234567890
    whatsapp_phone_number VARCHAR(50),  -- Business WhatsApp
    webhook_url VARCHAR(500),
    webhook_token VARCHAR(255),
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    messaging_rate_limit INT DEFAULT 10,  -- msgs per minute
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY perka_whatsapp_integrations_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- WhatsApp message templates per tenant
CREATE TABLE perka_whatsapp_templates (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    template_type VARCHAR(50) NOT NULL,  -- booking_confirmation|reminder|cancellation|custom
    template_name VARCHAR(255),
    body TEXT NOT NULL,  -- {{customer_name}}, {{date}}, {{time}}, etc.
    locale VARCHAR(10) DEFAULT 'en',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_whatsapp_templates_tenant_type_idx (tenant_id, template_type),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Message log (immutable)
CREATE TABLE perka_whatsapp_messages (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    customer_id CHAR(26),
    booking_id CHAR(26),
    phone_number VARCHAR(50) NOT NULL,
    direction VARCHAR(50) NOT NULL,  -- inbound|outbound
    message_type VARCHAR(50),  -- confirmation|reminder|customer_inquiry|broadcast
    body TEXT NOT NULL,
    status VARCHAR(50) NOT NULL,  -- queued|sent|delivered|read|failed
    twilio_message_sid VARCHAR(255),
    error_message TEXT,
    retry_count INT DEFAULT 0,
    sent_at DATETIME,
    delivered_at DATETIME,
    read_at DATETIME,
    metadata JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_whatsapp_messages_tenant_idx (tenant_id),
    KEY perka_whatsapp_messages_customer_idx (customer_id),
    KEY perka_whatsapp_messages_status_idx (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);

-- WhatsApp message settings per tenant
CREATE TABLE perka_whatsapp_settings (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    send_confirmations TINYINT(1) NOT NULL DEFAULT 1,
    send_reminders TINYINT(1) NOT NULL DEFAULT 1,
    send_cancellations TINYINT(1) NOT NULL DEFAULT 1,
    send_follow_up TINYINT(1) NOT NULL DEFAULT 0,
    follow_up_hours_after INT DEFAULT 24,
    enable_customer_responses TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY perka_whatsapp_settings_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### New Services

**Engine/Perka/WhatsappService.php**:
- `sendMessage(tenant_id, phone, message, type)`: Send via Twilio
- `sendTemplate(tenant_id, phone, template_type, data)`: Render + send template
- `receiveMessage(phone, message)`: Inbound handler
- `getDeliveryStatus(message_id)`: Poll Twilio status
- `handleWebhook(payload)`: Process Twilio callbacks
- `loadTemplates(tenant_id, locale)`: Get available templates

### New Controllers

**Admin/Perka/WhatsappController.php**:
- `settingsForm()`: `GET /admin/tenants/{tenant_id}/perka/whatsapp/settings`
- `saveSettings()`: `POST /admin/tenants/{tenant_id}/perka/whatsapp/settings`
- `connectAccount()`: `POST /admin/tenants/{tenant_id}/perka/whatsapp/connect` (Twilio OAuth)
- `templates()`: `GET /admin/tenants/{tenant_id}/perka/whatsapp/templates`
- `messageLog()`: `GET /admin/tenants/{tenant_id}/perka/whatsapp/messages`

**Webhook Handler**:
- `POST /perka/webhooks/whatsapp`: Receive inbound + delivery status

### APIs

**Admin**:
```
POST /admin/tenants/{tenant_id}/perka/whatsapp/test
{ phone: "+31612345678" }
→ { sent: true, message_id: "..." }
```

### Events Fired

```php
Event::dispatch('whatsapp.message_sent', { message_id, phone, type });
Event::dispatch('whatsapp.message_delivered', { message_id });
Event::dispatch('whatsapp.message_read', { message_id });
Event::dispatch('whatsapp.inbound_message', { phone, text });
```

### Webhooks (from Twilio → Perka)

```json
POST /perka/webhooks/whatsapp
{
  "MessageSid": "SM...",
  "MessageStatus": "delivered|read",
  "From": "+31612345678"
}
```

### UI Changes

**Admin**:
- WhatsApp connect button (Twilio)
- Template customization per language
- Message log viewer
- Send test message

**Booking Page**:
- Phone number input (WhatsApp opt-in checkbox)
- "You'll receive updates via WhatsApp" note

### Dependencies
- Requires: Twilio account
- Listens to: booking.created, booking.cancelled
- Used by: Confirmation/reminder flows

---

## Module 10: AI Assistant

### Purpose
Conversational booking via Claude API.

### Database Changes

**New Tables**:

```sql
-- AI assistant configuration per tenant
CREATE TABLE perka_ai_assistants (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    assistant_name VARCHAR(255) DEFAULT 'Booking Assistant',
    system_prompt TEXT,  -- Custom instructions
    model VARCHAR(50) DEFAULT 'claude-3-5-sonnet-20241022',
    temperature DECIMAL(2, 2) DEFAULT 0.7,
    max_tokens INT DEFAULT 1024,
    tools_enabled JSON,  -- [availability, create_booking, cancel_booking, ...]
    greeting_message TEXT,
    language VARCHAR(10) DEFAULT 'en',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY perka_ai_assistants_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Conversation history (immutable)
CREATE TABLE perka_ai_conversations (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    customer_id CHAR(26),
    phone_number VARCHAR(50),  -- For WhatsApp/SMS conversations
    conversation_mode VARCHAR(50),  -- web_widget|whatsapp|sms|email
    status VARCHAR(50) NOT NULL DEFAULT 'active',  -- active|completed|abandoned
    booking_created_id CHAR(26),  -- If conversation led to booking
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME,
    message_count INT DEFAULT 0,
    metadata JSON,
    
    PRIMARY KEY (id),
    KEY perka_ai_conversations_tenant_idx (tenant_id),
    KEY perka_ai_conversations_customer_idx (customer_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_created_id) REFERENCES bookings(id) ON DELETE SET NULL
);

-- Message turns in conversation (immutable)
CREATE TABLE perka_ai_messages (
    id CHAR(26) NOT NULL PRIMARY KEY,
    conversation_id CHAR(26) NOT NULL,
    role VARCHAR(50) NOT NULL,  -- user|assistant
    message_text TEXT NOT NULL,
    tokens_used INT,
    function_calls JSON,  -- {name, arguments}
    function_results JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_ai_messages_conversation_idx (conversation_id),
    FOREIGN KEY (conversation_id) REFERENCES perka_ai_conversations(id) ON DELETE CASCADE
);
```

### New Services

**Engine/Perka/AiAssistantService.php**:
- `createConversation(tenant_id, customer_id, mode): conversation_id`
- `sendMessage(conversation_id, user_message): ai_response`
- `processTool(tool_name, arguments)`: Call tool function
- `handleBookingCreation(ai_intent, customer_id, tenant_id): booking_id`
- `endConversation(conversation_id, reason)`: Close chat
- `getConversationHistory(conversation_id)`: Retrieve messages
- `generateSystemPrompt(tenant_id)`: Build context from tenant config

**Engine/Perka/AiTools.php**:
- `getAvailableSlots(tenant_id, service_id, date)`: Query slots
- `createBooking(tenant_id, customer_id, service_id, datetime, ...)`: Create booking
- `cancelBooking(booking_id, reason)`: Cancel
- `checkAccountBalance(customer_id, tenant_id)`: Loyalty/wallet balance
- `listServices(tenant_id)`: Service catalog
- `getBusinessHours(tenant_id)`: Operating hours

### New Controllers

**Booking/AiAssistantApi.php**:
- `POST /api/{slug}/perka/ai/chat`: Send message
- `GET /api/{slug}/perka/ai/chat/{conversation_id}`: Get history
- `GET /api/{slug}/perka/ai/config`: Get assistant config

**Admin/Perka/AiAssistantController.php**:
- `settings()`: Configure assistant
- `saveSettings()`: Save system prompt, model, tools
- `conversationHistory()`: View past chats
- `testAssistant()`: Send test message

### APIs

**Public**:
```
POST /api/{slug}/perka/ai/chat
{
  "message": "Book me a haircut next Tuesday",
  "conversation_id": "..." // optional
}
→ {
  "conversation_id": "...",
  "response": "I found 3 available slots...",
  "action": "booking_created" | "clarification_needed" | null,
  "booking_id": "..." // if booking created
}
```

### Events Fired

```php
Event::dispatch('ai.message_received', { conversation_id, message });
Event::dispatch('ai.booking_created_via_ai', { booking_id, conversation_id });
Event::dispatch('ai.tool_called', { conversation_id, tool_name });
Event::dispatch('ai.conversation_ended', { conversation_id, reason });
```

### Webhooks

```json
{
  "event": "ai.booking_created",
  "conversation_id": "...",
  "booking_id": "...",
  "customer_id": "...",
  "service_name": "Haircut",
  "datetime": "2024-12-15T10:00:00Z",
  "timestamp": "2024-12-15T10:00:00Z"
}
```

### UI Changes

**Booking Page**:
- Chat widget in bottom-right corner
- Greeting message from AI
- Chat history in drawer
- "Chat with AI" button

**Admin**:
- Assistant configuration panel
- Test chat interface
- Conversation history viewer

### Dependencies
- Requires: Anthropic Claude API key
- Uses: BookingService, TimeSlotCalculator, CustomerService
- Integrates with: WhatsApp (for SMS conversations)

---

## Module 11: Reviews

### Purpose
Customer ratings and reviews of services, staff, and businesses.

### Database Changes

**New Tables**:

```sql
-- Review configuration per tenant
CREATE TABLE perka_review_settings (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    require_booking TINYINT(1) NOT NULL DEFAULT 1,
    allow_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    moderate_before_publish TINYINT(1) NOT NULL DEFAULT 0,
    request_reviews_automatically TINYINT(1) NOT NULL DEFAULT 1,
    request_after_hours INT DEFAULT 24,  -- hours after booking
    display_on_profile TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY perka_review_settings_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Review record (immutable, audited)
CREATE TABLE perka_reviews (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    reviewer_id CHAR(26),  -- customer_id (NULL if anonymous)
    booking_id CHAR(26),  -- Related booking
    service_id CHAR(26),  -- Service reviewed
    staff_id CHAR(26),  -- Staff reviewed (optional)
    rating INT NOT NULL,  -- 1-5 stars
    title VARCHAR(255),
    comment TEXT,
    helpful_count INT DEFAULT 0,
    unhelpful_count INT DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',  -- pending|approved|rejected
    approved_at DATETIME,
    is_verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
    reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_reviews_tenant_idx (tenant_id),
    KEY perka_reviews_reviewer_idx (reviewer_id),
    KEY perka_reviews_booking_idx (booking_id),
    KEY perka_reviews_service_idx (service_id),
    KEY perka_reviews_staff_idx (staff_id),
    KEY perka_reviews_status_idx (status),
    KEY perka_reviews_rating_idx (rating),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Review response from business
CREATE TABLE perka_review_responses (
    id CHAR(26) NOT NULL PRIMARY KEY,
    review_id CHAR(26) NOT NULL,
    responder_id CHAR(26) NOT NULL,  -- business_user_id
    response_text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY perka_review_responses_review (review_id),
    FOREIGN KEY (review_id) REFERENCES perka_reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (responder_id) REFERENCES business_users(id) ON DELETE CASCADE
);

-- Aggregate review statistics per service/staff
CREATE TABLE perka_review_stats (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    review_target_type VARCHAR(50) NOT NULL,  -- service|staff|tenant
    review_target_id CHAR(26) NOT NULL,  -- service_id, staff_id, or tenant_id
    average_rating DECIMAL(2, 1),  -- 1.0-5.0
    review_count INT DEFAULT 0,
    review_count_5star INT DEFAULT 0,
    review_count_4star INT DEFAULT 0,
    review_count_3star INT DEFAULT 0,
    review_count_2star INT DEFAULT 0,
    review_count_1star INT DEFAULT 0,
    recommendation_percent DECIMAL(5, 2),  -- % who'd recommend
    last_review_at DATETIME,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY perka_review_stats_target (review_target_type, review_target_id, tenant_id),
    KEY perka_review_stats_tenant_idx (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### New Services

**Engine/Perka/ReviewService.php**:
- `createReview(booking_id, customer_id, rating, comment)`: Submit review
- `approveReview(review_id)`: Moderate and publish
- `rejectReview(review_id, reason)`: Decline review
- `respondToReview(review_id, response_text)`: Business reply
- `getReviewStats(service_id|staff_id|tenant_id)`: Aggregate ratings
- `requestReview(booking_id, customer_id)`: Send request email/SMS
- `markHelpful(review_id, customer_id)`: Vote helpful
- `canReview(booking_id, customer_id): bool`: Eligibility check

### New Controllers

**Admin/Perka/ReviewsController.php**:
- `settings()`: Configure review settings
- `moderation()`: Approve/reject pending
- `responses()`: View and manage responses
- `stats()`: Review statistics dashboard

**Booking/ReviewApi.php**:
- `POST /api/{slug}/perka/reviews`: Submit review
- `GET /api/{slug}/perka/reviews`: List reviews for tenant/service
- `GET /api/{slug}/perka/reviews/stats`: Get stats

### APIs

**Public**:
```
GET /api/{slug}/perka/reviews?service_id=...&sort=recent
→ [{ id, rating, comment, responder_comment, verified, ... }]

POST /api/{slug}/perka/reviews
{
  "booking_id": "...",
  "rating": 5,
  "comment": "Excellent service!",
  "title": "Highly recommended"
}
→ { review_id, status: "pending" | "approved" }
```

### Events Fired

```php
Event::dispatch('review.submitted', { review_id, rating });
Event::dispatch('review.approved', { review_id, service_id });
Event::dispatch('review.responded', { review_id, responder_id });
Event::dispatch('review.stats_updated', { service_id, avg_rating });
```

### Webhooks

```json
{
  "event": "review.submitted",
  "review_id": "...",
  "booking_id": "...",
  "rating": 5,
  "comment": "...",
  "status": "pending",
  "timestamp": "2024-12-15T10:00:00Z"
}
```

### UI Changes

**Booking Page**:
- Review request after booking (email/SMS)
- Review form with star rating
- View existing reviews for service

**Admin**:
- Review moderation queue
- Response composer
- Review statistics and trends

### Dependencies
- None (self-contained)
- Listens to: booking.completed event

---

## Module 12: Public Business Profiles

### Purpose
SEO-optimized public profile pages for each business.

### Database Changes

**New Tables**:

```sql
-- Public profile configuration per tenant
CREATE TABLE perka_public_profiles (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    slug VARCHAR(255) UNIQUE,  -- Perka.com/profiles/{slug}
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    bio TEXT,
    featured_image VARCHAR(500),
    service_highlights JSON,  -- [{service_id, position}]
    staff_highlights JSON,
    social_links JSON,  -- {instagram, facebook, twitter, ...}
    meta_description VARCHAR(500),  -- SEO
    meta_keywords VARCHAR(255),  -- SEO
    custom_css TEXT,  -- Limited styling
    show_reviews TINYINT(1) DEFAULT 1,
    show_staff TINYINT(1) DEFAULT 1,
    show_calendar_availability TINYINT(1) DEFAULT 0,  -- Show next N available slots
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY perka_public_profiles_tenant (tenant_id),
    KEY perka_public_profiles_slug (slug),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- View/click tracking for analytics
CREATE TABLE perka_profile_views (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    profile_id CHAR(26) NOT NULL,
    view_date DATE,
    view_count INT DEFAULT 1,
    referrer VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_profile_views_tenant_date_idx (tenant_id, view_date),
    FOREIGN KEY (profile_id) REFERENCES perka_public_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### New Services

**Engine/Perka/PublicProfileService.php**:
- `createProfile(tenant_id)`: Initialize profile
- `updateProfile(tenant_id, data)`: Edit profile
- `getProfile(slug)`: Fetch for display
- `incrementViewCount(tenant_id, referrer)`: Track views
- `generateMetaTags(tenant_id): {title, description, image}`

### New Controllers

**Admin/Perka/PublicProfileController.php**:
- `edit()`: `GET /admin/tenants/{tenant_id}/perka/profile`
- `save()`: `POST /admin/tenants/{tenant_id}/perka/profile`
- `preview()`: Preview public profile

**PublicProfileController.php** (public):
- `show()`: `GET /profiles/{slug}` (public page)
- `json()`: `GET /api/profiles/{slug}.json` (data API)

### APIs

**Public**:
```
GET /profiles/{slug}
→ Rendered HTML page (SEO-optimized)

GET /api/profiles/{slug}.json
→ {
  "name": "...",
  "bio": "...",
  "rating": 4.8,
  "services": [...],
  "staff": [...],
  "reviews": [...]
}
```

### Events Fired

```php
Event::dispatch('profile.published', { tenant_id });
Event::dispatch('profile.viewed', { tenant_id, referrer });
```

### UI Changes

**Admin**:
- Profile editor with preview
- Social links configuration
- View analytics (traffic, referrers)

**Public**:
- `/profiles/{slug}` canonical URL
- SEO meta tags
- Service grid
- Staff gallery
- Review section
- "Book now" CTA

### Dependencies
- Requires: Reviews (show ratings)
- Used by: Search engines (sitemap)

---

## Module 13: Multi-location

### Purpose
Support businesses with multiple physical locations.

### Database Changes

**New Tables**:

```sql
-- Business location
CREATE TABLE perka_locations (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,  -- "Berlin Main", "Paris Branch"
    slug VARCHAR(100),  -- Subdomain or path: berlin.salon.com or salon.com/berlin
    address_line_1 VARCHAR(255),
    address_line_2 VARCHAR(255),
    city VARCHAR(100),
    postal_code VARCHAR(20),
    country_code VARCHAR(2),
    timezone VARCHAR(100) NOT NULL,
    phone VARCHAR(50),
    email VARCHAR(255),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT DEFAULT 0,
    meta JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_locations_tenant_active_idx (tenant_id, is_active),
    KEY perka_locations_tenant_slug_idx (tenant_id, slug),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Location-specific resources (staff, services, etc.)
CREATE TABLE perka_location_services (
    id CHAR(26) NOT NULL PRIMARY KEY,
    location_id CHAR(26) NOT NULL,
    service_id CHAR(26) NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    override_price DECIMAL(10, 2),  -- Location-specific pricing
    override_duration INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_location_services_location_idx (location_id),
    FOREIGN KEY (location_id) REFERENCES perka_locations(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

-- Location-specific staff
CREATE TABLE perka_location_staff (
    id CHAR(26) NOT NULL PRIMARY KEY,
    location_id CHAR(26) NOT NULL,
    staff_id CHAR(26) NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_location_staff_location_idx (location_id),
    FOREIGN KEY (location_id) REFERENCES perka_locations(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Location-specific availability rules
CREATE TABLE perka_location_availability (
    id CHAR(26) NOT NULL PRIMARY KEY,
    location_id CHAR(26) NOT NULL,
    day_of_week TINYINT NOT NULL,  -- 0=Mon, 6=Sun
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_location_availability_location_day_idx (location_id, day_of_week),
    FOREIGN KEY (location_id) REFERENCES perka_locations(id) ON DELETE CASCADE
);
```

### Schema Changes to Existing Tables

Modify `tenants` table:
```sql
ALTER TABLE tenants ADD COLUMN
    primary_location_id CHAR(26);  -- Links to perka_locations
```

Modify `bookings` table:
```sql
ALTER TABLE bookings ADD COLUMN
    location_id CHAR(26);  -- Which location booking is at
```

### New Services

**Engine/Perka/LocationService.php**:
- `createLocation(tenant_id, data)`: New location
- `updateLocation(location_id, data)`: Edit
- `deleteLocation(location_id)`: Remove
- `assignServiceToLocation(location_id, service_id, override_price)`: Map service
- `assignStaffToLocation(location_id, staff_id)`: Map staff
- `getLocationServices(location_id)`: List services at location
- `getLocationStaff(location_id)`: List staff at location
- `getPrimaryLocation(tenant_id)`: Default location

### New Controllers

**Admin/Perka/LocationsController.php**:
- `index()`: List locations
- `create()`, `store()`: New location
- `edit()`, `update()`: Edit location
- `services()`: Manage location services
- `staff()`: Manage location staff
- `availability()`: Location-specific hours

### APIs

**Public**:
```
GET /api/{slug}/perka/locations
→ [{ id, name, address, phone, timezone, services, staff, ... }]

GET /api/{slug}/perka/locations/{id}/availability?date=...
→ Available slots at this location
```

### Events Fired

```php
Event::dispatch('location.created', { location_id, tenant_id });
Event::dispatch('location.updated', { location_id });
Event::dispatch('location.deleted', { location_id });
```

### UI Changes

**Booking Page**:
- Location selector dropdown
- "Select location" first step
- Location-specific services/staff

**Admin**:
- Locations management panel
- Service/staff assignment per location
- Availability override per location

### Dependencies
- Requires: Core VoxelBooking (services, staff, availability)
- Used by: Booking creation (location-aware availability)

---

## Module 14: Analytics

### Purpose
BI dashboards, reports, and insights for business owners.

### Database Changes

**New Tables**:

```sql
-- Aggregated analytics snapshots (for performance)
CREATE TABLE perka_analytics_snapshots (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    snapshot_date DATE NOT NULL,
    metric_type VARCHAR(50) NOT NULL,  -- bookings|revenue|customers|reviews
    value DECIMAL(15, 2),
    value_secondary DECIMAL(15, 2),  -- e.g., count and revenue
    aggregation_period VARCHAR(50),  -- daily|weekly|monthly
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_analytics_snapshots_tenant_date_idx (tenant_id, snapshot_date),
    KEY perka_analytics_snapshots_metric_idx (metric_type),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Custom reports per business
CREATE TABLE perka_analytics_reports (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,
    report_type VARCHAR(50),  -- revenue|bookings|customers|staff_performance
    filters JSON,  -- {date_range, service_id, staff_id, ...}
    metrics JSON,  -- [avg_rating, total_revenue, conversion_rate, ...]
    visualization_type VARCHAR(50),  -- line|bar|pie|table
    schedule VARCHAR(50),  -- one_time|daily|weekly|monthly
    scheduled_send_day INT,  -- Day of week/month
    scheduled_send_time TIME,
    recipients JSON,  -- [email@...]
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_analytics_reports_tenant_idx (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Report execution log
CREATE TABLE perka_analytics_report_runs (
    id CHAR(26) NOT NULL PRIMARY KEY,
    report_id CHAR(26) NOT NULL,
    generated_at DATETIME NOT NULL,
    data JSON,  -- Report data snapshot
    sent_at DATETIME,
    delivery_status VARCHAR(50),  -- sent|failed
    error_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_analytics_report_runs_report_idx (report_id),
    FOREIGN KEY (report_id) REFERENCES perka_analytics_reports(id) ON DELETE CASCADE
);
```

### New Services

**Engine/Perka/AnalyticsService.php**:
- `getMetrics(tenant_id, metric_type, date_range, filters)`: Query metrics
- `getRevenueSummary(tenant_id, date_range)`: Total revenue, by service, by staff
- `getBookingsSummary(tenant_id, date_range)`: Total, by status, no-show rate
- `getCustomerSummary(tenant_id, date_range)`: New, returning, churn, LTV
- `getStaffPerformance(tenant_id, staff_id, date_range)`: Bookings, revenue, ratings
- `getConversionAnalysis(tenant_id, date_range)`: Booking rate from traffic
- `generateReport(report_id)`: Run report and email
- `recordSnapshot(tenant_id, metric_type, value, date)`: Log metric
- `getTrends(tenant_id, metric, days)`: Trend data for charts

### New Controllers

**Admin/Perka/AnalyticsController.php**:
- `dashboard()`: Main analytics view
- `revenue()`: Revenue breakdown
- `bookings()`: Booking trends
- `customers()`: Customer analytics
- `staffPerformance()`: Staff metrics
- `reports()`: Manage scheduled reports
- `export()`: CSV/PDF export

### APIs

**Admin**:
```
GET /admin/tenants/{tenant_id}/perka/analytics/metrics?type=revenue&from=...&to=...
→ {
  "total": "5000.00",
  "by_service": [{ service_name, revenue }, ...],
  "by_staff": [{ staff_name, revenue }, ...],
  "trend": [{ date, revenue }, ...]
}

GET /admin/tenants/{tenant_id}/perka/analytics/customers
→ {
  "new_this_period": 15,
  "returning": 42,
  "churn_rate": 0.05,
  "avg_lifetime_value": "350.00",
  "retention_cohorts": [...]
}
```

### Events Fired

```php
Event::dispatch('analytics.metric_recorded', { tenant_id, metric_type, value });
Event::dispatch('analytics.report_generated', { report_id });
Event::dispatch('analytics.anomaly_detected', { tenant_id, anomaly });
```

### UI Changes

**Admin Dashboard**:
- Key metrics cards (revenue, bookings, customers)
- Revenue trend chart
- Booking status breakdown (pie chart)
- Staff performance table
- "Top services" bar chart
- No-show rate indicator

**Analytics Page** (new):
- Filterable dashboard
- Multiple chart types
- Custom date ranges
- CSV/PDF export
- Scheduled reports

### Dependencies
- None (read-only, aggregate queries)
- Listens to: booking.created, booking.completed, payment events

---

## Event System

Perka uses an event-driven architecture for loose coupling.

### Core Events

```php
// App/Events/Event.php
interface Event {
    public function getName(): string;
    public function getData(): array;
}

// Listen and dispatch
Event::dispatch('booking.created', $data);
Event::listen('booking.created', LoyaltyService::class);
```

### Event Registry

**Booking Events**:
- `booking.created`: {booking_id, customer_id, amount, service_id}
- `booking.confirmed`: {booking_id}
- `booking.cancelled`: {booking_id, reason}
- `booking.completed`: {booking_id, rating}
- `booking.no_show`: {booking_id}
- `booking.rescheduled`: {old_booking_id, new_booking_id}

**Payment Events**:
- `payment.succeeded`: {booking_id, amount, payment_id}
- `payment.failed`: {booking_id, reason}
- `payment.refunded`: {booking_id, amount}

**Loyalty Events**:
- `loyalty.points_earned`: {customer_id, points}
- `loyalty.tier_changed`: {customer_id, old_tier, new_tier}

**Review Events**:
- `review.submitted`: {review_id, rating}
- `review.approved`: {review_id}

**Subscription Events**:
- `subscription.started`: {subscription_id}
- `subscription.renewed`: {subscription_id}
- `subscription.cancelled`: {subscription_id}

### Event Listeners

Each Perka service registers listeners:

```php
// LoyaltyService
class LoyaltyService implements EventListener {
    public function handle(Event $event) {
        if ($event->getName() === 'booking.created') {
            $this->earnPoints(...);
        }
    }
}
```

---

## Cross-Module Dependencies

### Dependency Graph

```
VoxelBooking Core (immutable)
  ├─ Loyalty Engine
  │  └─ Rewards Engine
  │     └─ Membership
  │        └─ Prepaid Passes
  │           └─ Gift Cards
  │              └─ Wallet
  │                 └─ Payment Processing (Stripe)
  ├─ Marketplace
  │  └─ Wallet
  ├─ WhatsApp Integration
  │  └─ Booking Service (event listeners)
  ├─ AI Assistant
  │  └─ Booking Service (tool callers)
  ├─ Reviews
  │  └─ Booking Service (event listeners)
  ├─ Public Profiles
  │  └─ Reviews
  ├─ Multi-location
  │  └─ Services, Staff, Availability
  └─ Analytics
     └─ All modules (read-only)
```

### Feature Flag Checks

Every module checks if enabled before initializing:

```php
if (FeatureFlags::isEnabled($tenantId, 'loyalty')) {
    // Register LoyaltyService listeners
}
```

---

## Database Migration Strategy

### Perka Migrations Folder

All Perka migrations in separate namespace to avoid conflicts:
```
app/Perka/Migrations/
  ├── 001_create_loyalty_accounts.php
  ├── 002_create_loyalty_transactions.php
  ├── 003_create_loyalty_rewards.php
  ├── ...
  └── 100_create_analytics_snapshots.php
```

### Schema Versioning

VoxelBooking: `settings.db_version` = 28 (core migrations)
Perka: `settings.perka_db_version` = 100 (Perka migrations)

### Safe Updates

Both migrate independently:
1. VoxelBooking updates pull cleanly (no Perka tables touched)
2. Perka migrations run separately in Perka/Migrations folder
3. No conflicts since Perka uses prefix `perka_` on all tables

---

## Feature Flags & Tenant Settings

### Feature Flags Per Tenant

```sql
ALTER TABLE tenants ADD COLUMN perka_features JSON DEFAULT '{}';

-- Example: { "loyalty": 1, "marketplace": 0, "whatsapp": 1, ... }
```

### FeatureFlags Service

```php
class FeatureFlags {
    public static function isEnabled(string $tenantId, string $feature): bool {
        $tenant = Tenant::find($tenantId);
        return $tenant['perka_features'][$feature] ?? false;
    }
}
```

### Enable Feature via Admin

```php
// Admin/Perka/FeaturesController.php
POST /admin/tenants/{tenant_id}/perka/features/enable/{feature}
→ { enabled: true }
```

---

## API Organization

### Routing Structure

**Public APIs** (under `/api/{slug}/perka/`):
```
GET  /api/{slug}/perka/loyalty/account
GET  /api/{slug}/perka/rewards
GET  /api/{slug}/perka/memberships
GET  /api/{slug}/perka/pass-packages
POST /api/{slug}/perka/bookings/checkout
POST /api/{slug}/perka/reviews
GET  /api/{slug}/perka/marketplace/listings
POST /api/{slug}/perka/ai/chat
GET  /api/{slug}/perka/wallet
```

**Admin APIs** (under `/admin/tenants/{tenant_id}/perka/`):
```
GET  /admin/tenants/{tenant_id}/perka/loyalty/settings
POST /admin/tenants/{tenant_id}/perka/loyalty/settings
GET  /admin/tenants/{tenant_id}/perka/analytics/metrics
POST /admin/tenants/{tenant_id}/perka/memberships
```

### Webhook Endpoints

```
POST /perka/webhooks/stripe         → Payment updates
POST /perka/webhooks/twilio         → WhatsApp/SMS updates
POST /perka/webhooks/custom/{slug}  → Custom merchant webhooks
```

---

## Summary

This architecture:

✅ **Preserves VoxelBooking**: No modifications to core tables  
✅ **Modular**: Each feature independent, can enable/disable per tenant  
✅ **Loosely Coupled**: Event-driven, minimal direct dependencies  
✅ **Upstream Compatible**: Perka in separate namespace (`app/Perka/`)  
✅ **Scalable**: Database in `perka_*` prefix, migrations separate  
✅ **Extensible**: JSON meta fields, new services easy to add  
✅ **Secure**: Feature flags control access, payment PCI-compliant  

All 14 modules can be implemented independently following this design without breaking VoxelBooking compatibility.
