<?php

declare(strict_types=1);

use App\Ai\Tools\TestRecipeCandidateTool;
use App\Jobs\ExecuteScrapeRunJob;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Models\User;
use Database\Factories\RecipeFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;

\test('approved tool persists and dispatches an immutable candidate exactly once', function (): void {
    Queue::fake();
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);
    $request = new Request([
        'recipe_id' => $recipe->getKey(),
        'source' => 'export async function scrape(context) { context.emit({ name: "ok" }); }',
        'proposed_columns' => [['key' => 'name', 'label' => 'Name', 'type' => 'string']],
        'generation_summary' => 'Extract names.',
        'generation_reason' => 'initial',
    ], 'tool-call-1');
    $tool = new TestRecipeCandidateTool();

    $tool->handle($request);
    $tool->handle($request);

    static::assertSame(1, RecipeVersion::query()->count());
    static::assertSame(1, ScrapeRun::query()->count());
    Queue::assertPushed(ExecuteScrapeRunJob::class, 1);
    $version = RecipeVersion::query()->firstOrFail();
    \expect(fn() => $version->update(['source' => 'changed']))->toThrow(LogicException::class);
});
