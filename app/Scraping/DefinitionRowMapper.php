<?php

declare(strict_types=1);

namespace App\Scraping;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Convert source values into canonical scalar rows.
 *
 * @phpstan-import-type FieldShape from RecipeDefinition
 */
final class DefinitionRowMapper
{
    /**
     * @param array<int|string, mixed> $record
     * @param list<FieldShape> $fields
     *
     * @return array<string, bool|float|int|string|null>
     */
    public function map(array $record, array $fields, string $baseUrl): array
    {
        $row = [];
        foreach ($fields as $field) {
            $value = $this->path($record, $field['path']);
            if ($value === null || $value === '') {
                if ($field['required']) { throw new InvalidArgumentException('Required field missing: ' . $field['name']); }
                $row[$field['name']] = null;

                continue;
            }
            if (!\is_scalar($value)) { throw new InvalidArgumentException('Field is not scalar: ' . $field['name']); }
            if (\is_string($value)) {
                foreach ($field['transforms'] ?? [] as $transform) {
                    $value = match ($transform['op']) {
                        'trim' => \mb_trim($value),
                        'lowercase' => \mb_strtolower($value),
                        'uppercase' => \mb_strtoupper($value),
                        'replace' => \str_replace($transform['search'], $transform['value'], $value),
                        'prefix' => $transform['value'] . $value,
                        'suffix' => $value . $transform['value'],
                    };
                }
            }
            $row[$field['name']] = $this->cast($value, $field, $baseUrl);
        }

        return $row;
    }

    /**
     * @param array<int|string, mixed> $record
     */
    public function path(array $record, string $path): mixed
    {
        if ($path === '' || $path === '$') { return $record; }
        if (\array_key_exists($path, $record)) { return $record[$path]; }
        $path = \preg_replace('/^\\$\\.?/', '', $path) ?? $path;
        $path = \preg_replace('/\\[(\\d+)\\]/', '.$1', $path) ?? $path;
        if (\preg_match('/[^A-Za-z0-9_.-]/', $path) === 1) { throw new InvalidArgumentException('Unsupported path syntax.'); }
        $current = $record;
        foreach (\explode('.', $path) as $segment) {
            if (!\is_array($current) || !\array_key_exists($segment, $current)) { return null; }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param FieldShape $field
     */
    private function cast(bool|float|int|string $value, array $field, string $baseUrl): bool|float|int|string
    {
        return match ($field['type']) {
            'string' => (string) $value,
            'number' => $this->number($value, $field['number_locale'] ?? 'dot'),
            'boolean' => $this->boolean($value, $field),
            'date' => $this->date($value, $field['date_format'] ?? null),
            'url' => $this->url((string) $value, $baseUrl),
            default => throw new InvalidArgumentException('Unsupported field type.'),
        };
    }

    /**
     * Parse a fixed number notation.
     */
    private function number(bool|float|int|string $value, string $locale): float|int
    {
        if (\is_bool($value)) { throw new InvalidArgumentException('Boolean is not a number.'); }
        $text = \mb_trim((string) $value);
        if ($locale === 'comma') { $text = \str_replace(['.', ',', ' '], ['', '.', ''], $text); }
        if (!\is_numeric($text)) { throw new InvalidArgumentException('Invalid number.'); }

        $number = (float) $text;
        if (!\is_finite($number)) { throw new InvalidArgumentException('Number is out of range.'); }
        if (\preg_match('/^[+-]?\\d+$/', $text) === 1 && $number >= \PHP_INT_MIN && $number < \PHP_INT_MAX) {
            return (int) $text;
        }

        return $number;
    }

    /**
     * @param FieldShape $field
     */
    private function boolean(bool|float|int|string $value, array $field): bool
    {
        if (\is_bool($value)) { return $value; }
        $text = \mb_strtolower(\mb_trim((string) $value));
        $true = \array_map('mb_strtolower', $field['boolean_true'] ?? ['true', 'yes', '1']);
        $false = \array_map('mb_strtolower', $field['boolean_false'] ?? ['false', 'no', '0']);
        if (\in_array($text, $true, true)) { return true; }
        if (\in_array($text, $false, true)) { return false; }
        throw new InvalidArgumentException('Invalid boolean.');
    }

    /**
     * Parse a date into stable ISO 8601.
     */
    private function date(bool|float|int|string $value, string|null $format): string
    {
        $date = DateTimeImmutable::createFromFormat('!' . ($format ?? 'Y-m-d'), (string) $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date instanceof DateTimeImmutable || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) { throw new InvalidArgumentException('Invalid date.'); }

        return $date->format('Y-m-d');
    }

    /**
     * Resolve and validate a field URL.
     */
    private function url(string $value, string $baseUrl): string
    {
        $url = (new GuardedHttpTransport())->absoluteUrl($baseUrl, $value);
        if (\filter_var($url, \FILTER_VALIDATE_URL) === false || !\in_array(\parse_url($url, \PHP_URL_SCHEME), ['http', 'https'], true)) { throw new InvalidArgumentException('Invalid URL field.'); }

        return $url;
    }
}
