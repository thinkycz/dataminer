<?php

declare(strict_types=1);

use App\Scraping\CollectorConnectionService;
use App\Scraping\RecipeDefinition;
use Database\Factories\CollectorConnectionFactory;
use Database\Factories\CollectorScheduleFactory;
use Database\Factories\RecipeFactory;
use Database\Factories\RecipeVersionFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Thinkycz\LaravelCore\Support\Resolver;

\test('saved feed credentials are encrypted and bound to owner and exact origin', function (): void {
    $owner = UserFactory::new()->createOne();
    $other = UserFactory::new()->createOne();
    $connection = CollectorConnectionFactory::new()->makeOne(['user_id' => $owner->getKey()]);
    $connection->forceFill(['user_id' => $owner->getKey()])->save();
    $stored = Resolver::resolveDatabaseManager()->table('collector_connections')->where('id', $connection->getKey())->value('credentials');
    \expect($stored)->not->toContain('fixture-token');
    $definition = RecipeDefinition::fromArray(['schema_version' => 1, 'source_type' => 'json', 'url' => 'https://example.com', 'connection_id' => $connection->getKey(), 'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]]]);
    $service = new CollectorConnectionService();
    \expect(fn() => $service->forDefinition($definition, $other))->toThrow(ModelNotFoundException::class);
    $connection->update(['origin' => 'https://example.com:443']);
    \expect($service->headers($service->forDefinition($definition, $owner)))->toBe(['Authorization' => 'Bearer fixture-token']);
    \expect(fn() => $service->forDefinition(RecipeDefinition::fromArray([...$definition->toArray(), 'url' => 'http://example.com']), $owner))->toThrow(InvalidArgumentException::class);
});

\test('browser relay never exposes its service token or session id to another user', function (): void {
    \config()->set('scraping.browser_service_secret', 'fixture-secret-only-server');
    Http::preventStrayRequests();
    Http::fake(['127.0.0.1:3210/sessions' => Http::response(['sessionId' => 'private-session'])]);
    $owner = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($owner)->createOne();
    $this->be($owner, 'users')->postJson('/recipes/' . $recipe->getKey() . '/browser', ['action' => 'open'])->assertExactJson(['opened' => true]);
    $other = UserFactory::new()->createOne();
    $this->be($other, 'users')->postJson('/recipes/' . $recipe->getKey() . '/browser', ['action' => 'snapshot'])->assertNotFound();
    Http::assertSentCount(1);
});

\test('revoking a connection erases credentials and pauses its schedules', function (): void {
    $owner = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($owner)->createOne();
    $connection = CollectorConnectionFactory::new()->createOne(['user_id' => $owner->getKey(), 'verified_at' => \now()]);
    $version = RecipeVersionFactory::new()->createOne([
        'recipe_id' => $recipe->getKey(), 'definition_format' => 'definition',
        'definition' => ['schema_version' => 1, 'source_type' => 'json', 'url' => 'https://example.com', 'connection_id' => $connection->getKey(), 'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]]],
    ]);
    $schedule = CollectorScheduleFactory::new()->createOne(['user_id' => $owner->getKey(), 'recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey()]);
    $this->be($owner, 'users')->post('/recipes/' . $recipe->getKey() . '/connections/' . $connection->getKey() . '/revoke')->assertRedirect();
    \expect($connection->refresh()->getCredentials())->toBe([])
        ->and($connection->getStatus())->toBe('revoked')
        ->and($connection->hasVerifiedState())->toBeFalse()
        ->and($connection->getStateRevision())->toBe(2)
        ->and($schedule->refresh()->getStatus())->toBe('paused');
});

\test('a browser session cannot be saved against a changed source draft', function (): void {
    \config()->set('scraping.browser_service_secret', 'fixture-secret-only-server');
    Http::preventStrayRequests();
    Http::fake([
        '127.0.0.1:3210/sessions' => Http::response(['sessionId' => 'private-session']),
        '127.0.0.1:3210/sessions/private-session/state' => Http::response(['cookies' => [], 'origins' => []]),
    ]);
    $owner = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($owner)->createOne(['start_url' => 'https://example.com']);
    $this->be($owner, 'users')->postJson('/recipes/' . $recipe->getKey() . '/browser', ['action' => 'open'])->assertOk();
    $recipe->update(['start_url' => 'https://example.org']);
    $this->postJson('/recipes/' . $recipe->getKey() . '/browser', ['action' => 'save'])->assertConflict();
    $this->assertDatabaseCount('collector_connections', 0);
});

\test('an old run cannot expire a newly reconnected session', function (): void {
    $owner = UserFactory::new()->createOne();
    $connection = CollectorConnectionFactory::new()->createOne(['user_id' => $owner->getKey(), 'state_revision' => 2, 'verified_at' => \now()]);
    $recipe = RecipeFactory::new()->createOne(['user_id' => $owner->getKey()]);
    $version = RecipeVersionFactory::new()->createOne([
        'recipe_id' => $recipe->getKey(), 'definition_format' => 'definition',
        'definition' => ['schema_version' => 1, 'source_type' => 'json', 'url' => 'https://example.com', 'connection_id' => $connection->getKey(), 'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]]],
    ]);
    $schedule = CollectorScheduleFactory::new()->createOne(['user_id' => $owner->getKey(), 'recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey()]);
    (new CollectorConnectionService())->expire($connection->getKey(), 1);
    \expect($connection->refresh()->getStatus())->toBe('ready')->and($connection->hasVerifiedState())->toBeTrue()->and($schedule->refresh()->getStatus())->toBe('active');
    (new CollectorConnectionService())->expire($connection->getKey(), 2);
    \expect($connection->refresh()->getStatus())->toBe('expired')->and($connection->hasVerifiedState())->toBeFalse()->and($schedule->refresh()->getStatus())->toBe('paused');
});
