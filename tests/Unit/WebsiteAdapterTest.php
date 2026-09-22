<?php

declare(strict_types=1);

use App\Scraping\RecipeDefinition;
use App\Scraping\WebsiteAdapter;
use Illuminate\Support\Facades\Http;

\test('website rows use the same explicit transforms and required-field validation as feeds', function (): void {
    \config()->set('scraping.browser_service_secret', 'fixture-service-secret');
    Http::preventStrayRequests();
    Http::fake([
        '127.0.0.1:3210/sessions' => Http::response(['sessionId' => 'fixture']),
        '127.0.0.1:3210/sessions/fixture/extract' => Http::response(['rows' => [['price' => '1,25'], ['price' => null]], 'complete' => true, 'diagnostics' => [], 'bytes' => 40, 'requests' => 1, 'pages' => 1]),
        '127.0.0.1:3210/sessions/fixture' => Http::response(['closed' => true]),
    ]);
    $definition = RecipeDefinition::fromArray([
        'schema_version' => 1, 'source_type' => 'website', 'url' => 'https://example.com',
        'website' => ['record_selector' => '.item'],
        'fields' => [['name' => 'price', 'path' => '.price', 'type' => 'number', 'required' => true, 'number_locale' => 'comma']],
    ]);
    $result = (new WebsiteAdapter())->execute($definition, null);
    \expect($result->rows)->toBe([['price' => 1.25]])->and($result->complete)->toBeFalse()->and($result->diagnostics)->not->toBeEmpty();
    Http::assertSentCount(3);
});
