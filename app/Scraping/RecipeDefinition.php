<?php

declare(strict_types=1);

namespace App\Scraping;

use InvalidArgumentException;

/**
 * Validated, declarative extraction instructions.
 *
 * @phpstan-type TransformShape array{op:'trim'|'lowercase'|'uppercase'}|array{op:'replace',search:string,value:string}|array{op:'prefix'|'suffix',value:string}
 * @phpstan-type FieldShape array{name:string,path:string,type:string,required:bool,transforms?:list<TransformShape>,number_locale?:string,date_format?:string,boolean_true?:list<string>,boolean_false?:list<string>}
 * @phpstan-type DefinitionShape array{schema_version:int,source_type:string,url:string,connection_id:int|null,records_path:string,fields:list<FieldShape>,pagination:array<string,int|string>,limits:array{rows:int,bytes:int,requests:int,pages:int,seconds:int},validation:array{allow_empty:bool},comparison:array{identity:list<string>,fields:list<string>},website?:array<string,mixed>,csv?:array<string,mixed>,xml?:array<string,mixed>}
 */
final class RecipeDefinition
{
    /**
     * @param DefinitionShape $data
     */
    private function __construct(private readonly array $data) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        self::keys($data, ['schema_version', 'source_type', 'url', 'connection_id', 'records_path', 'fields', 'pagination', 'limits', 'validation', 'comparison', 'website', 'csv', 'xml']);
        if (($data['schema_version'] ?? null) !== 1 || !\in_array($data['source_type'] ?? null, ['website', 'json', 'csv', 'xml'], true)) {
            throw new InvalidArgumentException('Unsupported recipe schema or source type.');
        }
        self::string($data['url'] ?? null, 'url');
        $urlParts = \parse_url($data['url']);
        if (\filter_var($data['url'], \FILTER_VALIDATE_URL) === false || !\is_array($urlParts) || !\in_array($urlParts['scheme'] ?? null, ['http', 'https'], true) || isset($urlParts['user']) || isset($urlParts['pass'])) {
            throw new InvalidArgumentException('Recipe URL must use HTTP(S).');
        }
        if (($data['connection_id'] ?? null) !== null && (!\is_int($data['connection_id']) || $data['connection_id'] < 1)) {
            throw new InvalidArgumentException('Invalid connection ID.');
        }
        $data['connection_id'] ??= null;
        $data['records_path'] ??= '';
        $data['pagination'] ??= ['mode' => 'none'];
        $data['limits'] ??= ['rows' => 100, 'bytes' => 5_000_000, 'requests' => 100, 'pages' => 10, 'seconds' => 120];
        $data['validation'] ??= ['allow_empty' => false];
        self::string($data['records_path'], 'records_path', true);
        if (!\is_array($data['fields'] ?? null) || !\array_is_list($data['fields']) || $data['fields'] === []) {
            throw new InvalidArgumentException('Recipe needs a field list.');
        }
        $names = [];
        foreach ($data['fields'] as $field) {
            self::field($field);
            self::sourcePath($field['path'], $data['source_type']);
            if (isset($names[$field['name']])) {
                throw new InvalidArgumentException('Duplicate field name.');
            }
            $names[$field['name']] = true;
        }
        $data['comparison'] ??= ['identity' => [], 'fields' => []];
        $pagination = self::object($data['pagination'], 'pagination');
        self::keys($pagination, ['mode', 'page_param', 'offset_param', 'cursor_param', 'start', 'step', 'next_path', 'cursor_path', 'next_selector', 'max_actions']);
        $mode = $pagination['mode'] ?? null;
        if (!\in_array($mode, ['none', 'page', 'offset', 'next_link', 'cursor', 'next_page', 'load_more', 'scroll'], true)) {
            throw new InvalidArgumentException('Unsupported pagination mode.');
        }
        if ($mode !== 'none' && $data['source_type'] === 'website' && !\in_array($mode, ['next_page', 'load_more', 'scroll'], true)) {
            throw new InvalidArgumentException('Invalid website pagination mode.');
        }
        if ($data['source_type'] !== 'website' && \in_array($mode, ['next_page', 'load_more', 'scroll'], true)) {
            throw new InvalidArgumentException('Interactive pagination requires a website.');
        }
        if ($data['source_type'] !== 'json' && \in_array($mode, ['next_link', 'cursor'], true)) {
            throw new InvalidArgumentException('Link and cursor pagination require JSON.');
        }
        if (\in_array($data['source_type'], ['csv', 'xml'], true) && $mode !== 'none') {
            throw new InvalidArgumentException('CSV and XML pagination is not supported.');
        }
        $required = match ($mode) {
            'page' => ['page_param', 'start', 'step'],
            'offset' => ['offset_param', 'start', 'step'],
            'next_link', 'next_page' => ['next_path'],
            'cursor' => ['cursor_param', 'cursor_path'],
            'load_more' => ['next_path', 'max_actions'],
            'scroll' => ['max_actions'],
            default => [],
        };
        if (\array_diff(\array_keys($pagination), ['mode', ...$required]) !== [] || \array_diff($required, \array_keys($pagination)) !== []) {
            throw new InvalidArgumentException('Invalid pagination properties.');
        }
        foreach (['page_param', 'offset_param', 'cursor_param', 'next_path', 'cursor_path', 'next_selector'] as $key) {
            if (isset($pagination[$key])) {
                self::string($pagination[$key], $key);
            }
        }
        foreach (['start', 'step', 'max_actions'] as $key) {
            if (isset($pagination[$key]) && (!\is_int($pagination[$key]) || $pagination[$key] < ($key === 'start' ? 0 : 1))) {
                throw new InvalidArgumentException('Invalid pagination number.');
            }
        }
        $limits = self::object($data['limits'], 'limits');
        self::keys($limits, ['rows', 'bytes', 'requests', 'pages', 'seconds']);
        foreach (['rows' => 100_000, 'bytes' => 250_000_000, 'requests' => 10_000, 'pages' => 1_000, 'seconds' => 3_600] as $key => $maximum) {
            if (!\is_int($limits[$key] ?? null) || $limits[$key] < 1 || $limits[$key] > $maximum) {
                throw new InvalidArgumentException('Invalid recipe limit: ' . $key);
            }
        }
        $validation = self::object($data['validation'], 'validation');
        self::keys($validation, ['allow_empty']);
        if (!\is_bool($validation['allow_empty'] ?? null)) {
            throw new InvalidArgumentException('Invalid empty result policy.');
        }
        if (isset($data['website'])) {
            $website = self::object($data['website'], 'website');
            self::keys($website, ['record_selector', 'detail_url_selector', 'detail_fields', 'signed_in_selector']);
            self::string($website['record_selector'] ?? null, 'record_selector');
            if (isset($website['detail_url_selector'])) {
                self::string($website['detail_url_selector'], 'detail_url_selector');
            }
            if (isset($website['signed_in_selector'])) {
                self::string($website['signed_in_selector'], 'signed_in_selector');
            }
            if (isset($website['detail_fields'])) {
                if (!\is_array($website['detail_fields']) || !\array_is_list($website['detail_fields'])) {
                    throw new InvalidArgumentException('Invalid detail fields.');
                }
                foreach ($website['detail_fields'] as $field) {
                    self::field($field);
                    self::sourcePath($field['path'], 'website');
                    if (isset($names[$field['name']])) { throw new InvalidArgumentException('Duplicate field name.'); }
                    $names[$field['name']] = true;
                }
            }
        }
        $comparison = self::object($data['comparison'], 'comparison');
        self::keys($comparison, ['identity', 'fields']);
        foreach (['identity', 'fields'] as $key) {
            if (!\is_array($comparison[$key] ?? null) || !\array_is_list($comparison[$key])) {
                throw new InvalidArgumentException('Invalid comparison fields.');
            }
            foreach ($comparison[$key] as $name) {
                if (!\is_string($name) || !isset($names[$name])) {
                    throw new InvalidArgumentException('Comparison references an unknown field.');
                }
            }
        }
        if ($data['source_type'] === 'website' && !isset($data['website'])) {
            throw new InvalidArgumentException('Website source needs selectors.');
        }
        if ($data['source_type'] !== 'website' && isset($data['website'])) {
            throw new InvalidArgumentException('Website selectors require a website source.');
        }
        if ($data['source_type'] === 'csv' && $data['records_path'] !== '') {
            throw new InvalidArgumentException('CSV records path must be empty.');
        }
        if (isset($data['csv'])) {
            if ($data['source_type'] !== 'csv') { throw new InvalidArgumentException('CSV settings require CSV source.'); }
            $csv = self::object($data['csv'], 'csv');
            self::keys($csv, ['delimiter', 'encoding']);
            if (isset($csv['delimiter']) && (!\is_string($csv['delimiter']) || \mb_strlen($csv['delimiter'], '8bit') !== 1 || \in_array($csv['delimiter'], ["\n", "\r", '"'], true))) { throw new InvalidArgumentException('Invalid CSV delimiter.'); }
            if (isset($csv['encoding']) && !\in_array($csv['encoding'], ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true)) { throw new InvalidArgumentException('Invalid CSV encoding.'); }
        }
        if (isset($data['xml'])) {
            if ($data['source_type'] !== 'xml') { throw new InvalidArgumentException('XML settings require XML source.'); }
            $xml = self::object($data['xml'], 'xml');
            self::keys($xml, ['namespaces']);
            $namespaces = self::object($xml['namespaces'] ?? null, 'XML namespaces');
            foreach ($namespaces as $prefix => $uri) {
                if (\preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $prefix) !== 1) { throw new InvalidArgumentException('Invalid XML prefix.'); }
                self::string($uri, 'XML namespace');
            }
        }

        self::assertShape($data);

        return new self($data);
    }

    /**
     * @return DefinitionShape
     */
    public function toArray(): array { return $this->data; }

    /**
     * Hash the canonical definition.
     */
    public function checksum(): string { return \hash('sha256', \json_encode($this->canonical($this->data), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)); }

    /**
     * Source type.
     */
    public function getSourceType(): string { return $this->data['source_type']; }

    /**
     * Starting URL.
     */
    public function getUrl(): string { return $this->data['url']; }

    /**
     * Optional credential connection identifier.
     */
    public function getConnectionId(): int|null { return $this->data['connection_id']; }

    /**
     * @param array<string, int> $maximum
     */
    public function withLimits(array $maximum): self
    {
        $data = $this->data;
        foreach (['rows', 'bytes', 'requests', 'pages', 'seconds'] as $key) {
            if (isset($maximum[$key])) {
                if ($maximum[$key] < 1) { throw new InvalidArgumentException('Invalid maximum limit.'); }
                $data['limits'][$key] = \min($data['limits'][$key], $maximum[$key]);
            }
        }

        return self::fromArray($data);
    }

    /**
     * Capture the validated nested shape for static analysis.
     *
     * @param array<string, mixed> $data
     *
     * @phpstan-assert DefinitionShape $data
     */
    private static function assertShape(array $data): void
    {
        if (!\is_int($data['schema_version'] ?? null) || !\is_string($data['source_type'] ?? null) || !\is_string($data['url'] ?? null) || !\is_string($data['records_path'] ?? null) || !\is_array($data['fields'] ?? null) || !\is_array($data['pagination'] ?? null) || !\is_array($data['limits'] ?? null) || !\is_array($data['validation'] ?? null) || !\is_array($data['comparison'] ?? null)) {
            throw new InvalidArgumentException('Invalid definition shape.');
        }
    }

    /**
     * @phpstan-assert FieldShape $value
     */
    private static function field(mixed $value): void
    {
        $field = self::object($value, 'field');
        self::keys($field, ['name', 'path', 'type', 'required', 'transforms', 'number_locale', 'date_format', 'boolean_true', 'boolean_false']);
        self::string($field['name'] ?? null, 'field name');
        if (\preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,119}$/', $field['name']) !== 1) {
            throw new InvalidArgumentException('Invalid field name.');
        }
        self::string($field['path'] ?? null, 'field path', true);
        if (!\in_array($field['type'] ?? null, ['string', 'number', 'boolean', 'date', 'url'], true) || !\is_bool($field['required'] ?? null)) {
            throw new InvalidArgumentException('Invalid field type or required flag.');
        }
        if (isset($field['number_locale']) && !\in_array($field['number_locale'], ['dot', 'comma'], true)) { throw new InvalidArgumentException('Invalid number locale.'); }
        if (isset($field['date_format'])) { self::string($field['date_format'], 'date format'); }
        foreach (['boolean_true', 'boolean_false'] as $key) {
            if (isset($field[$key]) && (!\is_array($field[$key]) || !\array_is_list($field[$key]) || \array_filter($field[$key], static fn(mixed $item): bool => !\is_string($item)) !== [])) { throw new InvalidArgumentException('Invalid boolean mapping.'); }
        }
        if (isset($field['transforms'])) {
            if (!\is_array($field['transforms']) || !\array_is_list($field['transforms'])) {
                throw new InvalidArgumentException('Invalid transforms.');
            }
            foreach ($field['transforms'] as $transform) {
                $item = self::object($transform, 'transform');
                self::keys($item, ['op', 'search', 'value']);
                if (!\in_array($item['op'] ?? null, ['trim', 'lowercase', 'uppercase', 'replace', 'prefix', 'suffix'], true)) {
                    throw new InvalidArgumentException('Unsupported transform.');
                }
                $needed = $item['op'] === 'replace' ? ['op', 'search', 'value'] : (\in_array($item['op'], ['prefix', 'suffix'], true) ? ['op', 'value'] : ['op']);
                if (\array_diff(\array_keys($item), $needed) !== [] || \array_diff($needed, \array_keys($item)) !== []) {
                    throw new InvalidArgumentException('Invalid transform properties.');
                }
                foreach (['search', 'value'] as $key) {
                    if (isset($item[$key])) { self::string($item[$key], $key, true); }
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function object(mixed $value, string $name): array
    {
        if (!\is_array($value) || ($value !== [] && \array_is_list($value))) { throw new InvalidArgumentException('Invalid ' . $name . '.'); }
        $result = [];
        foreach ($value as $key => $item) {
            if (!\is_string($key)) { throw new InvalidArgumentException('Invalid ' . $name . ' key.'); }
            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string> $allowed
     */
    private static function keys(array $data, array $allowed): void
    {
        if (\array_diff(\array_keys($data), $allowed) !== []) { throw new InvalidArgumentException('Unknown recipe property.'); }
    }

    /**
     * @phpstan-assert string $value
     */
    private static function string(mixed $value, string $name, bool $empty = false): void
    {
        if (!\is_string($value) || (!$empty && $value === '') || \mb_strlen($value) > 2_000 || \preg_match('/[\\x00-\\x1F]/', $value) === 1) {
            throw new InvalidArgumentException('Invalid ' . $name . '.');
        }
    }

    /**
     * Validate selectors and paths without permitting expressions or calls.
     */
    private static function sourcePath(string $path, string $type): void
    {
        $valid = match ($type) {
            'json' => \preg_match('/^\\$?(?:\\.?[A-Za-z_][A-Za-z0-9_-]*|\\[\\d+\\])*$/', $path) === 1,
            'csv' => \preg_match('/^[A-Za-z0-9_. -]+$/', $path) === 1,
            'xml' => \preg_match('/^[A-Za-z0-9_\\/\\[\\]@.:-]+$/', $path) === 1,
            'website' => !\str_contains($path, '{') && !\str_contains($path, '}') && !\str_contains($path, ';') && !\str_contains($path, '\\'),
            default => false,
        };
        if (!$valid) { throw new InvalidArgumentException('Invalid field path.'); }
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private function canonical(array $value): array
    {
        if (!\array_is_list($value)) { \ksort($value); }
        foreach ($value as $key => $item) {
            if (\is_array($item)) { $value[$key] = $this->canonical($item); }
        }

        return $value;
    }
}
