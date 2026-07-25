# VoxelBooking → Perka: SaaS Evolution Roadmap

## Executive Summary

This document outlines how VoxelBooking can evolve from a booking infrastructure into **Perka**, a comprehensive SaaS platform combining booking, loyalty, memberships, prepaid passes, AI assistance, WhatsApp automation, subscriptions, and a peer-to-peer marketplace.

**Target**: A unified platform where small businesses can manage not just bookings, but entire customer relationships and revenue streams.

---

## Current State (VoxelBooking)

### What VoxelBooking Does Well

✅ **Four booking patterns** unified in one codebase (timeslot, resource, capacity, event)  
✅ **Multi-tenant architecture** with complete data isolation  
✅ **GDPR-compliant** (consent capture, anonymization, data export)  
✅ **No vendor lock-in** (self-hosted, open source)  
✅ **Minimal infrastructure** (PHP + MySQL, no Node.js, no Redis)  
✅ **Extensible** (JSON meta fields, cleanly separated patterns)  
✅ **Production-ready** (tested, auditable, secure)  

### Current Limitations

❌ No customer loyalty/rewards system  
❌ No recurring subscriptions  
❌ No prepaid passes or credit system  
❌ No marketplace for booking resale  
❌ No AI-powered booking assistant  
❌ No WhatsApp/SMS messaging  
❌ Limited payment integration  
❌ Manual business workflows only  

---

## Phase 1: Loyalty & Rewards (Months 1-3)

### Goal
Enable businesses to reward repeat customers with points, tiers, and benefits.

### Database Changes

New tables:
```sql
-- Customer loyalty status
CREATE TABLE loyalty_accounts (
    id CHAR(26) PRIMARY KEY,
    customer_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    points_balance INT NOT NULL DEFAULT 0,
    lifetime_points INT NOT NULL DEFAULT 0,
    tier VARCHAR(50) NOT NULL DEFAULT 'bronze',  -- bronze|silver|gold|platinum
    tier_started_at DATETIME,
    meta JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY loyalty_accounts_customer_tenant (customer_id, tenant_id),
    KEY loyalty_accounts_tenant_tier_idx (tenant_id, tier),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Points earn/burn transactions
CREATE TABLE loyalty_transactions (
    id CHAR(26) PRIMARY KEY,
    loyalty_account_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    booking_id CHAR(26),  -- Points earned from booking
    transaction_type VARCHAR(50),  -- earn|redeem|bonus|adjustment
    points_delta INT NOT NULL,  -- +100 or -50
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY loyalty_transactions_account_idx (loyalty_account_id),
    KEY loyalty_transactions_booking_idx (booking_id),
    FOREIGN KEY (loyalty_account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);

-- Tier benefits (configurable by business)
CREATE TABLE loyalty_tiers (
    id CHAR(26) PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    tier_name VARCHAR(50) NOT NULL,  -- bronze|silver|gold
    min_points INT NOT NULL,  -- Points needed to reach
    discount_percent DECIMAL(5, 2),  -- Automatic discount
    points_multiplier DECIMAL(3, 2) DEFAULT 1.0,  -- 1.5x points for gold
    benefits JSON,  -- [{ name, description }, ...]
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY loyalty_tiers_tenant_name (tenant_id, tier_name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Redeem rewards
CREATE TABLE loyalty_rewards (
    id CHAR(26) PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,  -- "Free haircut", "20% off"
    description TEXT,
    points_cost INT NOT NULL,  -- 500 points = reward
    discount_amount DECIMAL(10, 2),  -- OR fixed amount discount
    discount_percent DECIMAL(5, 2),  -- OR percentage discount
    max_redemptions INT,  -- NULL = unlimited
    redeemed_count INT DEFAULT 0,
    expires_at DATETIME,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY loyalty_rewards_tenant_active_idx (tenant_id, is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Redemption records
CREATE TABLE loyalty_redemptions (
    id CHAR(26) PRIMARY KEY,
    loyalty_account_id CHAR(26) NOT NULL,
    reward_id CHAR(26) NOT NULL,
    booking_id CHAR(26),  -- Applied to this booking
    points_redeemed INT NOT NULL,
    discount_applied DECIMAL(10, 2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY loyalty_redemptions_account_idx (loyalty_account_id),
    FOREIGN KEY (loyalty_account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES loyalty_rewards(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);
```

### Features

**For Businesses**:
- Configure points earning rules (1 point per €, per booking, per service)
- Set tier thresholds and benefits
- Create custom rewards (discounts, free services)
- View customer loyalty status in admin dashboard
- Send "you're close to silver!" motivational emails

**For Customers**:
- See loyalty balance and tier on booking page
- Automatic points credit after booking confirmation
- Redeem points at checkout (apply discount code)
- Receive tier-up notifications
- Track lifetime spend and points

### Implementation

1. **Engine/LoyaltyService.php**: Core logic
   - `earnPoints()`: Add points after booking
   - `redeemReward()`: Apply discount
   - `calculateTier()`: Determine tier from lifetime_points
   - `applyMultiplier()`: 1.5x points for gold members

2. **Admin\LoyaltyController.php**: Business configuration
   - Configure tier thresholds
   - Create rewards
   - View redemption history

3. **Booking/LoyaltyApi.php**: Public API
   - `GET /api/{slug}/loyalty/balance`: Current points/tier
   - `POST /api/{slug}/bookings?reward_id=...`: Redeem during booking

4. **Frontend**: Display loyalty UI
   - Points badge on booking page
   - Reward redemption selector
   - Tier progress bar

### Revenue Impact

- **Upsell**: $50-200/month per tenant for loyalty feature
- **Increased retention**: 20-30% repeat booking increase
- **Higher AOV**: Customers book higher-tier services to earn more

---

## Phase 2: Prepaid Passes & Packages (Months 4-6)

### Goal
Let customers buy bundles (10 haircuts, 20 classes, 100 spa credits).

### Database Changes

```sql
CREATE TABLE prepaid_packages (
    id CHAR(26) PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255),  -- "10-Pack Haircuts", "Monthly Yoga"
    description TEXT,
    service_id CHAR(26),  -- NULL = any service
    credits INT NOT NULL,  -- 10 haircuts
    validity_days INT,  -- Expires after 90 days (NULL = no expiry)
    price DECIMAL(10, 2) NOT NULL,
    discount_percent DECIMAL(5, 2),  -- 10% bulk discount
    per_booking_limit INT,  -- Max credits per booking
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY prepaid_packages_tenant_idx (tenant_id, is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);

CREATE TABLE prepaid_purchases (
    id CHAR(26) PRIMARY KEY,
    customer_id CHAR(26) NOT NULL,
    package_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    credits_purchased INT NOT NULL,
    credits_remaining INT NOT NULL,
    price_paid DECIMAL(10, 2),
    purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    
    KEY prepaid_purchases_customer_idx (customer_id),
    KEY prepaid_purchases_tenant_idx (tenant_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES prepaid_packages(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE prepaid_credits_used (
    id CHAR(26) PRIMARY KEY,
    purchase_id CHAR(26) NOT NULL,
    booking_id CHAR(26) NOT NULL,
    credits_used INT NOT NULL,
    used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY prepaid_credits_booking_idx (booking_id),
    FOREIGN KEY (purchase_id) REFERENCES prepaid_purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);
```

### Features

**For Businesses**:
- Create package tiers with bulk discounts
- Set validity period (e.g., expires after 90 days)
- Combine with loyalty (10% discount for silver tier)
- View package sales revenue
- Send "your package expires in 7 days" reminders

**For Customers**:
- Browse and buy packages on booking page
- Apply credits automatically at checkout
- See remaining credits
- Receive expiry warnings

### Implementation

1. **Engine/PrepaidService.php**: Credit management
   - `purchasePackage()`: Create prepaid_purchases record
   - `useCredits()`: Debit from purchase, add to used log
   - `getAvailableCredits()`: Sum remaining across all packages
   - `expireCredits()`: Scheduled job to clear expired packages

2. **Booking/PrepaidApi.php**: Public API
   - `GET /api/{slug}/prepaid/packages`: List available packages
   - `GET /api/{slug}/prepaid/balance`: Customer's remaining credits
   - `POST /api/{slug}/prepaid/purchase`: Buy package (payment required)

3. **Payment Integration** (Phase 3): Charge card before creating prepaid_purchases

### Revenue Impact

- **Upsell**: $100-500/month per tenant
- **Upfront revenue**: Customers prepay (cash flow improvement)
- **Higher LTV**: Package buyers book 3-5x more

---

## Phase 3: Payment Processing (Months 7-9)

### Goal
Collect payments directly in VoxelBooking for bookings, prepaid packages, and loyalty redemptions.

### Architecture

**Payment Gateway Integration**:
- Stripe (recommended): Credit card, bank account, Apple Pay, Google Pay
- Fallback: Mollie (EU-friendly), Square
- Future: Wise, Plaid for local payments

### Database Changes

```sql
CREATE TABLE payment_methods (
    id CHAR(26) PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    gateway VARCHAR(50),  -- stripe|mollie|square
    gateway_account_id VARCHAR(255),  -- Stripe account ID
    api_key_test VARCHAR(500),
    api_key_live VARCHAR(500),
    webhook_secret VARCHAR(500),
    is_live TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY payment_methods_tenant_gateway (tenant_id, gateway),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE payment_transactions (
    id CHAR(26) PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    customer_id CHAR(26),
    booking_id CHAR(26),
    prepaid_purchase_id CHAR(26),
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    status VARCHAR(50),  -- pending|succeeded|failed|refunded
    gateway VARCHAR(50),
    gateway_transaction_id VARCHAR(255),
    payment_method VARCHAR(50),  -- card|bank_account|apple_pay
    receipt_url TEXT,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY payment_transactions_tenant_idx (tenant_id),
    KEY payment_transactions_customer_idx (customer_id),
    KEY payment_transactions_booking_idx (booking_id),
    KEY payment_transactions_status_idx (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (prepaid_purchase_id) REFERENCES prepaid_purchases(id) ON DELETE CASCADE
);
```

### Features

**For Businesses**:
- Connect Stripe account (OAuth in admin)
- Toggle payment required per service/resource
- View payment dashboard (sales, fees, payouts)
- Refund bookings directly
- Automatic remittance (Stripe handles payouts)

**For Customers**:
- Pay at booking (required or optional)
- Save card for future bookings
- Automatic receipt email
- Dispute via gateway

### Implementation

1. **Engine/PaymentService.php**:
   - `initializePayment()`: Create payment transaction, get payment link
   - `handleWebhook()`: Process Stripe/Mollie webhooks
   - `refund()`: Reverse charge
   - `syncPayouts()`: Pull payout history

2. **Admin\PaymentController.php**:
   - Connect gateway account
   - View sales dashboard
   - Manual refunds

3. **Booking/PaymentApi.php**:
   - `POST /api/{slug}/checkout`: Initiate payment, return redirect URL
   - `GET /api/{slug}/checkout/status/{session_id}`: Poll for payment status

4. **Webhook Handlers**:
   - Listen for payment.succeeded, payment.failed
   - Update booking status to 'confirmed' on success
   - Send receipt email

### Revenue Impact

- **Commission**: 3-5% per transaction (or flat fee)
- **Volume**: Average $50-500 per booking
- **SaaS revenue**: $200-1000/month per tenant

---

## Phase 4: Subscriptions & Memberships (Months 10-12)

### Goal
Recurring billing for unlimited bookings, memberships, and subscriptions.

### Database Changes

```sql
CREATE TABLE memberships (
    id CHAR(26) PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255),  -- "Unlimited Monthly", "Annual Premium"
    description TEXT,
    interval VARCHAR(50),  -- monthly|quarterly|annual
    price DECIMAL(10, 2),
    booking_limit INT,  -- NULL = unlimited
    services_included JSON,  -- [service_id, ...] or null = all
    benefits JSON,  -- Priority booking, free cancellation, etc.
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY memberships_tenant_active_idx (tenant_id, is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE customer_subscriptions (
    id CHAR(26) PRIMARY KEY,
    customer_id CHAR(26) NOT NULL,
    membership_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    status VARCHAR(50),  -- active|paused|cancelled
    current_period_start DATETIME,
    current_period_end DATETIME,
    gateway_subscription_id VARCHAR(255),  -- Stripe subscription ID
    bookings_this_period INT DEFAULT 0,
    cancelled_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY customer_subscriptions_customer_idx (customer_id),
    KEY customer_subscriptions_tenant_idx (tenant_id),
    KEY customer_subscriptions_status_idx (status),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (membership_id) REFERENCES memberships(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE subscription_invoices (
    id CHAR(26) PRIMARY KEY,
    subscription_id CHAR(26) NOT NULL,
    amount DECIMAL(10, 2),
    status VARCHAR(50),  -- paid|failed|upcoming
    due_date DATETIME,
    paid_at DATETIME,
    gateway_invoice_id VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY subscription_invoices_subscription_idx (subscription_id),
    FOREIGN KEY (subscription_id) REFERENCES customer_subscriptions(id) ON DELETE CASCADE
);
```

### Features

**For Businesses**:
- Create membership tiers (Monthly, Annual, Premium)
- Unlimited bookings or per-tier limits
- Include specific services or all
- View MRR (Monthly Recurring Revenue)
- Dunning: auto-retry failed payments

**For Customers**:
- Subscribe to membership (auto-renews)
- Pause subscription (freeze during travel)
- Cancel with notice period
- See booking usage within period

### Implementation

1. **Engine/SubscriptionService.php**:
   - `subscribe()`: Create customer_subscriptions, call Stripe
   - `renewSubscription()`: Charge on due date
   - `cancelSubscription()`: Set status, handle refund
   - `checkBookingLimit()`: Enforce per-tier limits

2. **Admin\SubscriptionsController.php**:
   - Create/edit memberships
   - View MRR, churn, LTV metrics
   - Manual prorations

3. **Booking/SubscriptionApi.php**:
   - `GET /api/{slug}/memberships`: List available
   - `POST /api/{slug}/memberships/{id}/subscribe`: Subscribe
   - `GET /api/{slug}/subscription/status`: Current status

4. **Scheduler/SubscriptionRenewalJob.php**:
   - Run daily: check subscriptions due for renewal
   - Call PaymentService to charge
   - Update current_period_end
   - Handle failures (retry logic)

### Revenue Impact

- **High LTV**: Monthly recurring revenue
- **Predictability**: Can forecast revenue
- **Retention**: Better than one-off bookings

---

## Phase 5: AI Booking Assistant (Months 13-15)

### Goal
Conversational AI (Claude, ChatGPT) that handles booking questions and creates bookings.

### Architecture

**Two Modes**:

1. **Live Chat Widget**: Customer chats with AI on booking page
   - "When are you open?" → retrieves availability
   - "Book me for a haircut at 2pm" → creates booking
   - "Can I add a friend?" → checks capacity

2. **SMS/WhatsApp Agent**: Separate service sending/receiving messages
   - Customer texts: "Book me for massage Monday"
   - System: "I found 3 options. Pick time?"
   - Customer replies with selection → booking confirmed

### Implementation

**API Integration**:
- Anthropic Claude API (Perka's backend)
- Twilio SMS/WhatsApp (for SMS mode)
- OpenAI GPT-4 (fallback)

```php
// Engine/AiBookingAgent.php
class AiBookingAgent {
    public function processMessage(string $message, array $tenant, array $customer): array {
        // 1. Parse intent ("list_availability", "create_booking", "cancel_booking")
        // 2. Extract entities (date, time, service_id, party_size)
        // 3. Call appropriate service method
        // 4. Generate response
        // 5. Send back to customer
    }
}
```

**Available Functions** (exposed to AI):

```json
[
  {
    "name": "get_available_slots",
    "description": "Get available time slots for a date",
    "parameters": {
      "type": "object",
      "properties": {
        "date": {"type": "string"},
        "service_id": {"type": "string"}
      }
    }
  },
  {
    "name": "create_booking",
    "description": "Create a new booking",
    "parameters": {
      "type": "object",
      "properties": {
        "service_id": {"type": "string"},
        "start_datetime": {"type": "string"},
        "name": {"type": "string"},
        "email": {"type": "string"},
        "party_size": {"type": "integer"}
      }
    }
  },
  {
    "name": "check_account_balance",
    "description": "Get customer's loyalty points, prepaid credits, subscription status"
  },
  {
    "name": "cancel_booking",
    "description": "Cancel a confirmed booking"
  }
]
```

### Features

- **Natural language**: "Next Tuesday afternoon" → parse to date + time range
- **Multi-step conversation**: Clarify preferences if ambiguous
- **Upsell**: "You're a gold member! That service has a 15% discount"
- **Business hours**: "We're closed Monday, but open Tuesday 10am-6pm"
- **Follow-ups**: "Your booking is confirmed. Reply CONFIRM to lock in"

### Revenue Impact

- **Premium add-on**: $50-200/month per tenant
- **Conversion lift**: 30-40% higher booking rate (instant booking)
- **Support reduction**: Fewer email inquiries

---

## Phase 6: WhatsApp Automation (Months 16-18)

### Goal
Send booking confirmations, reminders, and upsell via WhatsApp (vs. email).

### Database Changes

```sql
CREATE TABLE whatsapp_integrations (
    id CHAR(26) PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    twilio_account_sid VARCHAR(255),
    twilio_auth_token VARCHAR(255),
    whatsapp_phone_number VARCHAR(50),  -- +1234567890
    webhook_url VARCHAR(500),
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY whatsapp_integrations_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE whatsapp_messages (
    id CHAR(26) PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    customer_id CHAR(26),
    booking_id CHAR(26),
    direction VARCHAR(50),  -- inbound|outbound
    message_type VARCHAR(50),  -- confirmation|reminder|upsell|customer_inquiry
    body TEXT,
    template_name VARCHAR(255),  -- whatsapp_confirmation_es, etc.
    status VARCHAR(50),  -- sent|delivered|read|failed
    gateway_message_id VARCHAR(255),  -- Twilio message SID
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY whatsapp_messages_tenant_idx (tenant_id),
    KEY whatsapp_messages_customer_idx (customer_id),
    KEY whatsapp_messages_status_idx (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);
```

### Features

**For Businesses**:
- Enable WhatsApp in tenant settings (link Twilio)
- Configure message templates (booking confirmation, 24h reminder)
- Replace email notifications with WhatsApp
- View delivery rates + read receipts

**For Customers**:
- Receive confirmation immediately (vs. email delays)
- Click-to-reschedule link in WhatsApp
- Auto-reply: "React 👍 to confirm your 2pm booking"
- Upsell: "Add a massage to your haircut? 20% off"

### Implementation

1. **Engine/WhatsappService.php**:
   - `send()`: Call Twilio API
   - `handleWebhook()`: Process delivery/read receipts
   - `sendReminder()`: Scheduled job using WhatsApp

2. **Mailer.php**: Extend to support WhatsApp
   - `shouldUseWhatsapp()`: Check tenant + customer preference
   - Fallback to email if WhatsApp fails

3. **Admin\WhatsappController.php**:
   - Link/unlink Twilio account
   - Configure templates per language
   - View message delivery dashboard

### Revenue Impact

- **Premium add-on**: $20-50/month per tenant
- **Better engagement**: 70% read rate (vs. 30% email)
- **Higher conversion**: Immediate reminders prevent no-shows

---

## Phase 7: Marketplace (Months 19-21)

### Goal
Let customers resell bookings; let businesses list services on a peer marketplace.

### Concept

**B2C Resale**:
- Customer buys 10-pack haircuts, only uses 5
- Lists remaining 5 on Perka marketplace
- Another customer buys at same or marked-up price
- Seller gets credit, Perka takes 10% commission

**B2B Marketplace**:
- Business A (salon) offers makeup services in their booking
- Customer can book makeup → salon books makeup artist (Business B)
- Revenue split: Customer pays $100 → Salon $70 + Artist $30 (configurable)

### Database Changes

```sql
CREATE TABLE marketplace_listings (
    id CHAR(26) PRIMARY KEY,
    seller_id CHAR(26),  -- customer or tenant
    seller_type VARCHAR(50),  -- customer|business
    tenant_id CHAR(26) NOT NULL,  -- Original business
    prepaid_purchase_id CHAR(26),  -- Credits being resold
    service_id CHAR(26),  -- Service available
    credits_available INT,
    price_per_credit DECIMAL(10, 2),  -- Markup pricing
    expiry_date DATE,
    status VARCHAR(50),  -- active|sold_out|expired|cancelled
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY marketplace_listings_tenant_idx (tenant_id),
    KEY marketplace_listings_status_idx (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

CREATE TABLE marketplace_transactions (
    id CHAR(26) PRIMARY KEY,
    listing_id CHAR(26) NOT NULL,
    buyer_id CHAR(26) NOT NULL,
    seller_id CHAR(26) NOT NULL,
    credits_purchased INT,
    price_total DECIMAL(10, 2),
    platform_fee DECIMAL(10, 2),  -- 10%
    seller_payout DECIMAL(10, 2),  -- 90%
    status VARCHAR(50),  -- pending|completed|refunded
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY marketplace_transactions_buyer_idx (buyer_id),
    KEY marketplace_transactions_seller_idx (seller_id),
    FOREIGN KEY (listing_id) REFERENCES marketplace_listings(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES customers(id) ON DELETE CASCADE
);
```

### Features

**For Customers**:
- List unused credits on marketplace
- Browse listings from same business or other locations
- Buy second-hand credits at discount
- Send marketplace message to seller

**For Businesses**:
- Allow/disallow resale (tenant setting)
- List services from other businesses
- Set commission split for partner services
- View marketplace earnings

### Implementation

1. **Engine/MarketplaceService.php**:
   - `createListing()`: Put credit on market
   - `purchaseListing()`: Buy and transfer credit
   - `payoutToSeller()`: Pay seller's balance

2. **Admin\MarketplaceController.php**:
   - Configure resale policy
   - View marketplace earnings
   - Dispute resolution

3. **Booking/MarketplaceApi.php**:
   - `GET /api/{slug}/marketplace?service_id=...`: Search listings
   - `POST /api/{slug}/marketplace/{id}/purchase`: Buy listing

4. **Payments**:
   - Buyer pays Perka (via Stripe)
   - Perka pays seller (weekly via ACH)

### Revenue Impact

- **Commission**: 10% on B2C resales ($1-5 per transaction)
- **GMV**: Could reach 30-50% of base booking volume

---

## Phase 8: Integration Hub (Months 22-24)

### Goal
Zapier/Make integrations, third-party app marketplace.

### Integrations

**Built-in**:
- Google Calendar sync (two-way)
- Slack notifications (booking alerts)
- Microsoft Teams
- Shopify (for product sales)
- QuickBooks (accounting sync)
- Xano/Retool (no-code extensions)

**First-Party Marketplace Apps**:
- Videocall integration (Zoom, Google Meet)
- SMS campaigns (Twilio)
- Survey tools (Typeform)
- CRM (HubSpot, Pipedrive)

**API Webhooks**:
```json
{
  "event": "booking.created",
  "data": { booking object },
  "timestamp": "2024-12-15T10:00:00Z",
  "signature": "hmac-sha256=..."
}
```

### Implementation

1. **Integration SDK**: Publish Python/Node.js SDK
2. **App Store**: UI to browse, install, authenticate apps
3. **Webhook Manager**: Manage subscriptions and retries
4. **OAuth**: Allow apps to request scopes (read_bookings, write_customers)

### Revenue Impact

- **Marketplace take**: 20-30% on app subscriptions
- **High LTV**: Integrations increase stickiness

---

## Phase 9: Analytics & Intelligence (Months 25-27)

### Goal
Business intelligence dashboards for small business owners.

### Features

**Dashboards**:
- Revenue by service, staff, customer segment
- Booking rate trends (daily/weekly/monthly)
- No-show rate by time-of-day
- Customer lifetime value distribution
- Churn rate by cohort

**Automated Insights**:
- "Your weekend bookings are down 20% YoY"
- "Customers aged 25-35 have 3x loyalty retention"
- "Recommend: raise prices by 10% on peak hours"

**Reporting**:
- Export reports to PDF
- Scheduled email reports
- Share dashboard links with team

### Implementation

1. **Engine/Analytics.php**:
   - Query aggregations
   - Trend calculations
   - Anomaly detection (ML)

2. **Admin\AnalyticsController.php**:
   - Render dashboard UI
   - Export reports

### Revenue Impact

- **Premium add-on**: $30-100/month
- **Upsell**: Enterprise customers willing to pay for intelligence

---

## Phase 10: Expansion & Scaling (Months 28+)

### Additional Revenue Streams

1. **White Label**: Resellers can rebrand Perka ($500-2000/month)
2. **Enterprise**: Custom SLAs, dedicated support ($1000+/month)
3. **Marketplace of Businesses**: Directory of businesses (Yelp-like)
4. **Lending**: Advance future revenue for businesses (Stripe Capital model)
5. **Insurance**: Booking protection for customers
6. **Certification**: Training courses for business owners ($50-500)

### Geographic Expansion

- **Phase 1**: Europe (English + 3 languages)
- **Phase 2**: Latin America
- **Phase 3**: Southeast Asia
- **Phase 4**: MENA

### Technical Debt Resolution

- Migrate to Laravel for faster development (optional)
- Kubernetes deployment (optional)
- Real-time collaboration (WebSockets)
- Mobile-first redesign
- GraphQL API

---

## Pricing Strategy (Perka SaaS)

### Base Tier: $29/month
- VoxelBooking core (4 patterns)
- Up to 100 bookings/month
- Email notifications
- 1 user (staff seat)

### Professional: $79/month
- Everything in Base
- Up to 1000 bookings/month
- + Loyalty
- + Prepaid packages
- + Payment processing (3% fee + $0.30)
- 3 staff seats
- Priority support

### Enterprise: $199/month
- Everything in Professional
- Unlimited bookings
- + Subscriptions
- + AI booking assistant
- + WhatsApp automation
- + Marketplace
- + Analytics
- 10 staff seats
- Dedicated success manager
- Custom integrations

### Platform Commission

In addition to SaaS subscription:
- Payment processing: 3% + $0.30 per transaction
- Marketplace: 10% commission
- App marketplace: 20-30% revenue share

### Annual Discount: -20%

---

## Implementation Timeline

```
Q1 (Phase 1): Loyalty & Rewards
Q2 (Phase 2): Prepaid Passes
Q3 (Phase 3): Payment Processing
Q4 (Phase 4): Subscriptions
Q1 (Phase 5): AI Assistant
Q2 (Phase 6): WhatsApp
Q3 (Phase 7): Marketplace
Q4 (Phase 8): Integrations
Q1 (Phase 9): Analytics
Q2+ (Phase 10): Scaling
```

**Total**: 24-30 months to full Perka platform

---

## Success Metrics

### Usage Metrics
- Active businesses using feature
- Bookings per tenant per month
- Revenue per feature

### Retention Metrics
- Monthly churn rate (target: < 5%)
- Net revenue retention (target: > 110%)

### Growth Metrics
- NRR: New revenue from loyalty/subscriptions
- LTV: Lifetime value per customer
- CAC: Customer acquisition cost

### Market Position
- Market share in vertical (target: 5-10%)
- NPS: Net promoter score (target: > 50)
- G2 rating (target: > 4.5/5)

---

## Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Payment fraud | 3D Secure, fraud detection, Stripe Radar |
| Data privacy (GDPR) | Maintain existing compliance, audit quarterly |
| Marketplace abuse | Manual review, user reputation, dispute resolution |
| AI hallucinations | Fine-tuning on booking data, human escalation for errors |
| Competitive pressure | First-mover advantage, deep vertical focus, open source community |

---

## Open Questions for Stakeholder Discussion

1. **Build vs. Partner**: Develop all features in-house or use third-party APIs (Stripe Connect, Twilio, etc.)?
2. **Self-Hosted vs. SaaS**: Continue self-hosted option or move to managed SaaS only?
3. **Vertical Expansion**: Focus on salons/spas first, or broaden to fitness/consulting?
4. **Pricing**: Subscription-only or include commission on transactions?
5. **Acquisition**: Organic growth, partnerships, or paid acquisition?

---

## Appendix: Tech Stack Evolution

### Phase 1-4 (Booking → Payments)
- Same as VoxelBooking
- Add: Stripe SDK, Mollie SDK
- Database: Add 15 new tables
- Frontend: Minor updates to checkout flow

### Phase 5+ (AI & Integrations)
- Add: Anthropic Claude SDK, Twilio SDK
- Messaging queue: (optional) Redis or Beanstalkd
- Real-time: WebSockets for AI chat (optional)
- Analytics: BigQuery or Clickhouse

### Infrastructure
- Self-hosted: Single PHP server, MySQL
- SaaS: Docker containers, Kubernetes, managed RDS
- CDN: Cloudflare for assets
- Email: SendGrid or AWS SES

---

## Conclusion

VoxelBooking has built a solid foundation for small business booking infrastructure. The evolution into Perka represents a $10-100M+ opportunity by bundling booking with loyalty, payments, subscriptions, AI, and marketplace services.

The phased approach (Phases 1-10) balances feature velocity with stability, allowing businesses to adopt features incrementally while Perka builds out the platform over 24+ months.

**Next Step**: Stakeholder alignment on go-to-market strategy and resource allocation.
