<?php

declare(strict_types=1);

use App\Scraping\DefinitionRowMapper;
use App\Scraping\FeedAdapter;
use App\Scraping\RecipeDefinition;
use Illuminate\Support\Facades\Http;

\test('JSON feed extracts typed canonical rows', function (): void {
    Http::fake(['*' => Http::response(['items' => [['id' => 'A', 'price' => '1,25']]])]);
    $definition = RecipeDefinition::fromArray([
        'schema_version' => 1,
        'source_type' => 'json',
        'url' => 'https://1.1.1.1/feed',
        'records_path' => 'items',
        'fields' => [
            ['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true],
            ['name' => 'price', 'path' => 'price', 'type' => 'number', 'number_locale' => 'comma', 'required' => true],
        ],
    ]);
    $result = (new FeedAdapter())->execute($definition);
    \expect($result->rows)->toBe([['id' => 'A', 'price' => 1.25]])
        ->and($result->complete)->toBeTrue()
        ->and($result->requests)->toBe(1);
});

\test('CSV and XML feeds extract rows', function (string $type, string $body, string $path): void {
    Http::fake(['*' => Http::response($body)]);
    $definition = RecipeDefinition::fromArray([
        'schema_version' => 1,
        'source_type' => $type,
        'url' => 'https://1.1.1.1/feed',
        'records_path' => $path,
        'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]],
    ]);
    \expect((new FeedAdapter())->execute($definition)->rows)->toBe([['id' => 'A']]);
})->with([
    'csv' => ['csv', "id\nA\n", ''],
    'xml' => ['xml', '<root><item><id>A</id></item></root>', '/root/item'],
]);

\test('XML external entities are rejected', function (): void {
    Http::fake(['*' => Http::response('<!DOCTYPE root [<!ENTITY x SYSTEM "file:///etc/passwd">]><root><item><id>&x;</id></item></root>')]);
    $definition = RecipeDefinition::fromArray([
        'schema_version' => 1,
        'source_type' => 'xml',
        'url' => 'https://1.1.1.1/feed',
        'records_path' => '/root/item',
        'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]],
    ]);
    \expect(fn() => (new FeedAdapter())->execute($definition))->toThrow(InvalidArgumentException::class);
});

\test('CSV preserves spaced headers and decodes a fixed legacy encoding', function (): void {
    Http::fake(['*' => Http::response("Product Name;Price\nCaf\xe9;1,25\n")]);
    $definition = RecipeDefinition::fromArray([
        'schema_version' => 1,
        'source_type' => 'csv',
        'url' => 'https://1.1.1.1/feed',
        'fields' => [
            ['name' => 'name', 'path' => 'Product Name', 'type' => 'string', 'required' => true],
            ['name' => 'price', 'path' => 'Price', 'type' => 'string', 'required' => true],
        ],
        'csv' => ['delimiter' => ';', 'encoding' => 'ISO-8859-1'],
    ]);
    \expect((new FeedAdapter())->execute($definition)->rows)->toBe([['name' => 'Café', 'price' => '1,25']]);
});

\test('namespaced XML resolves nested and attribute field paths', function (): void {
    Http::fake(['*' => Http::response('<x:root xmlns:x="urn:test"><x:item code="A"><x:detail><x:name>Alpha</x:name></x:detail></x:item></x:root>')]);
    $definition = RecipeDefinition::fromArray([
        'schema_version' => 1,
        'source_type' => 'xml',
        'url' => 'https://1.1.1.1/feed',
        'records_path' => '/x:root/x:item',
        'fields' => [
            ['name' => 'code', 'path' => '@code', 'type' => 'string', 'required' => true],
            ['name' => 'name', 'path' => 'x:detail/x:name', 'type' => 'string', 'required' => true],
        ],
        'xml' => ['namespaces' => ['x' => 'urn:test']],
    ]);
    \expect((new FeedAdapter())->execute($definition)->rows)->toBe([['code' => 'A', 'name' => 'Alpha']]);
});

\test('page pagination starts at the configured value and repeated content is incomplete', function (): void {
    Http::fake(['*' => Http::response(['items' => [['id' => 'A']]])]);
    $definition = RecipeDefinition::fromArray([
        'schema_version' => 1,
        'source_type' => 'json',
        'url' => 'https://1.1.1.1/feed',
        'records_path' => 'items',
        'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]],
        'pagination' => ['mode' => 'page', 'page_param' => 'page', 'start' => 3, 'step' => 1],
    ]);
    $result = (new FeedAdapter())->execute($definition);
    Http::assertSent(fn($request): bool => $request->url() === 'https://1.1.1.1/feed?page=3');
    \expect($result->rows)->toBe([['id' => 'A']])->and($result->complete)->toBeFalse();
});

\test('HTTP auth failures expose stable expiry reason', function (): void {
    Http::fake(['*' => Http::response('', 401)]);
    $definition = RecipeDefinition::fromArray([
        'schema_version' => 1,
        'source_type' => 'json',
        'url' => 'https://1.1.1.1/feed',
        'records_path' => 'items',
        'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]],
    ]);
    \expect(fn() => (new FeedAdapter())->execute($definition))->toThrow(RuntimeException::class, 'auth_expired');
});

\test('dates reject rollover and numbers reject nonfinite magnitude', function (): void {
    $mapper = new DefinitionRowMapper();
    \expect(fn() => $mapper->map(['value' => '2026-02-30'], [['name' => 'value', 'path' => 'value', 'type' => 'date', 'required' => true]], 'https://1.1.1.1/'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => $mapper->map(['value' => '1e9999'], [['name' => 'value', 'path' => 'value', 'type' => 'number', 'required' => true]], 'https://1.1.1.1/'))->toThrow(InvalidArgumentException::class);
});

\test('JSON follows explicit offset cursor and next-link pagination to exhaustion', function (array $pagination, string $nextUrl): void {
    Http::preventStrayRequests();
    Http::fakeSequence()
        ->push(['items' => [['id' => 'A']], 'next' => $pagination['mode'] === 'cursor' ? 'two' : '/feed?next=two'])
        ->push(['items' => [['id' => 'B']], 'next' => null])
        ->push(['items' => []]);
    $definition = RecipeDefinition::fromArray([
        'schema_version' => 1, 'source_type' => 'json', 'url' => 'https://1.1.1.1/feed', 'records_path' => 'items',
        'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]],
        'pagination' => $pagination,
    ]);
    $result = (new FeedAdapter())->execute($definition);
    Http::assertSent(fn($request): bool => $nextUrl === $request->url());
    \expect($result->rows)->toBe([['id' => 'A'], ['id' => 'B']])->and($result->complete)->toBeTrue();
})->with([
    [['mode' => 'offset', 'offset_param' => 'skip', 'start' => 0, 'step' => 1], 'https://1.1.1.1/feed?skip=1'],
    [['mode' => 'cursor', 'cursor_param' => 'cursor', 'cursor_path' => 'next'], 'https://1.1.1.1/feed?cursor=two'],
    [['mode' => 'next_link', 'next_path' => 'next'], 'https://1.1.1.1/feed?next=two'],
]);
