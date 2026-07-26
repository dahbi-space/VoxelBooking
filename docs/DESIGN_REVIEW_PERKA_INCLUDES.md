# Design Review: Perka Core Includes

Three strategic include points have been identified to minimize core coupling while enabling Perka to self-register routes, services, and admin menu items.

**Verdict**: No existing extension points in VoxelBooking. These three includes are the minimal necessary changes.

---

## 1. Routes Include: `app/routes.php`

### Current File
**Location**: `/home/user/VoxelBooking/app/routes.php`

**Current Structure** (lines 1-294):
```php
<?php
declare(strict_types=1);
use App\Engine\Router;
// ... middleware imports ...

return function (Router $router): void {
    // Global middleware group (lines 27-292)
    $router->group([ ... ], function (Router $router) {
        // ── Health check ──
        // ── SEO ──
        // ── Root redirect ──
        // ── Installation ──
        // ── Auth ──
        // ── Admin (lines 72-236) ──
        // ── Public API ──
        // ── Cron ──
        // ── Agent API ──
    });
};  // ← CLOSING BRACKET at line 294
```

### Proposed Include Location
**After line 294** (after the closing brace and before any other code):

```php
// ── Perka module routes ──
if (file_exists(__DIR__ . '/Perka/Routes.php')) {
    require __DIR__ . '/Perka/Routes.php';
}
```

### Why This Location is Best

1. **After all core routes** — Perka routes are registered after core, so they can't accidentally shadow core routes. If both define the same route, core wins (expected behavior).

2. **Outside the middleware group** — The include is OUTSIDE the return statement. This allows Perka/Routes.php to call `Router::get()` directly instead of being nested in a callback. Cleaner code structure.

3. **File still valid** — The return statement closes at line 294. The include after that is executed when the file is `require`d, but it's not part of the return value. The app receives the correct `$router` callback.

4. **No ambiguity** — Clear that Perka is an extension, not part of core.

### Impact on Future Upstream Updates

**Very Low Risk** because:
- Include is at the absolute end of file (line 295+)
- VoxelBooking updates will almost certainly not add code after line 294
- Even if new code is added at end, `file_exists()` guard ensures graceful handling
- No modifications to core route definitions means no merge conflicts

**Worst case**: New routes added after line 294 could conflict with included Perka routes. Mitigation: Perka routes use reserved `/perka/` prefix, unlikely to collide.

### Graceful Failure

```php
if (file_exists(__DIR__ . '/Perka/Routes.php')) {
    require __DIR__ . '/Perka/Routes.php';
}
```

If `app/Perka/` doesn't exist:
- `file_exists()` returns false
- Include silently skipped
- App continues normally ✓
- No error logged (Perka is optional)
- Routes array is unchanged

### Existing Extension Point?

**Search Result**: No existing hook system in `app/routes.php` or Router class.

**Could we use Router middleware groups?** No — middleware is for request filtering, not route registration. Routes must be defined at registration time.

**Could we dynamically add routes at dispatch time?** No — FastRoute compiles routes before dispatching. Routes are immutable after compilation.

**Conclusion**: The include is the only practical solution.

---

## 2. Bootstrap Include: `app/bootstrap.php`

### Current File
**Location**: `/home/user/VoxelBooking/app/bootstrap.php`

**Current Structure** (lines 1-17):
```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$app = new \App\Engine\App(dirname(__DIR__));
$app->boot();

return $app;
```

### Proposed Include Location
**After line 15** (after `$app->boot()` but before return):

```php
// Load Perka bootstrap if available
if (file_exists(__DIR__ . '/Perka/Bootstrap.php')) {
    require __DIR__ . '/Perka/Bootstrap.php';
}

return $app;
```

**Exact code**:
```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$app = new \App\Engine\App(dirname(__DIR__));
$app->boot();

// ← INSERT HERE
if (file_exists(__DIR__ . '/Perka/Bootstrap.php')) {
    require __DIR__ . '/Perka/Bootstrap.php';
}

return $app;
```

### Why This Location is Best

1. **After core boot, before return** — Perka services initialize after VoxelBooking core is fully booted. Perka can safely depend on:
   - Database initialized
   - Logger initialized
   - View engine initialized
   - Locale engine initialized
   - Demo mode initialized

2. **$app is available** — Perka/Bootstrap.php can call `$app->router()` to register hooks.

3. **No side effects** — Bootstrap runs once, at startup. Safe place for initialization logic (service registration, event listeners, etc.).

4. **Clear lifecycle** — Perka bootstrap is the last thing before app is returned to public/index.php.

### Impact on Future Upstream Updates

**Very Low Risk** because:
- Include is in middle of file (lines 16-18 out of ~18)
- Simple, stable API: `$app->boot()` and `return $app`
- Unlikely to change in future versions
- `file_exists()` guard handles missing Perka gracefully

**Best case**: VoxelBooting adds more boot logic before return. Include still works — Perka boots after all core initialization.

**Worst case**: VoxelBooting refactors bootstrap structure entirely. Perka/Bootstrap.php may break, but app still boots (error is isolated to Perka).

### Graceful Failure

```php
if (file_exists(__DIR__ . '/Perka/Bootstrap.php')) {
    require __DIR__ . '/Perka/Bootstrap.php';
}
```

If `app/Perka/Bootstrap.php` doesn't exist:
- `file_exists()` returns false
- Include skipped
- App continues, returns to public/index.php ✓
- Perka features are unavailable (expected)
- No error or warning

If `app/Perka/Bootstrap.php` exists but has syntax error:
- PHP Parse Error is thrown
- App crashes with visible error (preferred over silent failure)
- Admin sees the error and can fix or remove Perka

### Existing Extension Point?

**Search Result**: No existing hook/listener system in App.php or bootstrap.php.

**Could we use inheritance?** No — App is final class, cannot be extended.

**Could we use method registration?** No — No hooks in App::boot() or initialization sequence.

**Conclusion**: The include is necessary. No existing extension point exists.

---

## 3. Admin Menu Include: `templates/admin/layout.php`

### Current File
**Location**: `/home/user/VoxelBooking/templates/admin/layout.php`

**Current Structure** (lines 86-272):
```php
<nav class="vb-sidebar-nav">
    <?php
    // Sidebar logic: tenant context detection, role checks, pattern resolution
    
    if ($inTenantContext):
        // Tenant-context menu (lines 105-222)
        // Overview section
        // Scheduling section (pattern-specific)
        // People section
        // System section
    elseif (\App\Engine\Auth::isOperator()):
        // Operator menu (lines 224-253)
        // Dashboard
        // Tenants
        // Bookings
        // Applications
    endif;
    
    if (\App\Engine\Auth::isOperator() && !$isImpersonating):
        // System section (operator-only, lines 255-270)
        // Settings
        // Deletion queue
        // Updates
    endif;
    ?>
</nav>
```

### Proposed Include Location
**After line 271** (after the last `<?php endif; ?>` but before closing `</nav>`):

```php
<!-- Perka admin menu items -->
<?php if (file_exists(__DIR__ . '/../../app/Perka/menu.php')): ?>
    <?php require __DIR__ . '/../../app/Perka/menu.php'; ?>
<?php endif; ?>
```

**Exact context** (lines 254-272):
```php
            <?php if (\App\Engine\Auth::isOperator() && !$isImpersonating): ?>
            <div class="vb-sidebar-section">
                <div class="vb-sidebar-section-label"><?= __('admin.nav.system') ?></div>
                <a href="/admin/settings" class="vb-sidebar-link <?= str_starts_with($activePage, 'settings') ? 'active' : '' ?>">
                    <i data-lucide="settings"></i>
                    <?= __('admin.nav.settings') ?>
                </a>
                <a href="/admin/deletion-queue" class="vb-sidebar-link <?= $activePage === 'deletion-queue' ? 'active' : '' ?>">
                    <i data-lucide="shield"></i>
                    <?= __('admin.nav.deletion_queue') ?>
                </a>
                <a href="/admin/updates" class="vb-sidebar-link <?= $activePage === 'updates' ? 'active' : '' ?>">
                    <i data-lucide="download"></i>
                    <?= __('admin.nav.updates') ?>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- ← INSERT HERE -->
            <?php if (file_exists(__DIR__ . '/../../app/Perka/menu.php')): ?>
                <?php require __DIR__ . '/../../app/Perka/menu.php'; ?>
            <?php endif; ?>
        </nav>
```

### Why This Location is Best

1. **Inside `<nav>` but after all core items** — Perka menu is visually appended to core menu. No restructuring of existing HTML.

2. **Outside all conditionals** — Menu appears regardless of user role (Perka/menu.php handles its own role checks via PHP conditionals).

3. **Clean separation** — Comment marks Perka section clearly. Visual and structural separation from core menu.

4. **Reuses existing styles** — Perka menu items use same `.vb-sidebar-link`, `.vb-sidebar-section`, etc. CSS classes. Consistent UI.

### Impact on Future Upstream Updates

**Low Risk** because:
- Include is at end of `<nav>` (line 272+)
- Closing `</nav>` is just a static tag, won't change
- HTML structure is unlikely to change significantly
- `file_exists()` guard prevents errors if Perka missing

**Best case**: VoxelBooking adds more menu items. Perka items appear after them (still correct).

**Worst case**: VoxelBooting refactors sidebar to use JavaScript menu builder. Include still works (just loads PHP that outputs HTML), but styling might be off. Perka/menu.php can update its output format if needed.

### Graceful Failure

```php
<?php if (file_exists(__DIR__ . '/../../app/Perka/menu.php')): ?>
    <?php require __DIR__ . '/../../app/Perka/menu.php'; ?>
<?php endif; ?>
```

If `app/Perka/menu.php` doesn't exist:
- `file_exists()` returns false
- Include skipped
- Admin sidebar renders normally without Perka items ✓
- Admin panel is fully functional

If `app/Perka/menu.php` exists but has syntax error:
- PHP Parse Error thrown during template render
- Admin page fails to load
- Admin sees error (preferred — clear feedback)
- Can be fixed or Perka deleted to restore functionality

### Existing Extension Point?

**Search Result**: No existing menu hook system in layout.php or View class.

**Could we use View methods to register menu?** No — View is only for template rendering, not for state management.

**Could we use session to pass menu items?** No — Sidebar is rendered in template, session unavailable there.

**Could we use a template partial include path?** No — View engine looks for templates in `templates/` only, not `app/Perka/`.

**Conclusion**: The include is the only practical solution for dynamic menu injection.

---

## Summary: No Existing Extension Points Found

Searched entire VoxelBooking codebase (`app/Engine/`, `app/Controllers/`) for:
- Hook systems
- Event listeners
- Plugin registries
- Callback mechanisms
- Middleware hooks
- Template inheritance

**Result**: Zero extension points exist.

**Why**: VoxelBooting is a lean micro-framework. It does not build infrastructure for plugins/extensions. Everything is wired at startup via `app/routes.php` and `app/bootstrap.php`.

**Conclusion**: The three includes are necessary and represent the minimal, most surgical changes possible.

---

## Three Includes Summary Table

| Include | File | Location | Lines Added | Risk | Graceful Failure |
|---------|------|----------|-------------|------|------------------|
| Routes | `app/routes.php` | After line 294 | 3 | Very Low | ✓ Yes |
| Bootstrap | `app/bootstrap.php` | After line 15 | 3 | Very Low | ✓ Yes |
| Menu | `templates/admin/layout.php` | After line 271 | 4 | Low | ✓ Yes |
| **Total** | — | — | **10 lines** | **Very Low** | **All ✓** |

---

## Backward Compatibility & Upstream Safety

### VoxelBooking v1.1.0 → v1.2.0 Update Scenario

User has Perka installed. New VoxelBooking release is available.

**Current state**:
- `app/routes.php` has Perka include at end
- `app/bootstrap.php` has Perka include at end
- `templates/admin/layout.php` has Perka include at end
- `app/Perka/` folder with all Perka code

**VoxelBooking update process**:
1. User downloads v1.2.0 tarball
2. Extracts to overwrite `app/`, `templates/` but NOT `app/Perka/` (user's custom code)
3. Updated files are:
   - `app/routes.php` (v1.2.0 version)
   - `app/bootstrap.php` (v1.2.0 version)
   - `templates/admin/layout.php` (v1.2.0 version)

**What happens to includes?**

Case A: VoxelBooting doesn't modify end of files
- Updated files still have Perka includes at end
- Perka continues to work ✓

Case B: VoxelBooting adds new content at end of files
- New content is added before Perka includes
- Perka includes still execute ✓

Case C: VoxelBooting removes or refactors file structure
- Includes might be in different location or removed
- User must re-apply includes to updated file
- Clear instructions provided in migration guide

**Mitigation**: Place includes at absolute end of each file. Use `file_exists()` guard. Document that Perka includes are meant to be re-applied after updates.

---

## Conclusion

These three include points are:
1. ✓ Minimal (10 lines total)
2. ✓ Safe (file_exists guards)
3. ✓ Upstream-compatible (end of files)
4. ✓ Gracefully fail (app continues if Perka missing)
5. ✓ Only option (no existing hooks)
6. ✓ Clear and maintainable (simple, obvious pattern)

**Approval**: Ready for Phase 0 implementation.

