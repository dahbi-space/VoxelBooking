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
        // Resolve field label from translation, fall back to humanized field name
        $translated = __('validation.attributes.' . $field);
        $label = ($translated !== 'validation.attributes.' . $field)
            ? $translated
            : ucfirst(str_replace('_', ' ', $field));

        $strValue = is_string($value) ? trim($value) : '';

        $failed = match ($rule) {
            'required'   => $value === null || $strValue === '',
            'string'     => !is_string($value) && $value !== null,
            'email'      => $strValue !== '' && !filter_var($strValue, FILTER_VALIDATE_EMAIL),
            'integer'    => $strValue !== '' && !ctype_digit(ltrim($strValue, '-')),
            'min'        => $strValue !== '' && mb_strlen($strValue) < (int) $param,
            'max'        => $strValue !== '' && (int) $strValue > (int) $param,
            'max_length' => $strValue !== '' && mb_strlen($strValue) > (int) $param,
            'in'         => $strValue !== '' && !in_array($strValue, explode(',', $param ?? ''), true),
            'date'       => $strValue !== '' && strtotime($strValue) === false,
            'url'        => $strValue !== '' && !filter_var($strValue, FILTER_VALIDATE_URL),
            default      => false,
        };

        if (!$failed) {
            return null;
        }

        return __('validation.' . $rule, ['field' => $label, 'param' => $param ?? '']);
    }
}
