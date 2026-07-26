<?php

declare(strict_types=1);

/**
 * Neutral not-found page for /business/{slug}.
 *
 * Rendered identically whether the slug is unknown, has no profile, or the
 * profile is unpublished — no existence leak. Standalone (no admin layout).
 * Status 404 is set by the controller via PerkaView::response(..., 404).
 */
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Page not found</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color: #1a1a1a; background: #fff; }
        .pk-404 { text-align: center; padding: 2rem; }
        .pk-404 h1 { font-size: 3rem; margin: 0 0 .5rem; }
        .pk-404 p { color: #666; margin: 0; }
        @media (prefers-color-scheme: dark) {
            body { color: #ececec; background: #111; }
            .pk-404 p { color: #aaa; }
        }
    </style>
</head>
<body>
    <div class="pk-404">
        <h1>404</h1>
        <p>This page isn’t available.</p>
    </div>
</body>
</html>
