<?php

declare(strict_types=1);

/**
 * Supported currency registry — single source of truth.
 *
 * Every entry maps ISO 4217 code to its display symbol.
 * Used by:
 *   - Locale::currencySymbol()  → PHP currency formatting
 *   - get_supported_currencies() → admin select dropdowns
 *   - Locale::getFormattingConfig() → injected into window.__VB_FMT__.currency_symbol
 *     for JS formatPrice()
 *
 * When no dedicated symbol exists, the ISO code itself is the symbol.
 */
return [
    'EUR' => '€',
    'USD' => '$',
    'GBP' => '£',
    'CHF' => 'CHF',
    'SEK' => 'kr',
    'NOK' => 'kr',
    'DKK' => 'kr',
    'PLN' => 'zł',
    'CZK' => 'Kč',
    'HUF' => 'Ft',
    'RON' => 'lei',
    'BGN' => 'лв',
    'HRK' => 'kn',
    'CAD' => 'CA$',
    'AUD' => 'A$',
    'NZD' => 'NZ$',
    'BRL' => 'R$',
    'MXN' => 'MX$',
    'ARS' => 'AR$',
    'JPY' => '¥',
    'CNY' => '¥',
    'KRW' => '₩',
    'INR' => '₹',
    'SGD' => 'S$',
    'THB' => '฿',
    'IDR' => 'Rp',
    'AED' => 'AED',
    'ZAR' => 'R',
    'TRY' => '₺',
    'ILS' => '₪',
    'EGP' => 'E£',
    'NGN' => '₦',
];
