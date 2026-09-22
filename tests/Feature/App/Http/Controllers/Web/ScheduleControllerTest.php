<?php

declare(strict_types=1);

use App\Models\CollectorSchedule;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Database\Factories\RecipeFactory;
use Database\Factories\RecipeVersionFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Queue;
use Thinkycz\LaravelCore\Support\Typer;

\test('owner can configure and pause a schedule for an approved definition', function (): void {
    Queue::fake();
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);
    $version = Typer::assertInstance(RecipeVersionFactory::new()->for($recipe)->createOne([
        'definition_format' => 'definition',
        'definition' => [
            'schema_version' => 1,
            'source_type' => 'json',
            'url' => 'https://example.com/feed',
            'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]],
        ],
        'status' => RecipeVersion::STATUS_APPROVED,
    ]), RecipeVersion::class);
    $recipe->update(['active_version_id' => $version->getKey()]);

    $this->be($user, 'users')->post('/collectors/' . $recipe->getKey() . '/schedule', [
        'cadence' => 'advanced',
        'timezone' => 'Europe/Prague',
        'local_time' => '09:05',
        'cron_expression' => '5,35 * * * *',
    ], $this->inertiaHeaders())->assertRedirect();

    $schedule = CollectorSchedule::query()->firstOrFail();
    \expect($schedule->getRecipeVersionId())->toBe($version->getKey())
        ->and($schedule->getCronExpression())->toBe('5,35 * * * *');

    $this->be($user, 'users')->post('/collectors/' . $recipe->getKey() . '/schedule/pause', [], $this->inertiaHeaders())->assertRedirect();
    \expect($schedule->refresh()->getStatus())->toBe(CollectorSchedule::STATUS_PAUSED);
});

\test('an unsafe cron expression is rejected without creating a schedule', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);
    $version = Typer::assertInstance(RecipeVersionFactory::new()->for($recipe)->createOne([
        'definition_format' => 'definition',
        'definition' => [
            'schema_version' => 1,
            'source_type' => 'json',
            'url' => 'https://example.com/feed',
            'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]],
        ],
        'status' => RecipeVersion::STATUS_APPROVED,
    ]), RecipeVersion::class);
    $recipe->update(['active_version_id' => $version->getKey()]);

    $this->be($user, 'users')->from('/collectors/' . $recipe->getKey())->post('/collectors/' . $recipe->getKey() . '/schedule', [
        'cadence' => 'advanced',
        'timezone' => 'Europe/Prague',
        'local_time' => '09:00',
        'cron_expression' => '*/5 * * * *',
    ], $this->inertiaHeaders())->assertRedirect('/collectors/' . $recipe->getKey())->assertSessionHasErrors('schedule');

    $this->assertDatabaseCount('collector_schedules', 0);
});
