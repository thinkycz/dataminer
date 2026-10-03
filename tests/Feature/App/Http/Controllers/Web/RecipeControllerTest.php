<?php

declare(strict_types=1);

use App\Ai\Agents\RecipeGenerationAgent;
use App\Ai\RecipeGenerationService;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Scraping\RecipeDefinition;
use Database\Factories\CollectorScheduleFactory;
use Database\Factories\RecipeFactory;
use Database\Factories\RecipeVersionFactory;
use Database\Factories\ScrapeRunFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\ToolChoice;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest cannot access recipes', function (): void {
    $this->get('/collectors')->assertRedirect('/login');
});

\test('old crawler links redirect to the collector workspace', function (): void {
    $user = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($user)->createOne();
    $this->be($user, 'users')->get('/recipes')->assertRedirect('/collectors');
    $this->get('/recipes/create')->assertRedirect('/collectors/create');
    $this->get('/recipes/' . $recipe->getKey())->assertRedirect('/collectors/' . $recipe->getKey());
    $this->get('/recipes/' . $recipe->getKey() . '/setup')->assertRedirect('/collectors/' . $recipe->getKey() . '/setup');
    $this->get('/scrape-runs')->assertRedirect('/runs');
});

\test('user can create and view an owned recipe', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $response = $this->be($user, 'users')->post('/collectors', [
        'name' => 'Product catalog',
        'start_url' => 'https://example.com/products',
        'instructions' => 'Extract each product name and public price.',
    ], $this->inertiaHeaders());

    $recipe = Recipe::query()->firstOrFail();
    $response->assertRedirect('/collectors/' . $recipe->getKey() . '/setup');
    static::assertSame($user->getKey(), $recipe->user()->getResults()?->getKey());

    $this->be($user, 'users')->get('/collectors/' . $recipe->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'collectors/Show');
});

\test('recipe index only contains recipes owned by the current user', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    RecipeFactory::new()->for($user)->createOne(['name' => 'Mine']);
    RecipeFactory::new()->createOne(['name' => 'Not mine']);

    $this->be($user, 'users')->get('/collectors', $this->inertiaHeaders())
        ->assertOk()->assertJsonCount(1, 'props.recipes.data')->assertJsonPath('props.recipes.data.0.name', 'Mine');
});

\test('creation opens a persisted draft for the selected source format', function (string $sourceType): void {
    $user = UserFactory::new()->createOne();
    $response = $this->be($user, 'users')->post('/collectors', [
        'name' => 'Public catalog',
        'start_url' => 'https://example.com/catalog',
        'source_type' => $sourceType,
    ], $this->inertiaHeaders());

    $recipe = Recipe::query()->firstOrFail();
    $response->assertRedirect('/collectors/' . $recipe->getKey() . '/setup');
    \expect($recipe->getSetupDraft())->not->toBeNull();
    $this->get('/collectors/' . $recipe->getKey() . '/setup', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.definition.source_type', $sourceType)
        ->assertJsonPath('props.definition.url', 'https://example.com/catalog');
})->with(['website', 'json', 'csv', 'xml']);

\test('creation rejects an unsupported source format', function (): void {
    $user = UserFactory::new()->createOne();
    $this->be($user, 'users')->from('/collectors/create')->post('/collectors', [
        'name' => 'Public catalog',
        'start_url' => 'https://example.com/catalog',
        'source_type' => 'script',
    ], $this->inertiaHeaders())->assertRedirect('/collectors/create')->assertSessionHasErrors(['source_type']);
    $this->assertDatabaseCount('recipes', 0);
});

\test('disabled generation leaves the recipe draft unchanged and queues nothing', function (): void {
    RecipeGenerationAgent::fake();
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);

    $this->be($user, 'users')->from('/collectors/' . $recipe->getKey())
        ->post('/collectors/' . $recipe->getKey() . '/generate', [], $this->inertiaHeaders())->assertForbidden();

    RecipeGenerationAgent::assertNeverQueued();
    static::assertSame(Recipe::STATUS_DRAFT, $recipe->refresh()->getStatus());
});

\test('user cannot view another users recipe', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->createOne(), Recipe::class);
    $this->be($user, 'users')->get('/collectors/' . $recipe->getKey())->assertNotFound();
});

\test('each version links only to its own completed test sample', function (): void {
    $user = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($user)->createOne();
    $version = RecipeVersionFactory::new()->for($recipe)->createOne(['status' => RecipeVersion::STATUS_TESTED]);
    $otherVersion = RecipeVersionFactory::new()->for($recipe)->createOne(['version' => 2]);
    $sample = ScrapeRunFactory::new()->createOne([
        'recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey(),
        'user_id' => $user->getKey(), 'kind' => ScrapeRun::KIND_TEST, 'status' => ScrapeRun::STATUS_COMPLETED,
    ]);
    ScrapeRunFactory::new()->createOne([
        'recipe_id' => $recipe->getKey(), 'recipe_version_id' => $otherVersion->getKey(),
        'user_id' => $user->getKey(), 'kind' => ScrapeRun::KIND_TEST, 'status' => ScrapeRun::STATUS_FAILED,
    ]);
    ScrapeRunFactory::new()->createOne([
        'recipe_id' => $recipe->getKey(), 'recipe_version_id' => $otherVersion->getKey(),
        'user_id' => $user->getKey(), 'kind' => ScrapeRun::KIND_FULL, 'status' => ScrapeRun::STATUS_COMPLETED,
    ]);
    $this->be($user, 'users')->get('/collectors/' . $recipe->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.versions.0.sample_run_id', null)
        ->assertJsonPath('props.versions.1.sample_run_id', $sample->getId());
});

\test('Inertia setup validation returns to the form with field errors', function (): void {
    $user = UserFactory::new()->createOne();
    $this->be($user, 'users')->from('/collectors/create')->post('/collectors', [
        'name' => '', 'start_url' => 'not-a-url', 'instructions' => '',
    ], $this->inertiaHeaders())->assertRedirect('/collectors/create')->assertSessionHasErrors(['name', 'start_url']);
    $this->get('/collectors/create', $this->inertiaHeaders())->assertOk()
        ->assertJsonPath('component', 'collectors/Create');
    $this->assertDatabaseCount('recipes', 0);
});

\test('disabled generation makes no provider request', function (): void {
    Http::preventStrayRequests();
    Http::fake();
    $user = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($user)->createOne();

    \expect(fn() => (new RecipeGenerationService())->queue($recipe, $user, 'initial'))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    Http::assertNothingSent();
    \expect($recipe->refresh()->getStatus())->toBe(Recipe::STATUS_DRAFT)
        ->and($recipe->getAiConversationId())->toBeNull();
    \expect((new RecipeGenerationAgent())->toolChoice()->mode)->toBe(ToolChoice::auto);
});

\test('collector cards summarize full collections and schedules without per-card queries', function (): void {
    $user = UserFactory::new()->createOne();
    $definition = RecipeDefinition::fromArray(['schema_version' => 1, 'source_type' => 'json', 'url' => 'https://example.com/data', 'fields' => [['name' => 'name', 'path' => 'name', 'type' => 'string', 'required' => true]]]);
    $recipe = RecipeFactory::new()->for($user)->createOne(['setup_draft' => $definition->toArray()]);
    $version = RecipeVersionFactory::new()->for($recipe)->createOne(['definition_format' => 'definition', 'definition' => $definition->toArray(), 'checksum' => $definition->checksum(), 'status' => RecipeVersion::STATUS_TESTED]);
    $run = ScrapeRunFactory::new()->createOne(['user_id' => $user->getKey(), 'recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey(), 'kind' => ScrapeRun::KIND_FULL, 'row_count' => 7, 'status' => ScrapeRun::STATUS_COMPLETED]);
    ScrapeRunFactory::new()->createOne(['user_id' => $user->getKey(), 'recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey(), 'kind' => ScrapeRun::KIND_TEST, 'created_at' => \now()->addSecond()]);
    CollectorScheduleFactory::new()->createOne(['user_id' => $user->getKey(), 'recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey(), 'status' => 'paused']);
    $this->be($user, 'users');
    DB::enableQueryLog();
    $this->get('/collectors', $this->inertiaHeaders())->assertOk()
        ->assertJsonPath('props.recipes.data.0.last_run.id', $run->getId())
        ->assertJsonPath('props.recipes.data.0.last_run.rows', 7)
        ->assertJsonPath('props.recipes.data.0.source_type', 'json')
        ->assertJsonPath('props.recipes.data.0.review_ready', true)
        ->assertJsonPath('props.recipes.data.0.schedule.status', 'paused');
    $singleCount = \count(DB::getQueryLog());
    DB::disableQueryLog();
    RecipeFactory::new()->count(8)->for($user)->create();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get('/collectors', $this->inertiaHeaders())->assertOk()->assertJsonCount(9, 'props.recipes.data');
    \expect(\count(DB::getQueryLog()))->toBe($singleCount);
    DB::disableQueryLog();
});

\test('workspace tabs survive direct navigation and old history links', function (): void {
    $user = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($user)->createOne();
    $this->be($user, 'users')->get('/collectors/' . $recipe->getKey() . '?tab=schedule', $this->inertiaHeaders())->assertJsonPath('props.tab', 'schedule');
    $this->get('/collectors/' . $recipe->getKey() . '/runs', $this->inertiaHeaders())->assertJsonPath('props.tab', 'history');
    $this->get('/collectors/' . $recipe->getKey() . '?tab=unknown', $this->inertiaHeaders())->assertJsonPath('props.tab', 'overview');
});

\test('collector cards identify the saved draft source and preserve the active source while edits are pending', function (): void {
    $user = UserFactory::new()->createOne();
    $draft = RecipeDefinition::fromArray(['schema_version' => 1, 'source_type' => 'csv', 'url' => 'https://example.com/revised.csv', 'fields' => [['name' => 'name', 'path' => 'name', 'type' => 'string', 'required' => true]]]);
    $recipe = RecipeFactory::new()->for($user)->createOne(['start_url' => 'https://example.com/original', 'setup_draft' => $draft->toArray()]);
    $this->be($user, 'users')->get('/collectors', $this->inertiaHeaders())
        ->assertJsonPath('props.recipes.data.0.start_url', 'https://example.com/revised.csv')
        ->assertJsonPath('props.recipes.data.0.source_type', 'csv');
    $active = RecipeDefinition::fromArray(['schema_version' => 1, 'source_type' => 'json', 'url' => 'https://example.com/active.json', 'fields' => [['name' => 'name', 'path' => 'name', 'type' => 'string', 'required' => true]]]);
    $version = RecipeVersionFactory::new()->for($recipe)->createOne(['definition_format' => 'definition', 'definition' => $active->toArray(), 'checksum' => $active->checksum(), 'status' => RecipeVersion::STATUS_APPROVED]);
    $recipe->update(['active_version_id' => $version->getKey()]);
    $this->get('/collectors', $this->inertiaHeaders())
        ->assertJsonPath('props.recipes.data.0.start_url', 'https://example.com/active.json')
        ->assertJsonPath('props.recipes.data.0.source_type', 'json')
        ->assertJsonPath('props.recipes.data.0.review_ready', false);
});
