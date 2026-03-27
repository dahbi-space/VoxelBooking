<!DOCTYPE html>
<html lang="<?= htmlspecialchars($tenant['locale'] ?? 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Book an appointment with <?= htmlspecialchars($tenant['name']) ?>">
    <title>Book – <?= htmlspecialchars($tenant['name']) ?></title>

    <!-- Brand tokens (per-tenant) -->
    <style><?= $brandStyle ?></style>

    <!-- Inter Variable (self-hosted) -->
    <link rel="preconnect" href="/assets/fonts/">
    <link rel="stylesheet" href="/assets/css/booking-css.css">

    <!-- Anti-FOUC: hide until CSS loads -->
    <style>
        .vb-book-app { opacity: 0; transition: opacity 0.2s ease-out; }
        .vb-book-app.is-ready { opacity: 1; }
    </style>
</head>
<body>
    <div class="vb-book-app" id="vb-book-app">
        <!-- Header -->
        <header class="vb-book-header">
            <div class="vb-book-header-inner">
                <?php if (!empty($tenant['logo_path'])): ?>
                    <img
                        src="/uploads/<?= htmlspecialchars($tenant['slug']) ?>/<?= htmlspecialchars($tenant['logo_path']) ?>"
                        alt="<?= htmlspecialchars($tenant['name']) ?>"
                        class="vb-book-logo"
                    >
                <?php endif; ?>
                <h1 class="vb-book-business-name"><?= htmlspecialchars($tenant['name']) ?></h1>
                <?php if (!empty($tenant['booking_page_description'])): ?>
                    <p class="vb-book-business-desc"><?= htmlspecialchars($tenant['booking_page_description']) ?></p>
                <?php endif; ?>
            </div>
        </header>

        <!-- Booking flow container -->
        <main class="vb-book-flow" id="vb-book-flow">
            <!-- Steps are rendered by JavaScript -->
            <div class="vb-book-loading" id="vb-book-loading">
                <div class="vb-book-spinner"></div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="vb-book-footer">
            <span>Powered by</span>
            <a href="https://voxelbooking.com" target="_blank" rel="noopener">VoxelBooking</a>
        </footer>
    </div>

    <!-- Tenant config for JS -->
    <script>
        window.__VB_CONFIG__ = <?= json_encode($tenantConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.__VB_CSRF__ = <?= json_encode($csrfToken) ?>;
        window.__VB_TS__ = Date.now();
    </script>
    <script type="module" src="/assets/js/booking.js"></script>
</body>
</html>
