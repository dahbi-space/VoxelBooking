# Epic 1: Public Business Profiles - Implementation Plan

## Overview

Public Business Profiles is the first Perka module to implement. It enables business owners to showcase their services, staff, and reviews on a SEO-optimized public profile page accessible at `/profiles/{slug}`.

**Status**: Design Phase (No Code Yet)  
**Estimated Scope**: 8-9 days  
**Dependencies**: Reviews module (for showing ratings)  
**Future Dependencies**: Marketplace, Loyalty, Analytics

---

## Architecture Summary: Minimal Core Coupling

**Philosophy**: Everything lives in `app/Perka/`. The booking engine is NEVER modified.

**Core Changes**: Exactly **3 one-line includes**:
1. `app/routes.php` → `app/Perka/Routes.php` (if exists)
2. `app/bootstrap.php` → `app/Perka/Bootstrap.php` (if exists)
3. `templates/admin/layout.php` → `app/Perka/menu.php` (if exists)

**Everything Else**: Lives in `app/Perka/`:
- Controllers
- Services
- Models
- Templates/views
- Route definitions (called from include)
- Bootstrap logic (service initialization)
- Admin menu items

**Migrations**: Perka migrations do NOT live in core `app/Migrations/` and do NOT share the core `settings.db_version` watermark. That single integer watermark can cause a future upstream core migration (numbered below Perka's band) to be silently skipped on update — breaking both the "easy upstream merge" goal and clean removability.

Instead, each module ships its own migrations under `app/Perka/Modules/{Module}/Migrations/NNN_*.php`, run by a minimal Perka-owned runner (`App\Perka\Shared\PerkaMigrator`). The runner reuses the **core migration file format** (each file returns an array of SQL statements) but records applied migrations as an **applied-list** in a dedicated `perka_migrations` table (one row per migration, keyed by `Module/filename`), never a counter. It never reads or writes `settings.db_version`. `perka_migrations` has no foreign keys, so it never blocks core tenant deletion; deleting `app/Perka/` removes the runner and its registry entirely.

**Result**: 
- ✓ Zero modifications to booking engine
- ✓ Zero modifications to existing tables (except 1 new column for feature flags)
- ✓ Perka can be deleted/disabled by removing `app/Perka/` folder
- ✓ Future VoxelBooking updates merge cleanly (includes are at end of files)
- ✓ Each Perka module (Profiles, Loyalty, Marketplace, etc.) follows same pattern

---

## 1. Business Requirements

### Core Goals
1. **Public Discoverability**: Each business gets a SEO-optimized profile URL (`/profiles/{slug}`)
2. **Brand Showcase**: Display business name, bio, featured services, staff, reviews, and social links
3. **Admin Control**: Business owners can customize and publish their profile
4. **Analytics Ready**: Track profile views and referrers for future reporting
5. **No API Breaking**: Preserve VoxelBooking core, additive-only design

### Key Features
- Customizable business bio and featured image
- Highlight specific services and staff members
- Display aggregate review ratings and recent reviews
- Social media links (Instagram, Facebook, Twitter, LinkedIn)
- SEO meta tags (title, description, keywords, og:image)
- Optional: Show next available booking slots
- Optional: Custom CSS for white-label customization
- Privacy: Publishable/unpublishable

### Out of Scope (Future)
- Booking directly from profile (links to /book/{slug})
- Location-specific profiles (Multi-location epic)
- Loyalty/rewards info on profile (Loyalty epic)
- Marketplace resale info (Marketplace epic)
- Advanced analytics dashboard (Analytics epic)

---

## 2. User Stories

### Business Owner Perspective
```
As a business owner,
I want to create and customize my public profile,
So that potential customers can find and learn about my business.

Acceptance Criteria:
- Profile URL is stable and SEO-optimized
- I can add bio, image, featured services, staff
- I can set social media links
- I can preview before publishing
- I can control visibility (publish/unpublish)
- My profile shows aggregate review rating (if reviews enabled)
```

### Customer Perspective
```
As a potential customer,
I want to view the business's public profile,
So that I can learn about the services and staff before booking.

Acceptance Criteria:
- Profile loads quickly
- Services grid displays with descriptions and staff
- Review ratings are visible
- "Book Now" link leads to /book/{slug}
- Social links work correctly
```

### Search Engine Perspective
```
As a search engine,
I want to crawl the business profile with rich metadata,
So that I can index and rank it appropriately.

Acceptance Criteria:
- Meta tags present (og:title, og:description, og:image)
- Structured data (Schema.org/LocalBusiness)
- Canonical URL set
- Mobile-responsive design
- Fast page load
```

---

## 3. Database Changes

### New Tables

#### `perka_public_profiles`
```sql
CREATE TABLE perka_public_profiles (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL UNIQUE,
    slug VARCHAR(255) UNIQUE,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    bio TEXT,
    featured_image VARCHAR(500),
    service_highlights JSON,  -- [{service_id, position, order}, ...]
    staff_highlights JSON,    -- [{staff_id, position, order}, ...]
    social_links JSON,        -- {instagram, facebook, twitter, linkedin, website}
    meta_description VARCHAR(500),
    meta_keywords VARCHAR(255),
    custom_css TEXT,
    show_reviews TINYINT(1) DEFAULT 1,
    show_staff TINYINT(1) DEFAULT 1,
    show_next_available TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY perka_public_profiles_tenant (tenant_id),
    KEY perka_public_profiles_slug (slug),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

**Rationale**:
- `tenant_id` UNIQUE: One profile per business (multi-location handled later)
- `slug`: Custom URL-friendly identifier, SEO-critical
- `is_published`: Controls visibility without deleting
- `service_highlights` + `staff_highlights`: JSON arrays allow flexible ordering and selection without new tables
- `meta_*`: SEO customization
- `custom_css`: Limited CSS for white-label customization (sanitized on save)
- `show_*` flags: Feature toggles (Reviews, Staff visibility, availability display)

#### `perka_profile_views`
```sql
CREATE TABLE perka_profile_views (
    id CHAR(26) NOT NULL PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    profile_id CHAR(26) NOT NULL,
    view_date DATE NOT NULL,
    view_count INT DEFAULT 1,
    referrer VARCHAR(500),
    country_code VARCHAR(2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY perka_profile_views_tenant_date_idx (tenant_id, view_date),
    KEY perka_profile_views_profile_date_idx (profile_id, view_date),
    FOREIGN KEY (profile_id) REFERENCES perka_public_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

**Rationale**:
- Aggregated by `view_date` (not per-request) for performance
- `view_count` incremented on duplicate view (same IP/session same day)
- `referrer`: Track traffic sources (search, social, direct)
- `country_code`: Future geo-analytics (GeoIP lookup optional)
- Index on `(tenant_id, view_date)` for analytics queries

### Schema Changes to Existing Tables

**Modify `tenants` table** (if not already done):
```sql
ALTER TABLE tenants ADD COLUMN perka_features JSON DEFAULT '{}';
  -- Example: { "public_profiles": 1, "loyalty": 0, "marketplace": 1 }
```

**Note**: No modifications to `services`, `staff`, or `bookings` tables—design is purely additive.

---

## 4. New Routes

### Public Routes (No Auth)
```
GET  /profiles/{slug}              → PublicProfileController::show()
GET  /api/profiles/{slug}.json     → PublicProfileController::json()
```

### Admin Routes (Auth Required)
```
GET  /admin/tenants/{tenant_id}/perka/profile           → Admin\Perka\PublicProfileController::edit()
POST /admin/tenants/{tenant_id}/perka/profile           → Admin\Perka\PublicProfileController::save()
GET  /admin/tenants/{tenant_id}/perka/profile/preview   → Admin\Perka\PublicProfileController::preview()
POST /admin/tenants/{tenant_id}/perka/profile/generate-slug → Admin\Perka\PublicProfileController::generateSlug()
```

### Routing in app/Routes.php
```php
// Public profile routes
Router::get('/profiles/{slug}', 'PublicProfileController@show')
    ->middleware(['SecurityMiddleware', 'InstalledMiddleware', 'ThrottleMiddleware']);

Router::get('/api/profiles/{slug}.json', 'PublicProfileController@json')
    ->middleware(['SecurityMiddleware', 'InstalledMiddleware', 'ThrottleMiddleware']);

// Admin profile routes
Router::group(['prefix' => '/admin/tenants/{tenant_id}/perka'], ['middleware' => ['SecurityMiddleware', 'InstalledMiddleware', 'AuthMiddleware']], function() {
    Router::get('/profile', 'Admin\Perka\PublicProfileController@edit');
    Router::post('/profile', 'Admin\Perka\PublicProfileController@save');
    Router::get('/profile/preview', 'Admin\Perka\PublicProfileController@preview');
    Router::post('/profile/generate-slug', 'Admin\Perka\PublicProfileController@generateSlug');
});
```

---

## 5. Controllers

### `app/Controllers/PublicProfileController.php`

**Responsibilities**:
- Resolve profile by slug (public route, no auth)
- Return HTML page with SEO tags
- Return JSON API response
- Increment view counter
- Handle 404 if profile not found or not published

**Methods**:
```php
public function show(Request $request): Response
    // GET /profiles/{slug}
    // - Load profile with tenant and review stats
    // - Render HTML template with meta tags
    // - Track view (increment view_count for today)
    // Return Response with HTML

public function json(Request $request): Response
    // GET /api/profiles/{slug}.json
    // - Load profile with tenant info
    // - Load featured services/staff details
    // - Load review aggregate stats
    // - Return JSON response
```

**Dependencies**:
- `PublicProfileService::getProfile(slug)` → fetch profile data
- `PublicProfileService::incrementViewCount(profile_id, referrer)` → track views
- Reviews service (if available) → fetch aggregate ratings
- `View::response()` → render template

### `app/Controllers/Admin/Perka/PublicProfileController.php`

**Responsibilities**:
- Admin form for editing profile
- Save profile changes
- Generate/validate slugs
- Preview profile before publishing
- Manage featured services and staff

**Methods**:
```php
public function edit(Request $request): Response
    // GET /admin/tenants/{tenant_id}/perka/profile
    // - Load existing profile or create stub
    // - Load all services and staff for selection
    // - Render edit form template

public function save(Request $request): Response
    // POST /admin/tenants/{tenant_id}/perka/profile
    // - Validate input (bio length, slug uniqueness, color validity)
    // - Save profile data
    // - Validate and save custom CSS
    // - Update service/staff highlights
    // - Log to audit trail
    // - Redirect to preview or back to edit

public function preview(Request $request): Response
    // GET /admin/tenants/{tenant_id}/perka/profile/preview
    // - Load profile (saved or from form state)
    // - Render public profile template
    // - Show as it would appear

public function generateSlug(Request $request): Response
    // POST /admin/tenants/{tenant_id}/perka/profile/generate-slug
    // - Generate slug from tenant name (if empty)
    // - Check uniqueness, retry with suffix if needed
    // - Return JSON { slug, available: true/false }
```

**Permissions**:
- Operator: Access all tenants' profiles
- Business Owner: Access only their own profile
- Business Manager: Access only their own profile
- Customer: No access

---

## 6. Services

### `app/Engine/Perka/PublicProfileService.php`

**Responsibilities**:
- CRUD operations for profiles
- Slug validation and uniqueness
- View tracking
- Generate meta tags
- Integrate with Reviews (if enabled)

**Methods**:
```php
public static function getProfile(string $slug): ?array
    // Fetch profile by slug with tenant info
    // Return { id, tenant_id, tenant_name, slug, bio, ... }
    // Return null if not found or not published

public static function getProfileById(string $profileId): ?array
    // Fetch by ID (admin use)

public static function getProfileForTenant(string $tenantId): ?array
    // Fetch profile for admin editing

public static function createProfile(string $tenantId): string
    // Initialize empty profile for new tenant
    // Generate default slug from tenant name
    // Return profile ID

public static function updateProfile(string $profileId, array $data): bool
    // Update profile fields: bio, meta_description, meta_keywords, social_links, custom_css, etc.
    // Validate slug uniqueness
    // Sanitize custom CSS (allowlist properties)
    // Log audit trail
    // Invalidate cache
    // Return success

public static function validateSlug(string $slug, string $excludeProfileId = null): array
    // Check slug format (alphanumeric, hyphens, underscores, 3-100 chars)
    // Check uniqueness (unless excluding self)
    // Return { valid: bool, error?: string }

public static function generateSlug(string $tenantName): string
    // Create slug from business name
    // If taken, append -2, -3, etc.
    // Use natural slug format (lowercase, hyphenated)

public static function setFeaturedServices(string $profileId, array $serviceIds): bool
    // Set service highlights JSON
    // Validate all IDs exist for tenant
    // Preserve order
    // Log audit

public static function setFeaturedStaff(string $profileId, array $staffIds): bool
    // Set staff highlights JSON
    // Validate all IDs exist for tenant
    // Log audit

public static function publishProfile(string $profileId): bool
    // Set is_published = 1
    // Log audit
    // Fire event: profile.published

public static function unpublishProfile(string $profileId): bool
    // Set is_published = 0
    // Log audit

public static function incrementViewCount(string $profileId, ?string $referrer = null): void
    // Find existing view record for today
    // Increment view_count or create new
    // Log referrer and country code (optional GeoIP)

public static function getViewStats(string $tenantId, string $startDate, string $endDate): array
    // Query perka_profile_views for date range
    // Return { total_views, daily_breakdown, top_referrers }

public static function generateMetaTags(string $tenantId): array
    // Build { title, description, image, url, keywords, og:* }
    // Include review rating if available
    // Return array for template

public static function getProfileWithReviews(string $slug): array
    // Fetch profile + aggregate review stats
    // Include recent reviews (if show_reviews enabled)
    // Return enriched profile data
```

**Caching Strategy**:
- Profile data: Cache 1 hour (invalidate on update)
- Review stats: Cache 15 minutes (Reviews module updates)
- View counts: No cache (real-time in DB)

---

## 7. Views

### `templates/public/profile.php`

**Purpose**: Render public profile page with SEO meta tags.

**Content**:
```html
<!DOCTYPE html>
<html>
<head>
    <!-- SEO Meta Tags -->
    <title>{profile.business_name} - {meta_title}</title>
    <meta name="description" content="{meta_description}">
    <meta name="keywords" content="{meta_keywords}">
    <link rel="canonical" href="/profiles/{slug}">
    
    <!-- Open Graph (social sharing) -->
    <meta property="og:title" content="{profile.business_name}">
    <meta property="og:description" content="{meta_description}">
    <meta property="og:image" content="{featured_image_url}">
    <meta property="og:url" content="/profiles/{slug}">
    <meta property="og:type" content="business.business">
    
    <!-- Structured Data (Schema.org/LocalBusiness) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "{profile.business_name}",
        "image": "{featured_image_url}",
        "description": "{bio}",
        "telephone": "{tenant.phone}",
        "address": "{tenant.address}",
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "{avg_rating}",
            "reviewCount": "{review_count}"
        },
        "url": "/profiles/{slug}"
    }
    </script>
</head>
<body>
    <!-- Navigation -->
    <header>
        <!-- Top navbar with logo, search, login -->
    </header>
    
    <!-- Hero Section -->
    <section class="hero">
        <div class="featured-image" style="background-image: url({featured_image})"></div>
        <div class="hero-content">
            <h1>{profile.business_name}</h1>
            <div class="rating">
                ★★★★★ {avg_rating} ({review_count} reviews)
                <a href="#reviews">View Reviews</a>
            </div>
        </div>
    </section>
    
    <!-- About Section -->
    <section class="about">
        <h2>About Us</h2>
        <p>{profile.bio}</p>
        
        <!-- Social Links -->
        <div class="social-links">
            <a href="{social.instagram}" target="_blank">Instagram</a>
            <a href="{social.facebook}" target="_blank">Facebook</a>
            <!-- etc -->
        </div>
        
        <!-- CTA Button -->
        <a href="/book/{slug}" class="btn btn-primary">Book Now</a>
    </section>
    
    <!-- Services Grid -->
    <section class="services">
        <h2>Our Services</h2>
        <div class="service-grid">
            <!-- Each service card: image, name, duration, price -->
            <!-- Click opens /book/{slug}?service_id=... -->
        </div>
    </section>
    
    <!-- Staff Gallery (if enabled) -->
    <section class="staff" v-if="profile.show_staff">
        <h2>Meet Our Team</h2>
        <div class="staff-grid">
            <!-- Staff cards: photo, name, role/services -->
        </div>
    </section>
    
    <!-- Reviews Section (if enabled) -->
    <section class="reviews" v-if="profile.show_reviews" id="reviews">
        <h2>Customer Reviews</h2>
        <div class="reviews-summary">
            <div class="rating-bar">★★★★★ {avg_rating} average</div>
            <div class="rating-breakdown">
                5 stars: {count_5star} | 4 stars: {count_4star} | ...
            </div>
        </div>
        <div class="reviews-list">
            <!-- Recent reviews with approval status -->
        </div>
    </section>
    
    <!-- Next Available (if enabled) -->
    <section class="availability" v-if="profile.show_next_available">
        <h2>Available Now</h2>
        <p>Next available slot: {next_available_time}</p>
        <a href="/book/{slug}" class="btn">Book</a>
    </section>
    
    <!-- Footer -->
    <footer>
        <!-- Business info, links, copyright -->
    </footer>
</body>
</html>
```

**Design Considerations**:
- Mobile-responsive (Tailwind CSS)
- Fast loading (optimize featured_image)
- Accessibility (WCAG 2.1 AA)
- Dark mode support (Tailwind prefers-color-scheme)

### `templates/admin/perka/profile-edit.php`

**Purpose**: Admin form for editing profile.

**Sections**:
1. **Slug & Publishing**
   - Slug input (with generate button)
   - Publish/Unpublish toggle
   - Slug validation feedback

2. **Featured Image**
   - Image upload
   - Preview
   - Max 2MB, JPG/PNG only

3. **Bio & SEO**
   - Bio textarea (max 2000 chars)
   - Meta description (max 500 chars)
   - Meta keywords (max 255 chars)
   - Preview of how it appears in search results

4. **Social Links**
   - URL inputs for Instagram, Facebook, Twitter, LinkedIn, Website
   - URL validation (must be https://)

5. **Feature Toggles**
   - Show reviews (checkbox)
   - Show staff (checkbox)
   - Show next available slots (checkbox)

6. **Service Highlights**
   - Multi-select or drag-drop to reorder featured services
   - Show service name, duration, price

7. **Staff Highlights**
   - Multi-select or drag-drop to reorder featured staff
   - Show staff name, avatar

8. **Custom CSS (Optional)**
   - Textarea with CSS rules
   - Allowlist: color, background-color, border, font-size, font-weight, text-align
   - Block: visibility, display:none, position:absolute, etc.
   - CSS sanitization/validation

9. **Actions**
   - Save (POST)
   - Cancel
   - Preview (opens preview page)

### `templates/admin/perka/profile-preview.php`

**Purpose**: Preview how profile looks to customers (before publishing).

**Content**: Render same template as public profile, but with admin bar at top showing:
- "This is a preview" notice
- Edit button (link back to form)
- Publish button (POST)
- Browser warning if not published

---

## 8. APIs

### Public Profile Display
```
GET /profiles/{slug}

Response (HTML):
- 200 OK: Rendered HTML profile page
- 404 Not Found: Profile doesn't exist or not published
- Headers:
  - Cache-Control: max-age=3600 (1 hour cache for search engines)
  - Content-Type: text/html; charset=utf-8
```

### Public Profile JSON API
```
GET /api/profiles/{slug}.json

Response (200 OK):
{
  "id": "01abc...",
  "slug": "salon-beauty",
  "business_name": "Salon Beauty",
  "bio": "Professional salon...",
  "featured_image": "/uploads/profiles/01abc.jpg",
  "rating": 4.8,
  "review_count": 42,
  "social_links": {
    "instagram": "https://instagram.com/salonbeauty",
    "facebook": "https://facebook.com/salonbeauty"
  },
  "services": [
    {
      "id": "01xyz...",
      "name": "Haircut",
      "duration": 30,
      "price": "50.00",
      "description": "Professional haircut"
    }
  ],
  "staff": [
    {
      "id": "01def...",
      "name": "Alice",
      "avatar": "/uploads/staff/01def.jpg",
      "bio": "Experienced..."
    }
  ],
  "reviews": [
    {
      "id": "01review...",
      "rating": 5,
      "comment": "Great service!",
      "reviewer_name": "John",
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}

Errors:
- 404: Profile not found or not published
  { "error": "Profile not found", "code": "not_found" }
```

### Admin Profile Management
```
GET /admin/tenants/{tenant_id}/perka/profile

Response (200 OK, HTML):
- Profile edit form
- Current values pre-filled
- Validation errors (if redirected from save)

POST /admin/tenants/{tenant_id}/perka/profile

Request Body:
{
  "slug": "salon-beauty",
  "bio": "Professional salon...",
  "featured_image_id": "01abc...",  // from ImageUpload
  "meta_description": "Find us on...",
  "meta_keywords": "salon,beauty,hair",
  "social_links": {
    "instagram": "https://instagram.com/salonbeauty",
    "facebook": "https://facebook.com/salonbeauty"
  },
  "service_highlights": ["01xyz...", "01uvw..."],
  "staff_highlights": ["01def...", "01ghi..."],
  "show_reviews": 1,
  "show_staff": 1,
  "show_next_available": 0,
  "custom_css": "..."
}

Response:
- 302 Redirect: Back to form with flash message
- Flash success: "Profile saved!"
- Flash errors: Validation errors

Errors:
- 422 Unprocessable Entity:
  {
    "errors": {
      "slug": "Slug already in use",
      "bio": "Bio must be 10-2000 characters"
    }
  }
```

### Generate Slug
```
POST /admin/tenants/{tenant_id}/perka/profile/generate-slug

Request Body:
{
  "name": "My Business Name"
}

Response (200 OK):
{
  "slug": "my-business-name",
  "available": true
}

If slug taken:
{
  "slug": "my-business-name-2",
  "available": true
}
```

---

## 9. SEO Strategy

### Slug Strategy
- **Canonical Slug**: `{tenant_name_slugified}` (e.g., "Salon Beauty" → "salon-beauty")
- **Custom Slug**: Business owner can set custom slug (e.g., "sarah-hair-salon")
- **Uniqueness**: Slugs must be globally unique across all profiles
- **Immutability**: Once published, slug should not change (301 redirect to new slug if needed)
- **Format**: Lowercase, alphanumeric + hyphens/underscores only, 3-100 chars
- **Validation**: Server-side in `PublicProfileService::validateSlug()`

### Meta Tags
```html
<!-- Core -->
<title>{business_name} - Booking & Services</title>
<meta name="description" content="{first_150_chars_of_bio}">

<!-- Search Engine -->
<meta name="robots" content="index, follow">
<link rel="canonical" href="/profiles/{slug}">

<!-- Open Graph (Social) -->
<meta property="og:title" content="{business_name}">
<meta property="og:description" content="{meta_description}">
<meta property="og:image" content="{featured_image_url}">
<meta property="og:url" content="/profiles/{slug}">
<meta property="og:type" content="business.business">

<!-- Structured Data (JSON-LD) -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "{business_name}",
  "image": "{featured_image_url}",
  "description": "{bio}",
  "address": "{business_address}",
  "telephone": "{business_phone}",
  "url": "/profiles/{slug}",
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "{avg_rating}",
    "reviewCount": "{review_count}"
  },
  "review": [
    {
      "@type": "Review",
      "author": { "@type": "Person", "name": "{reviewer_name}" },
      "reviewRating": { "@type": "Rating", "ratingValue": "{rating}" },
      "reviewBody": "{review_comment}"
    }
  ]
}
</script>
```

### Sitemap Integration
- Add `/admin/tenants/{tenant_id}/perka/profiles/sitemap.xml` (admin-scoped)
- Add public endpoint `/api/sitemap/public-profiles.xml` that lists all published profiles
- Each profile entry: `<url><loc>/profiles/{slug}</loc><lastmod>...</lastmod></url>`
- Cache this for 24 hours

### Robots.txt
- Add to public `/robots.txt`:
  ```
  User-agent: *
  Disallow: /admin
  Disallow: /api/profiles/*.json
  Allow: /profiles/
  ```

---

## 10. Slug Strategy (Detailed)

### Slug Validation Rules
```php
// Format: 3-100 chars, alphanumeric + hyphens/underscores
// Lowercase
// No spaces, no special chars except - and _
// Cannot start/end with hyphen or underscore
// Cannot be a reserved word (admin, api, auth, book, install, etc.)
```

### Slug Generation Algorithm
```
1. Take tenant name: "Sarah's Hair Salon"
2. Lowercase: "sarah's hair salon"
3. Replace non-alphanumeric with hyphens: "sarah-s-hair-salon"
4. Collapse consecutive hyphens: "sarah-s-hair-salon"
5. Remove leading/trailing hyphens: "sarah-s-hair-salon"
6. Check uniqueness in DB
7. If taken, append "-2": "sarah-s-hair-salon-2"
8. If still taken, try "-3", "-4", etc.
9. Return first available
```

### Slug Validation Response
```
✓ Valid, Available: { valid: true, available: true, slug: "... "}
✓ Valid, Reserved: { valid: true, available: false, error: "Slug reserved" }
✗ Invalid Format: { valid: false, error: "Slug must be 3-100 alphanumeric characters" }
```

### Handling Slug Changes
- **Before Publishing**: Allow changes freely
- **After Publishing**: Allow changes with automatic 301 redirect from old slug
  - Create `perka_profile_slugs_history` table to track old slugs
  - Endpoint: `GET /profiles/{old_slug}` → 301 redirect to new slug
  - Cache invalidation for both slugs

---

## 11. Permissions

### Role-Based Access Control

| Role | Can View Profile | Can Edit Profile | Can Publish | Can Delete |
|------|------------------|------------------|-------------|-----------|
| Operator (super-admin) | ✓ All | ✓ All | ✓ | ✗ |
| Business Owner | ✓ Own | ✓ Own | ✓ | ✗ |
| Business Manager | ✓ Own | ✓ Own | ✓ | ✗ |
| Customer | ✓ Published only | ✗ | ✗ | ✗ |
| Anonymous | ✓ Published only | ✗ | ✗ | ✗ |

### Middleware Checks
```php
// Admin routes
AuthMiddleware::check();  // Must be logged in
// Then in controller:
$tenantId = $request->param('tenant_id');
$user = Auth::user();
if (!$user->canEditTenant($tenantId)) {
    return Response::forbidden();
}
```

---

## 12. Caching

### Strategy
| Data | TTL | Invalidation |
|------|-----|--------------|
| Profile Data | 1 hour | On update or 1hr expiry |
| Review Stats | 15 minutes | On new review or 15min expiry |
| View Counts | No cache | Real-time DB query |
| Featured Image | 24 hours | On image update |

### Implementation
```php
// Cache key: "profile:{slug}"
// Cache key: "profile:reviews:{tenant_id}"
// Cache key: "profile:featured_image:{profile_id}"

// Invalidation on update:
Cache::forget("profile:{old_slug}");
Cache::forget("profile:{new_slug}");
Cache::forget("profile:reviews:{tenant_id}");
```

### CDN Caching (for future)
- Use Cache-Control headers: `public, max-age=3600`
- ETag for client-side validation
- Allow CloudFlare/Varnish to cache public profiles

---

## 13. Security Considerations

### Input Validation
- **Slug**: Alphanumeric + hyphens/underscores only, 3-100 chars
- **Bio**: Max 2000 chars, no HTML (strip tags)
- **Meta Description**: Max 500 chars, no HTML
- **Meta Keywords**: Max 255 chars, comma-separated
- **URLs (social links)**: Must start with https://
- **Custom CSS**: Allowlist properties only (see below)

### XSS Prevention
```php
// All output escaped with htmlspecialchars()
echo htmlspecialchars($profile['bio'], ENT_QUOTES, 'UTF-8');

// For JSON output, use json_encode()
echo json_encode($profile);

// For HTML meta tags
<meta name="description" content="<?= htmlspecialchars($description) ?>">
```

### Custom CSS Sanitization
```php
// Allowlist approach: only allow specific properties
$allowed_properties = [
    'color', 'background-color', 'border', 'border-color',
    'font-size', 'font-weight', 'text-align', 'line-height',
    'padding', 'margin', 'width', 'height', 'max-width', 'max-height'
];

// Block dangerous properties
$blocked_keywords = [
    'expression', 'behavior', 'javascript:', '@import', 'position:absolute',
    'position:fixed', 'z-index', 'visibility:hidden', 'display:none',
    '::before', '::after'
];

// Parse CSS and filter
$css = preg_replace_callback('/([a-z-]+):\s*([^;]+);/', function($m) {
    $property = $m[1];
    $value = $m[2];
    if (!in_array($property, $allowed_properties)) return '';
    if (preg_match('/(' . implode('|', $blocked_keywords) . ')/i', $value)) return '';
    return $m[0];
}, $custom_css);
```

### SQL Injection Prevention
- All queries use prepared statements
- Never interpolate user input into SQL

### CSRF Protection
- All POST/PUT/DELETE require CSRF token
- Token generated in form, validated in middleware

### Image Upload Security
- Validate file type (JPG/PNG only)
- Validate file size (max 2MB)
- Rename file with ULID (prevent directory traversal)
- Store outside web root (serve via controller)
- Scan with antivirus (optional, on production)

---

## 14. Future Compatibility

### With Marketplace (Epic 8)
- Profiles will show seller ratings from marketplace transactions
- "Browse Marketplace" section on profile
- Seller profile page (subset of main profile)

### With Loyalty (Epic 1-2)
- Profiles will show loyalty program info (if enabled)
- "Join loyalty program" section
- Loyalty-earning services highlighted

### With Analytics (Epic 14)
- Profile view analytics dashboard
- Referrer source tracking
- Conversion to booking tracking
- A/B testing of profile content

### With Multi-location (Epic 13)
- Primary business profile at `/profiles/{slug}`
- Location-specific profiles at `/profiles/{slug}/locations/{location_id}`
- Locations list on business profile

---

## 15. Minimal Core Modifications

To keep the booking engine completely untouched, we add exactly **three includes**:

### 1. `app/routes.php` (ONE LINE)
```php
// At end of file, after all existing route definitions:
if (file_exists(__DIR__ . '/Perka/Routes.php')) {
    require __DIR__ . '/Perka/Routes.php';
}
```

### 2. `app/bootstrap.php` (ONE LINE)
```php
// After $app->boot(), before return $app:
if (file_exists(__DIR__ . '/Perka/Bootstrap.php')) {
    require __DIR__ . '/Perka/Bootstrap.php';
}
return $app;
```

### 3. `templates/admin/layout.php` (ONE INCLUDE in sidebar)
```php
<!-- Existing sidebar items -->

<!-- Perka admin menu -->
<?php if (file_exists(__DIR__ . '/../../app/Perka/menu.php')): ?>
    <?php require __DIR__ . '/../../app/Perka/menu.php'; ?>
<?php endif; ?>
```

---

## 16. Files to Create (All in app/Perka)

### Directory Structure
```
app/Perka/
├── Bootstrap.php                    (initializes services, registers menu)
├── Routes.php                       (defines routes using core Router)
├── menu.php                         (admin sidebar items)
├── Controllers/
│   ├── PublicProfileController.php
│   └── Admin/
│       └── PublicProfileController.php
├── Services/
│   └── PublicProfileService.php
├── Models/
│   └── PublicProfile.php
└── templates/
    ├── public/
    │   └── profile.php
    └── admin/
        ├── profile-edit.php
        └── profile-preview.php
```

### Migrations (module-owned, run by PerkaMigrator — NOT core app/Migrations/)
- `app/Perka/Modules/PublicProfile/Migrations/001_create_perka_business_profiles.php`
- Registry table `perka_migrations` (applied-list) is created by the runner on first run.

Single new table only: **`perka_business_profiles`** (one row per tenant). No
`perka_profile_views` (analytics is a future module) and no service/staff
highlight tables — featured content, if reintroduced, is revisited later.
Identity fields (name, slug, cover_image_path, timezone) are read from
`tenants`; no feature-flag column is added to `tenants` in this phase.

### New Perka Files — module-based layout (`app/Perka/Modules/{Module}/…`)

Phase 1 (Database + Models + Services) — implemented:
- `app/Perka/Shared/PerkaMigrator.php` - Minimal Perka-owned migration runner (applied-list)
- `app/Perka/Modules/PublicProfile/Migrations/001_create_perka_business_profiles.php`
- `app/Perka/Modules/PublicProfile/Models/BusinessProfile.php` - Query/persistence methods
- `app/Perka/Modules/PublicProfile/Services/PublicProfileService.php` - Business logic

Later phases (NOT yet created):
- `app/Perka/Bootstrap.php` - Perka bootstrap orchestrator (invokes PerkaMigrator, wiring)
- `app/Perka/Routes.php` - Route orchestrator (loads each module's routes.php)
- `app/Perka/menu.php` - Admin menu orchestrator
- `app/Perka/Modules/PublicProfile/routes.php` - Module routes
- `app/Perka/Modules/PublicProfile/Controllers/PublicProfileController.php` - Public display
- `app/Perka/Modules/PublicProfile/Controllers/Admin/PublicProfileController.php` - Admin form
- `app/Perka/Modules/PublicProfile/Views/public/profile.php` - Public profile page
- `app/Perka/Modules/PublicProfile/Views/admin/profile-edit.php` - Admin edit form
- `app/Perka/Modules/PublicProfile/Views/admin/profile-preview.php` - Admin preview

### Testing (Optional)
- `tests/Unit/Perka/PublicProfileServiceTest.php`
- `tests/Integration/PublicProfileFlowTest.php`

---

## 17. Files to Modify (CORE ONLY: 3 lines total)

### Core Framework (3 one-line includes)
- `app/routes.php` - Add one-line include for Perka routes
- `app/bootstrap.php` - Add one-line include for Perka bootstrap
- `templates/admin/layout.php` - Add one-line include for Perka menu

**That's it. No other core files are modified.**

**The booking engine remains completely untouched.**

---

## 18. Implementation Risks

### Risk 1: Upstream Updates Breaking Includes
**Impact**: If core routes.php, bootstrap.php, or templates/admin/layout.php are modified by VoxelBooking updates, includes might break  
**Likelihood**: Low (includes are at end of files)  
**Mitigation**: Place includes at END of existing files, use file_exists() guards, test on updates

### Risk 2: Slug Collision
**Impact**: Two businesses could get same slug (race condition)  
**Likelihood**: Medium (high volume)  
**Mitigation**: Database UNIQUE constraint on slug, validate before INSERT

### Risk 3: SEO Duplicate Content
**Impact**: Google penalizes site if profile appears in multiple places  
**Likelihood**: Low  
**Mitigation**: Canonical link tag, robots.txt disallow duplicates

### Risk 4: View Tracking Performance
**Impact**: Database write on every profile visit degrades performance  
**Likelihood**: Medium (high traffic profiles)  
**Mitigation**: Aggregate views by day (increment counter, not insert)

### Risk 5: XSS via Social Links
**Impact**: Malicious JavaScript in profile  
**Likelihood**: Low (URL validation)  
**Mitigation**: Whitelist https:// only, validate URL format

### Risk 6: Image Upload Abuse
**Impact**: Disk space exhaustion, malware upload  
**Likelihood**: Low  
**Mitigation**: File size limits, type validation, antivirus scanning

### Risk 7: Slow Profile Rendering
**Impact**: Users wait for profile to load (SEO impact)  
**Likelihood**: Medium (many reviews/staff)  
**Mitigation**: Database indexes, caching, lazy-load images

---

## 19. Estimated Implementation Steps

### Phase 0: Core Integration (0.5 day)
- [ ] Add one-line include to `app/routes.php`
- [ ] Add one-line include to `app/bootstrap.php`
- [ ] Add one-line include to `templates/admin/layout.php`
- [ ] Verify core files still work (no breaking changes)

### Phase 1: Database & Service (1.5 days)
- [ ] Create migrations 101-103 (profiles table, views table, perka_features column)
- [ ] Create `PublicProfileService` with CRUD and validation
- [ ] Create `PublicProfile` model (query methods)
- [ ] Test service layer in isolation

### Phase 2: Public Profile Display (1.5 days)
- [ ] Create `PublicProfileController` in `app/Perka/Controllers/`
- [ ] Create `app/Perka/templates/public/profile.php`
- [ ] Implement meta tag generation
- [ ] Implement view tracking
- [ ] Test SEO with schema validators

### Phase 3: Admin Profile Management (1.5 days)
- [ ] Create `Admin/PublicProfileController` in `app/Perka/Controllers/`
- [ ] Create edit form template
- [ ] Create preview template
- [ ] Implement image upload handling
- [ ] Implement slug generation and validation

### Phase 4: Routes & Bootstrap (1 day)
- [ ] Create `app/Perka/Routes.php` (register all routes)
- [ ] Create `app/Perka/Bootstrap.php` (service initialization)
- [ ] Create `app/Perka/menu.php` (admin sidebar items)
- [ ] Verify routes load and don't conflict with core

### Phase 5: Advanced Features (1 day)
- [ ] Integrate Reviews display (show ratings)
- [ ] Implement featured services/staff selection
- [ ] Implement social links management
- [ ] Custom CSS sanitization and validation

### Phase 6: Testing & Refinement (1 day)
- [ ] Unit tests for `PublicProfileService`
- [ ] Integration tests for profile flow
- [ ] Manual testing of public profile
- [ ] Manual testing of admin form
- [ ] SEO testing (meta tags, structured data)

**Total: 8-9 days**

### Blockers / Assumptions
- Reviews module query structure (can read existing review tables)
- ImageUpload utility exists in core (app/Engine/Utilities/ImageUpload.php)
- Existing GDPR consent patterns in BookingService (for reference)

---

## 20. Success Criteria

✓ Profile accessible at `/profiles/{slug}` without authentication  
✓ SEO meta tags present and valid  
✓ Admin can create/edit profile  
✓ Admin can publish/unpublish profile  
✓ Slug validation prevents duplicates  
✓ View tracking counts profile visits  
✓ Review ratings displayed (if Reviews enabled)  
✓ Responsive design works on mobile/tablet/desktop  
✓ Image upload works (max 2MB, JPG/PNG)  
✓ Custom CSS sanitization blocks malicious rules  
✓ No XSS vulnerabilities  
✓ No SQL injection vulnerabilities  
✓ CSRF token required on admin forms  
✓ JSON API returns correct data  
✓ Cache invalidation works on update  
✓ Tests pass (unit + integration)  

---

## 21. Post-Implementation Checklist

- [ ] Code review complete
- [ ] All tests passing
- [ ] Manual testing of public profile
- [ ] Manual testing of admin form
- [ ] SEO validation (schema.org, meta tags)
- [ ] Performance testing (page load time)
- [ ] Security testing (XSS, SQLi, CSRF)
- [ ] Accessibility testing (WCAG 2.1 AA)
- [ ] Documentation updated (MODULES.md, API.md)
- [ ] Changelog entry added
- [ ] Commit and push to feature branch
- [ ] Pull request created with description
- [ ] Approved by code reviewer
- [ ] Merged to main branch

---

## 22. Appendix: API Examples

### Example: Fetch Profile JSON
```bash
curl https://voxelbooking.local/api/profiles/salon-beauty.json

Response:
{
  "id": "01abc...",
  "slug": "salon-beauty",
  "business_name": "Salon Beauty",
  "bio": "Professional hair salon specializing in cuts, coloring, and styling.",
  "featured_image": "/uploads/profiles/01abc.jpg",
  "rating": 4.8,
  "review_count": 42,
  "services": [
    {
      "id": "01xyz...",
      "name": "Haircut",
      "duration": 30,
      "price": "50.00",
      "description": "Professional haircut for all hair types"
    }
  ],
  "reviews": [
    {
      "id": "01rev...",
      "rating": 5,
      "comment": "Great service!",
      "reviewer_name": "John",
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}
```

### Example: Save Profile (Admin)
```bash
curl -X POST https://voxelbooking.local/admin/tenants/01tenant.../perka/profile \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: abc123..." \
  -d '{
    "slug": "salon-beauty-new",
    "bio": "Updated bio...",
    "meta_description": "Professional hair salon",
    "service_highlights": ["01xyz...", "01uvw..."],
    "social_links": {
      "instagram": "https://instagram.com/salonbeauty"
    }
  }'

Response:
302 Redirect to /admin/tenants/{tenant_id}/perka/profile
Flash: "Profile saved successfully!"
```

