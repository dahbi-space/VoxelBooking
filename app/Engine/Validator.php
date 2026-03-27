<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Input validation.
 *
 * Rules: required, string, email, integer, min, max, max_length, in, date, url.
 * Pipe-delimited: 'required|email|max_length:255'
 */
final class Validator
{
    /**
     * Validate data against rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $rules Field => pipe-delimited rules
     * @return array<string, string> Field => first error message (empty = all valid)
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $param = null;

                if (str_contains($rule, ':')) {
                    [$rule, $param] = explode(':', $rule, 2);
                }

                $error = self::checkRule($field, $value, $rule, $param);

                if ($error !== null) {
                    $errors[$field] = $error;
                    break; // First error per field only
                }
            }
        }

        return $errors;
    }

    private static function checkRule(string $field, mixed $value, string $rule, ?string $param): ?string
    {
        $label = ucfirst(str_replace('_', ' ', $field));
        $strValue = is_string($value) ? trim($value) : '';

        return match ($rule) {
            'required' => ($value === null || $strValue === '')
                ? "{$label} is required."
                : null,

            'string' => (!is_string($value) && $value !== null)
                ? "{$label} must be a string."
                : null,

            'email' => ($strValue !== '' && !filter_var($strValue, FILTER_VALIDATE_EMAIL))
                ? "{$label} must be a valid email address."
                : null,

            'integer' => ($strValue !== '' && !ctype_digit(ltrim($strValue, '-')))
                ? "{$label} must be an integer."
                : null,

            'min' => ($strValue !== '' && mb_strlen($strValue) < (int) $param)
                ? "{$label} must be at least {$param} characters."
                : null,

            'max' => ($strValue !== '' && (int) $strValue > (int) $param)
                ? "{$label} must not exceed {$param}."
                : null,

            'max_length' => ($strValue !== '' && mb_strlen($strValue) > (int) $param)
                ? "{$label} must not exceed {$param} characters."
                : null,

            'in' => ($strValue !== '' && !in_array($strValue, explode(',', $param ?? ''), true))
                ? "{$label} must be one of: {$param}."
                : null,

            'date' => ($strValue !== '' && strtotime($strValue) === false)
                ? "{$label} must be a valid date."
                : null,

            'url' => ($strValue !== '' && !filter_var($strValue, FILTER_VALIDATE_URL))
                ? "{$label} must be a valid URL."
                : null,

            default => null,
        };
    }
}
