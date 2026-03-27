<?php

declare(strict_types=1);

/**
 * Locale registry.
 *
 * Each supported locale is defined here with formatting rules.
 * Adding a new locale = adding an entry here + a lang/{locale}/ directory.
 * No code changes required.
 *
 * v1 ships with English only. Other locales will be added later.
 */
return [
    'en' => [
        'name'             => 'English',
        'native_name'      => 'English',
        'direction'        => 'ltr',
        'date_format'      => 'm/d/Y',
        'date_format_long' => 'F j, Y',
        'time_format'      => 'g:i A',
        'datetime_format'  => 'm/d/Y g:i A',
        'week_start'       => 0,
        'decimal_sep'      => '.',
        'thousands_sep'    => ',',
        'currency_position'=> 'before',
        'currency_space'   => false,
        'php_locale'       => 'en_US.UTF-8',
        'intl_locale'      => 'en-US',
    ],
    'nl' => [
        'name'             => 'Dutch',
        'native_name'      => 'Nederlands',
        'direction'        => 'ltr',
        'date_format'      => 'd-m-Y',
        'date_format_long' => 'j F Y',
        'time_format'      => 'H:i',
        'datetime_format'  => 'd-m-Y H:i',
        'week_start'       => 1,
        'decimal_sep'      => ',',
        'thousands_sep'    => '.',
        'currency_position'=> 'before',
        'currency_space'   => true,
        'php_locale'       => 'nl_NL.UTF-8',
        'intl_locale'      => 'nl-NL',
    ],
    'de' => [
        'name'             => 'German',
        'native_name'      => 'Deutsch',
        'direction'        => 'ltr',
        'date_format'      => 'd.m.Y',
        'date_format_long' => 'j. F Y',
        'time_format'      => 'H:i',
        'datetime_format'  => 'd.m.Y H:i',
        'week_start'       => 1,
        'decimal_sep'      => ',',
        'thousands_sep'    => '.',
        'currency_position'=> 'after',
        'currency_space'   => true,
        'php_locale'       => 'de_DE.UTF-8',
        'intl_locale'      => 'de-DE',
    ],
    'fr' => [
        'name'             => 'French',
        'native_name'      => 'Français',
        'direction'        => 'ltr',
        'date_format'      => 'd/m/Y',
        'date_format_long' => 'j F Y',
        'time_format'      => 'H:i',
        'datetime_format'  => 'd/m/Y H:i',
        'week_start'       => 1,
        'decimal_sep'      => ',',
        'thousands_sep'    => ' ',
        'currency_position'=> 'after',
        'currency_space'   => true,
        'php_locale'       => 'fr_FR.UTF-8',
        'intl_locale'      => 'fr-FR',
    ],
    'es' => [
        'name'             => 'Spanish',
        'native_name'      => 'Español',
        'direction'        => 'ltr',
        'date_format'      => 'd/m/Y',
        'date_format_long' => 'j \d\e F \d\e Y',
        'time_format'      => 'H:i',
        'datetime_format'  => 'd/m/Y H:i',
        'week_start'       => 1,
        'decimal_sep'      => ',',
        'thousands_sep'    => '.',
        'currency_position'=> 'after',
        'currency_space'   => true,
        'php_locale'       => 'es_ES.UTF-8',
        'intl_locale'      => 'es-ES',
    ],
];
