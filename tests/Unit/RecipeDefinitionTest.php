<?php

declare(strict_types=1);

use App\Scraping\RecipeDefinition;

/**
 * @return array<string, mixed>
 */
function feedDefinition(): array
{
    return [
        'schema_version' => 1,
        'source_type' => 'json',
        'url' => 'https://1.1.1.1/feed',
        'records_path' => 'items',
        'fields' => [
            ['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true],
        ],
    ];
}

\test('definition normalizes defaults and has a stable checksum', function (): void {
    $definition = RecipeDefinition::fromArray(\feedDefinition());
    \expect($definition->getSourceType())->toBe('json')
        ->and($definition->getConnectionId())->toBeNull()
        ->and($definition->toArray()['pagination'])->toBe(['mode' => 'none'])
        ->and($definition->checksum())->toBe(RecipeDefinition::fromArray($definition->toArray())->checksum());
});

\test('definition rejects unknown code-like properties', function (): void {
    $data = \feedDefinition();
    $data['script'] = 'eval("1")';
    \expect(fn(): RecipeDefinition => RecipeDefinition::fromArray($data))->toThrow(InvalidArgumentException::class);
});

\test('definition rejects expressions in field paths through adapter mapping', function (): void {
    $data = \feedDefinition();
    $data['fields'][0]['path'] = 'id.constructor()';
    \expect(fn(): RecipeDefinition => RecipeDefinition::fromArray($data))->toThrow(InvalidArgumentException::class);
});

\test('definition requires a load more selector and allows unique detail comparisons', function (): void {
    $data = \feedDefinition();
    $data['source_type'] = 'website';
    $data['fields'][0]['path'] = '.id';
    $data['website'] = ['record_selector' => '.item', 'detail_fields' => [['name' => 'title', 'path' => 'h1', 'type' => 'string', 'required' => true]]];
    $data['pagination'] = ['mode' => 'load_more', 'max_actions' => 2];
    $data['comparison'] = ['identity' => ['id'], 'fields' => ['title']];
    \expect(fn(): RecipeDefinition => RecipeDefinition::fromArray($data))->toThrow(InvalidArgumentException::class);
    $data['pagination']['next_path'] = '.more';
    \expect(RecipeDefinition::fromArray($data)->toArray()['comparison']['fields'])->toBe(['title']);
});
