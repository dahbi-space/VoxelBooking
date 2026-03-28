<!DOCTYPE html>
<html lang="<?= \App\Engine\Locale::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= __('booking.empty.404_title') ?></title>
    <link rel="stylesheet" href="/assets/css/booking-css.css">
</head>
<body>
    <div class="vb-book-app is-ready" id="vb-book-app">
        <main class="vb-book-flow vb-book-error-center">
            <div class="vb-book-error-content">
                <div class="vb-book-error-icon">
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M24 16v10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="24" cy="32" r="1.5" fill="currentColor"/>
                    </svg>
                </div>
                <h1 class="vb-book-business-name vb-book-error-title"><?= __('booking.empty.404_title') ?></h1>
                <p class="vb-book-business-desc vb-book-error-desc">
                    <?= __('booking.empty.404_desc') ?>
                </p>
                <p class="vb-book-business-desc vb-book-error-help">
                    <?= __('booking.empty.404_help') ?>
                </p>
            </div>
        </main>
        <footer class="vb-book-footer">
            <span><?= __('booking.footer.powered_by') ?></span>
            <a href="<?= htmlspecialchars(brand_url(), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars(app_name(), ENT_QUOTES, 'UTF-8') ?></a>
        </footer>
    </div>
</body>
</html>
