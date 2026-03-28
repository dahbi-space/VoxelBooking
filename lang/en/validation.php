<?php

declare(strict_types=1);

/**
 * English translations: validation messages.
 *
 * Replacements: :field → field label, :param → rule parameter value.
 */
return [
    'required'   => ':field is required.',
    'string'     => ':field must be a string.',
    'email'      => ':field must be a valid email address.',
    'integer'    => ':field must be an integer.',
    'min'        => ':field must be at least :param characters.',
    'max'        => ':field must not exceed :param.',
    'max_length' => ':field must not exceed :param characters.',
    'in'         => ':field must be one of: :param.',
    'date'       => ':field must be a valid date.',
    'url'        => ':field must be a valid URL.',
    'numeric'    => ':field must be a number.',
    'unique'     => ':field has already been taken.',
    'confirmed'  => ':field confirmation does not match.',
    'phone'      => ':field must be a valid phone number.',
    'slug'       => ':field must contain only lowercase letters, numbers, and hyphens.',
    'hex_color'  => ':field must be a valid hex color.',
    'timezone'   => ':field must be a valid timezone.',
    'file'       => ':field must be an uploaded file.',
    'image'      => ':field must be an image.',
    'max_size'   => ':field must not be larger than :param KB.',
];
