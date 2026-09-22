<?php

declare(strict_types=1);

use App\Models\CollectorConnection;
use App\Models\CollectorOccurrence;
use App\Models\CollectorSchedule;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Scraping\ScheduleService;
use Database\Factories\CollectorConnectionFactory;
use Database\Factories\RecipeFactory;
use Database\Factories\RecipeVersionFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * @return array{Recipe, RecipeVersion, User}
 */
function schedulableRecipe(): array
{
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
        'approved_at' => Carbon::now('UTC'),
    ]), RecipeVersion::class);
    $recipe->update(['active_version_id' => $version->getKey(), 'status' => Recipe::STATUS_READY]);

    return [$recipe, $version, $user];
}

\afterEach(function (): void {
    Carbon::setTestNow();
});

\test('schedule pins the approved definition and coalesces missed ticks', function (): void {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-09-22 08:01:00', 'UTC'));
    [$recipe, $version, $user] = \schedulableRecipe();
    $service = new ScheduleService();
    $schedule = $service->configure($recipe, $user, 'every_15_minutes', 'Europe/Prague', '09:00', null);
    $due = $schedule->getNextRunAt();
    \expect($due)->not->toBeNull();

    Carbon::setTestNow(Carbon::parse('2026-09-22 12:01:00', 'UTC'));
    \expect($service->dispatchDue())->toBe(1)
        ->and($service->dispatchDue())->toBe(0)
        ->and(CollectorOccurrence::query()->count())->toBe(1)
        ->and(ScrapeRun::query()->count())->toBe(1);

    $occurrence = CollectorOccurrence::query()->firstOrFail();
    \expect($occurrence->getRunId())->not->toBeNull()
        ->and($occurrence->getDueAt()->equalTo($due))->toBeTrue()
        ->and($occurrence->recipeVersion()->getResults()?->getKey())->toBe($version->getKey())
        ->and($schedule->refresh()->getNextRunAt()?->greaterThan(Carbon::now('UTC')))->toBeTrue();
});

\test('schedule rejects a legacy version and cannot be duplicated for one recipe', function (): void {
    [$recipe, $version, $user] = \schedulableRecipe();
    $service = new ScheduleService();
    $first = $service->configure($recipe, $user, 'daily', 'Europe/Prague', '09:00', null);
    $second = $service->configure($recipe, $user, 'weekly', 'Europe/Prague', '09:00', 1);
    \expect($first->getKey())->toBe($second->getKey())
        ->and(CollectorSchedule::query()->count())->toBe(1);

    $legacyRecipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);
    $legacyVersion = Typer::assertInstance(RecipeVersionFactory::new()->for($legacyRecipe)->createOne(['status' => RecipeVersion::STATUS_APPROVED]), RecipeVersion::class);
    $legacyRecipe->update(['active_version_id' => $legacyVersion->getKey()]);
    \expect(fn() => $service->configure($legacyRecipe, $user, 'daily', 'Europe/Prague', '09:00', null))
        ->toThrow(InvalidArgumentException::class);
});

\test('pause and resume move only future slots', function (): void {
    [$recipe, $version, $user] = \schedulableRecipe();
    $service = new ScheduleService();
    $schedule = $service->configure($recipe, $user, 'daily', 'Europe/Prague', '09:00', null);
    $service->pause($recipe, $user);
    \expect($schedule->refresh()->getStatus())->toBe(CollectorSchedule::STATUS_PAUSED)
        ->and($schedule->getNextRunAt())->toBeNull()
        ->and($service->dispatchDue())->toBe(0);
    $service->resume($recipe, $user);
    \expect($schedule->refresh()->getStatus())->toBe(CollectorSchedule::STATUS_ACTIVE)
        ->and($schedule->getNextRunAt())->not->toBeNull();
});

\test('configure and resume reject a connection that is no longer ready', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);
    $connection = Typer::assertInstance(CollectorConnectionFactory::new()->createOne(['user_id' => $user->getKey(), 'origin' => 'https://example.com:443', 'verified_at' => \now()]), CollectorConnection::class);
    $version = Typer::assertInstance(RecipeVersionFactory::new()->for($recipe)->createOne([
        'definition_format' => 'definition',
        'definition' => ['schema_version' => 1, 'source_type' => 'json', 'url' => 'https://example.com/feed', 'connection_id' => $connection->getKey(), 'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]]],
        'status' => RecipeVersion::STATUS_APPROVED,
    ]), RecipeVersion::class);
    $recipe->update(['active_version_id' => $version->getKey(), 'status' => Recipe::STATUS_READY]);
    $service = new ScheduleService();
    $schedule = $service->configure($recipe, $user, 'daily', 'Europe/Prague', '09:00', null);
    $service->pause($recipe, $user);
    $connection->update(['status' => 'expired', 'verified_at' => null]);
    \expect(fn() => $service->resume($recipe, $user))->toThrow(InvalidArgumentException::class)
        ->and($schedule->refresh()->getStatus())->toBe(CollectorSchedule::STATUS_PAUSED)
        ->and(fn() => $service->configure($recipe, $user, 'daily', 'Europe/Prague', '09:00', null))->toThrow(InvalidArgumentException::class);
    \expect($schedule->refresh()->getStatus())->toBe(CollectorSchedule::STATUS_PAUSED);
});
