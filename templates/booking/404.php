<!DOCTYPE html>
<html lang="<?= \App\Engine\Locale::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= __('booking.empty.404_title') ?></title>
    <link rel="stylesheet" href="/assets/css/booking-css.css">
    <style>
        :root {
            --vb-brand: #2563EB;
            --vb-brand-hover: #1D4FD7;
            --vb-brand-light: #EFF6FF;
            --vb-brand-text: #FFFFFF;
            --vb-brand-rgb: 37, 99, 235;
        }
    </style>
</head>
<body>
    <div class="vb-book-app is-ready" id="vb-book-app">
        <main class="vb-book-flow" style="display:flex;align-items:center;justify-content:center;min-height:70vh;">
            <div style="text-align:center;max-width:400px;">
                <div style="margin-bottom:24px;">
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity:0.25;">
                        <circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M24 16v10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="24" cy="32" r="1.5" fill="currentColor"/>
                    </svg>
                </div>
                <h1 class="vb-book-business-name" style="font-size:1.375rem;"><?= __('booking.empty.404_title') ?></h1>
                <p class="vb-book-business-desc" style="margin-top:12px;">
                    <?= __('booking.empty.404_desc') ?>
                </p>
                <p class="vb-book-business-desc" style="margin-top:24px;font-size:0.8125rem;">
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
