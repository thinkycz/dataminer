<?php

declare(strict_types=1);

use App\Jobs\ExecuteScrapeRunJob;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRow;
use App\Models\ScrapeRun;
use App\Models\ScrapeRunColumn;
use App\Models\User;
use Database\Factories\RecipeFactory;
use Database\Factories\RecipeVersionFactory;
use Database\Factories\ScrapeRunFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Queue;
use Thinkycz\LaravelCore\Support\Typer;

\test('manual run uses the active version and queues no AI work', function (): void {
    Queue::fake();
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);
    $version = Typer::assertInstance(RecipeVersionFactory::new()->for($recipe)->createOne(['status' => RecipeVersion::STATUS_APPROVED]), RecipeVersion::class);
    $recipe->update(['active_version_id' => $version->getKey(), 'status' => Recipe::STATUS_READY]);

    $response = $this->be($user, 'users')->post('/recipes/' . $recipe->getKey() . '/runs/start');
    $run = ScrapeRun::query()->firstOrFail();
    $response->assertRedirect('/scrape-runs/' . $run->getId());
    Queue::assertPushed(ExecuteScrapeRunJob::class, 1);
});

\test('run detail shows owned dataset rows and columns', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);
    $version = Typer::assertInstance(RecipeVersionFactory::new()->for($recipe)->createOne(), RecipeVersion::class);
    $run = Typer::assertInstance(ScrapeRunFactory::new()->createOne([
        'recipe_id' => $recipe->getKey(),
        'recipe_version_id' => $version->getKey(),
        'user_id' => $user->getKey(),
        'status' => ScrapeRun::STATUS_COMPLETED,
        'row_count' => 1,
    ]), ScrapeRun::class);
    ScrapeRunColumn::query()->create(['run_id' => $run->getId(), 'key' => 'name', 'label' => 'Name', 'type' => 'string', 'position' => 0]);
    ScrapeRow::query()->create(['run_id' => $run->getId(), 'sequence' => 1, 'payload' => ['name' => 'Example']]);

    $this->be($user, 'users')->get('/scrape-runs/' . $run->getId(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'runs/Show')->assertJsonPath('props.rows.data.0.payload.name', 'Example');
});

\test('another user cannot view or cancel an owned run', function (): void {
    $run = Typer::assertInstance(ScrapeRunFactory::new()->createOne(), ScrapeRun::class);
    $other = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $this->be($other, 'users')->get('/scrape-runs/' . $run->getId())->assertNotFound();
    $this->be($other, 'users')->post('/scrape-runs/' . $run->getId() . '/cancel')->assertNotFound();
});
