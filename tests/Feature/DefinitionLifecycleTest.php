<?php

declare(strict_types=1);

use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Scraping\RecipeCandidateService;
use App\Scraping\RecipeDefinition;
use App\Scraping\ScrapeRunner;
use Database\Factories\RecipeFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

\test('a collector can be manually tested activated repaired and exported without AI', function (): void {
    Queue::fake();
    Storage::fake('private');
    Http::preventStrayRequests();
    Http::fake(['https://93.184.216.34/data' => Http::response([['id' => 'one', 'name' => '=unsafe']])]);
    $user = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($user)->createOne(['start_url' => 'https://93.184.216.34/data']);
    $definition = RecipeDefinition::fromArray([
        'schema_version' => 1, 'source_type' => 'json', 'url' => 'https://93.184.216.34/data',
        'fields' => [
            ['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true],
            ['name' => 'name', 'path' => 'name', 'type' => 'string', 'required' => true],
        ],
    ]);
    $service = new RecipeCandidateService();
    $service->saveDraft($recipe, $user, $definition);
    $run = $service->preview($recipe, $user);
    (new ScrapeRunner())->execute($run);
    \expect($run->refresh()->getStatus())->toBe(ScrapeRun::STATUS_COMPLETED);
    $version = $recipe->versions()->firstOrFail();
    \expect($version->getStatus())->toBe(RecipeVersion::STATUS_TESTED);
    $this->be($user, 'users')->post('/recipes/' . $recipe->getKey() . '/versions/' . $version->getKey() . '/approve')->assertRedirect();
    $this->get('/scrape-runs/' . $run->getId() . '/download/csv')->assertOk();
    $service->saveDraft($recipe, $user, $definition);
    \expect($recipe->refresh()->getActiveVersionId())->toBe($version->getKey());
    (new ScrapeRunner())->execute($run);
    Http::assertSentCount(1);
    \expect($run->rows()->count())->toBe(1);
});

\test('setup and browser actions never cross recipe ownership', function (): void {
    $recipe = RecipeFactory::new()->createOne();
    $other = UserFactory::new()->createOne();
    $this->be($other, 'users')->get('/recipes/' . $recipe->getKey() . '/setup')->assertNotFound();
    $this->post('/recipes/' . $recipe->getKey() . '/browser', ['action' => 'open'])->assertNotFound();
    $this->post('/recipes/' . $recipe->getKey() . '/preview')->assertNotFound();
});

\test('CSV and XML definitions preview through the same independent lifecycle', function (string $type, string $body, string $records, string $path): void {
    Queue::fake();
    Storage::fake('private');
    Http::preventStrayRequests();
    Http::fake(['https://93.184.216.34/data' => Http::response($body)]);
    $user = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($user)->createOne(['start_url' => 'https://93.184.216.34/data']);
    $definition = RecipeDefinition::fromArray([
        'schema_version' => 1, 'source_type' => $type, 'url' => 'https://93.184.216.34/data', 'records_path' => $records,
        'fields' => [['name' => 'name', 'path' => $path, 'type' => 'string', 'required' => true]],
    ]);
    $service = new RecipeCandidateService();
    $service->saveDraft($recipe, $user, $definition);
    $run = $service->preview($recipe, $user);
    (new ScrapeRunner())->execute($run);
    \expect($run->refresh()->getStatus())->toBe(ScrapeRun::STATUS_COMPLETED)->and($run->rows()->firstOrFail()->getPayload())->toBe(['name' => 'Example']);
})->with([
    ['csv', "name\nExample\n", '', 'name'],
    ['xml', '<items><item><name>Example</name></item></items>', '/items/item', 'name'],
]);

\test('invalid rows leave incomplete evidence and cannot activate a candidate', function (): void {
    Queue::fake();
    Http::fake(['https://93.184.216.34/data' => Http::response([['name' => 'valid'], ['other' => 'invalid']])]);
    $user = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($user)->createOne();
    $service = new RecipeCandidateService();
    $service->saveDraft($recipe, $user, RecipeDefinition::fromArray([
        'schema_version' => 1, 'source_type' => 'json', 'url' => 'https://93.184.216.34/data',
        'fields' => [['name' => 'name', 'path' => 'name', 'type' => 'string', 'required' => true]],
    ]));
    $run = $service->preview($recipe, $user);
    \expect(fn() => (new ScrapeRunner())->execute($run))->toThrow(RuntimeException::class);
    \expect($run->refresh()->isComplete())->toBeFalse()->and($run->getStatus())->toBe(ScrapeRun::STATUS_FAILED)->and($run->getDiagnostics())->not->toBeEmpty();
    $version = $recipe->versions()->firstOrFail();
    $this->be($user, 'users')->post('/recipes/' . $recipe->getKey() . '/versions/' . $version->getKey() . '/approve', [], $this->inertiaHeaders())->assertSessionHasErrors('version');
});

\test('definition snapshots cannot be rewritten after preview', function (): void {
    Queue::fake();
    $user = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($user)->createOne();
    $service = new RecipeCandidateService();
    $service->saveDraft($recipe, $user, RecipeDefinition::fromArray(['schema_version' => 1, 'source_type' => 'json', 'url' => 'https://93.184.216.34/data', 'fields' => [['name' => 'name', 'path' => 'name', 'type' => 'string', 'required' => true]]]));
    $service->preview($recipe, $user);
    $version = $recipe->versions()->firstOrFail();
    \expect(fn() => $version->update(['definition' => []]))->toThrow(LogicException::class);
});

\test('a deliberately bounded preview can activate without claiming a complete dataset', function (): void {
    Queue::fake();
    Storage::fake('private');
    Http::fake(['*' => Http::response(\array_map(static fn(int $id): array => ['id' => (string) $id], \range(1, 101)))]);
    $user = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($user)->createOne();
    $service = new RecipeCandidateService();
    $service->saveDraft($recipe, $user, RecipeDefinition::fromArray(['schema_version' => 1, 'source_type' => 'json', 'url' => 'https://93.184.216.34/data', 'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]]]));
    $run = $service->preview($recipe, $user);
    (new ScrapeRunner())->execute($run);
    \expect($run->refresh()->getStatus())->toBe(ScrapeRun::STATUS_COMPLETED)->and($run->isComplete())->toBeFalse()->and($run->getRowCount())->toBe(100)->and($recipe->versions()->firstOrFail()->getStatus())->toBe(RecipeVersion::STATUS_TESTED);
});

\test('cancelling during extraction prevents artifact publication', function (): void {
    Queue::fake();
    Storage::fake('private');
    $user = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($user)->createOne();
    $service = new RecipeCandidateService();
    $service->saveDraft($recipe, $user, RecipeDefinition::fromArray(['schema_version' => 1, 'source_type' => 'json', 'url' => 'https://93.184.216.34/data', 'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]]]));
    $run = $service->preview($recipe, $user);
    Http::fake(static function () use ($run) {
        $run->update(['status' => ScrapeRun::STATUS_CANCELLED, 'finished_at' => \now()]);

        return Http::response([['id' => 'cancelled']]);
    });
    (new ScrapeRunner())->execute($run);
    \expect($run->refresh()->getStatus())->toBe(ScrapeRun::STATUS_CANCELLED)->and($run->getJsonPath())->toBeNull()->and($run->rows()->count())->toBe(0);
});

\test('rejecting a candidate during preview cannot resurrect it as tested', function (): void {
    Queue::fake();
    Storage::fake('private');
    $user = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($user)->createOne();
    $service = new RecipeCandidateService();
    $service->saveDraft($recipe, $user, RecipeDefinition::fromArray(['schema_version' => 1, 'source_type' => 'json', 'url' => 'https://93.184.216.34/data', 'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]]]));
    $run = $service->preview($recipe, $user);
    $version = $recipe->versions()->firstOrFail();
    Http::fake(static function () use ($version) {
        $version->update(['status' => RecipeVersion::STATUS_REJECTED]);

        return Http::response([['id' => 'valid']]);
    });
    (new ScrapeRunner())->execute($run);
    \expect($version->refresh()->getStatus())->toBe(RecipeVersion::STATUS_REJECTED);
});

\test('malformed definition arrays return validation errors rather than server errors', function (): void {
    $owner = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($owner)->createOne();
    $this->be($owner, 'users')->postJson('/recipes/' . $recipe->getKey() . '/setup', ['definition' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('definition');
});
