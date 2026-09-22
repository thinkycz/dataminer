<?php

declare(strict_types=1);

namespace App\Scraping;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * Extract bounded JSON, CSV, and XML feeds.
 *
 * @phpstan-import-type FieldShape from RecipeDefinition
 */
final class FeedAdapter implements SourceAdapterInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function execute(RecipeDefinition $definition, array $headers = []): ExtractionResult
    {
        $recipe = $definition->toArray();
        $type = $definition->getSourceType();
        if (!\in_array($type, ['json', 'csv', 'xml'], true)) { throw new InvalidArgumentException('Feed adapter requires JSON, CSV, or XML.'); }
        $limits = $recipe['limits'];
        $pagination = $recipe['pagination'];
        if (\in_array($pagination['mode'], ['load_more', 'scroll'], true)) { throw new InvalidArgumentException('Interactive pagination needs a browser.'); }
        $url = $definition->getUrl();
        $rows = [];
        $diagnostics = [];
        $bytes = 0;
        $requests = 0;
        $pages = 0;
        $complete = true;
        $seen = [];
        $seenBodies = [];
        $transport = new GuardedHttpTransport();
        $mapper = new DefinitionRowMapper();
        $start = \microtime(true);
        $originalOrigin = $transport->origin($url);
        if (\in_array($pagination['mode'], ['page', 'offset'], true)) {
            $key = $pagination['mode'] === 'page' ? 'page_param' : 'offset_param';
            $url = $this->withQuery($url, Typer::assertString($pagination[$key]), (string) Typer::assertInt($pagination['start']));
        }

        while (true) {
            if ($pages >= $limits['pages'] || $requests >= $limits['requests'] || $limits['seconds'] <= \microtime(true) - $start) {
                $complete = false;
                $diagnostics[] = 'Page, request, or time limit reached.';
                break;
            }
            if (isset($seen[$url])) {
                $complete = false;
                $diagnostics[] = 'Repeated pagination URL.';
                break;
            }
            if ($headers !== [] && $originalOrigin !== $transport->origin($url)) {
                throw new InvalidArgumentException('Authenticated pagination cannot change origin.');
            }
            $seen[$url] = true;
            $response = $transport->get($url, $headers, $limits['bytes'] - $bytes, \max(1, $limits['seconds'] - (int) (\microtime(true) - $start)), $limits['requests'] - $requests);
            $bytes += $response['bytes'];
            $requests += $response['requests'];
            ++$pages;
            $body = $response['body'];
            $bodyHash = \hash('sha256', $body);
            if (isset($seenBodies[$bodyHash])) {
                $complete = false;
                $diagnostics[] = 'Repeated source page.';
                break;
            }
            $seenBodies[$bodyHash] = true;
            $document = null;
            $records = match ($type) {
                'json' => $this->jsonRecords($body, $recipe['records_path'], $mapper, $document),
                'csv' => $this->csvRecords($body, $recipe['csv'] ?? []),
                'xml' => $this->xmlRecords($body, $recipe['records_path'], $this->namespaces($recipe['xml']['namespaces'] ?? []), $recipe['fields']),
            };
            if ($records === [] && $pages === 1 && !$recipe['validation']['allow_empty']) { throw new RuntimeException('Source returned no records.'); }
            foreach ($records as $record) {
                if ($limits['rows'] <= \count($rows)) {
                    $complete = false;
                    $diagnostics[] = 'Row limit reached.';
                    break 2;
                }
                try {
                    $rows[] = $mapper->map($record, $recipe['fields'], $response['url']);
                } catch (InvalidArgumentException $exception) {
                    $complete = false;
                    $diagnostics[] = 'Skipped invalid row: ' . $exception->getMessage();
                }
            }
            if ($pagination['mode'] === 'none' || $records === []) { break; }
            if (\in_array($pagination['mode'], ['page', 'offset'], true)) {
                $key = $pagination['mode'] === 'page' ? 'page_param' : 'offset_param';
                $nextValue = Typer::assertInt($pagination['start']) + $pages * Typer::assertInt($pagination['step']);
                $url = $this->withQuery($definition->getUrl(), Typer::assertString($pagination[$key]), (string) $nextValue);

                continue;
            }
            if ($type !== 'json' || !\is_array($document)) { throw new InvalidArgumentException('Link and cursor pagination require JSON.'); }
            $key = $pagination['mode'] === 'cursor' ? 'cursor_path' : 'next_path';
            $next = $mapper->path($document, Typer::assertString($pagination[$key]));
            if ($next === null || $next === '') { break; }
            if (!\is_string($next) && !\is_int($next)) { throw new InvalidArgumentException('Invalid pagination token.'); }
            $url = $pagination['mode'] === 'cursor'
                ? $this->withQuery($definition->getUrl(), Typer::assertString($pagination['cursor_param']), (string) $next)
                : $transport->absoluteUrl($response['url'], (string) $next);
        }

        if ($rows === [] && !$recipe['validation']['allow_empty']) { throw new RuntimeException('No valid rows extracted.'); }

        return new ExtractionResult($rows, $complete, \array_slice($diagnostics, 0, 100), $bytes, $requests, $pages);
    }

    /**
     * @return list<array<int|string, mixed>>
     */
    private function jsonRecords(string $body, string $path, DefinitionRowMapper $mapper, mixed &$document): array
    {
        $document = \json_decode($body, true, 64, \JSON_THROW_ON_ERROR);
        if (!\is_array($document)) { throw new InvalidArgumentException('JSON feed root must be an array or object.'); }
        $records = $mapper->path($document, \preg_replace('/\\[\\*\\]$/', '', $path) ?? $path);
        if (!\is_array($records) || !\array_is_list($records)) { throw new InvalidArgumentException('JSON records path must select a list.'); }
        $normalized = [];
        foreach ($records as $record) {
            if (!\is_array($record)) { throw new InvalidArgumentException('JSON record must be an object.'); }
            $normalized[] = $record;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return list<array<string, string>>
     */
    private function csvRecords(string $body, array $settings): array
    {
        $encoding = Typer::assertString($settings['encoding'] ?? 'UTF-8');
        if ($encoding !== 'UTF-8') { $body = Typer::assertString(\mb_convert_encoding($body, 'UTF-8', $encoding)); }
        $body = \preg_replace('/^\\xEF\\xBB\\xBF/', '', $body) ?? $body;
        $stream = \fopen('php://temp', 'w+');
        if ($stream === false) { throw new RuntimeException('Cannot open CSV stream.'); }
        try {
            \fwrite($stream, $body);
            \rewind($stream);
            $delimiter = Typer::assertString($settings['delimiter'] ?? ',');
            $headers = \fgetcsv($stream, separator: $delimiter, escape: '');
            if (!\is_array($headers)) { return []; }
            foreach ($headers as $header) { if (!\is_string($header) || $header === '') { throw new InvalidArgumentException('Invalid CSV header.'); } }
            $records = [];
            while (($values = \fgetcsv($stream, separator: $delimiter, escape: '')) !== false) {
                if ($values === [null]) { continue; }
                if (\count($values) !== \count($headers)) { throw new InvalidArgumentException('CSV row width differs from header.'); }
                $record = \array_combine($headers, $values);
                foreach ($record as $key => $value) { $record[$key] = $value ?? ''; }
                $records[] = $record;
            }

            return $records;
        } finally { \fclose($stream); }
    }

    /**
     * @param array<string, string> $namespaces
     * @param list<FieldShape> $fields
     *
     * @return list<array<string, string>>
     */
    private function xmlRecords(string $body, string $path, array $namespaces, array $fields): array
    {
        if ($body === '' || \preg_match('/<!\\s*(?:DOCTYPE|ENTITY)/i', $body) === 1) { throw new InvalidArgumentException('XML DTD and entities are forbidden.'); }
        if (\preg_match('/^[A-Za-z0-9_\\/\\[\\]@.:-]+$/', $path) !== 1) { throw new InvalidArgumentException('Unsupported XML records path.'); }
        $document = new DOMDocument();
        $previous = \libxml_use_internal_errors(true);
        try {
            if (!$document->loadXML($body, \LIBXML_NONET | \LIBXML_NOCDATA)) { throw new InvalidArgumentException('Invalid XML feed.'); }
        } finally {
            \libxml_clear_errors();
            \libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        foreach ($namespaces as $prefix => $uri) { $xpath->registerNamespace($prefix, $uri); }
        $nodes = $xpath->query($path);
        if ($nodes === false) { throw new InvalidArgumentException('Invalid XML records path.'); }
        $records = [];
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) { continue; }
            $record = [];
            foreach ($fields as $field) {
                $fieldPath = $field['path'];
                if ($fieldPath === '' || \str_starts_with($fieldPath, '/') || \str_contains($fieldPath, '..')) { throw new InvalidArgumentException('Invalid relative XML field path.'); }
                $matches = $xpath->query($fieldPath, $node);
                if ($matches === false) { throw new InvalidArgumentException('Invalid XML field path.'); }
                $match = $matches->item(0);
                $record[$fieldPath] = $match instanceof DOMNode ? ($match->textContent ?? '') : '';
            }
            $records[] = $record;
        }

        return $records;
    }

    /**
     * @return array<string, string>
     */
    private function namespaces(mixed $value): array
    {
        if (!\is_array($value)) { throw new InvalidArgumentException('Invalid XML namespaces.'); }
        $namespaces = [];
        foreach ($value as $prefix => $uri) {
            if (!\is_string($prefix) || !\is_string($uri)) { throw new InvalidArgumentException('Invalid XML namespace.'); }
            $namespaces[$prefix] = $uri;
        }

        return $namespaces;
    }

    /**
     * Put a bounded pagination token in a query parameter.
     */
    private function withQuery(string $url, string $key, string $value): string
    {
        $parts = \parse_url($url);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) { throw new InvalidArgumentException('Invalid URL.'); }
        $query = [];
        \parse_str($parts['query'] ?? '', $query);
        $query[$key] = $value;

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . ($parts['path'] ?? '/') . '?' . \http_build_query($query);
    }
}
