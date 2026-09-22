<?php

declare(strict_types=1);

use App\Jobs\RecoverStaleCollectorRunsJob;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Scraping\RunOutcomeService;
use Database\Factories\RecipeFactory;
use Database\Factories\RecipeVersionFactory;
use Database\Factories\ScrapeRunFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Thinkycz\LaravelCore\Support\Typer;

\test('recovery fails only stale collector runs and records their outcome once', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00', 'UTC'));
    try {
        $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
        $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);
        $version = Typer::assertInstance(RecipeVersionFactory::new()->for($recipe)->createOne(), RecipeVersion::class);
        $stale = Typer::assertInstance(ScrapeRunFactory::new()->createOne([
            'recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey(), 'user_id' => $user->getKey(),
            'kind' => 'scheduled', 'status' => ScrapeRun::STATUS_RUNNING,
            'retention_managed' => true, 'heartbeat_at' => Carbon::now('UTC')->subSeconds(4_201),
        ]), ScrapeRun::class);
        $fresh = Typer::assertInstance(ScrapeRunFactory::new()->createOne([
            'recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey(), 'user_id' => $user->getKey(),
            'kind' => 'scheduled', 'status' => ScrapeRun::STATUS_RUNNING,
            'retention_managed' => true, 'heartbeat_at' => Carbon::now('UTC')->subMinute(),
        ]), ScrapeRun::class);
        $outcomes = Mockery::mock(RunOutcomeService::class);
        $outcomes->shouldReceive('record')->once()->with(Mockery::on(static fn(ScrapeRun $run): bool => $run->getId() === $stale->getId()));

        Queue::fake();
        (new RecoverStaleCollectorRunsJob())->handle($outcomes);
        Queue::assertPushed(RecoverStaleCollectorRunsJob::class, 1);
        Queue::assertPushed(RecoverStaleCollectorRunsJob::class, static function (RecoverStaleCollectorRunsJob $job) use ($outcomes): bool {
            $job->handle($outcomes);

            return true;
        });
        (new RecoverStaleCollectorRunsJob())->handle($outcomes);
        Queue::assertPushed(RecoverStaleCollectorRunsJob::class, 1);

        \expect($stale->refresh()->getStatus())->toBe(ScrapeRun::STATUS_FAILED)
            ->and($stale->getError())->toBe('worker_timeout')
            ->and($fresh->refresh()->getStatus())->toBe(ScrapeRun::STATUS_RUNNING);
    } finally {
        Carbon::setTestNow();
    }
});
