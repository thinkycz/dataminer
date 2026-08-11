<?php

declare(strict_types=1);

namespace App\Scraping;

use App\Jobs\ExecuteScrapeRunJob;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Models\User;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class ScrapeRunService
{
    /**
     * Create and dispatch a mutually exclusive run.
     */
    public function start(Recipe $recipe, RecipeVersion $version, User $user, string $kind): ScrapeRun
    {
        $run = Resolver::resolveDatabaseManager()->transaction(function () use ($recipe, $version, $user, $kind): ScrapeRun {
            $lockedRecipe = Recipe::query()->lockForUpdate()->findOrFail($recipe->getKey());

            $activeExists = $lockedRecipe->runs()->getQuery()
                ->whereIn('status', [ScrapeRun::STATUS_QUEUED, ScrapeRun::STATUS_RUNNING])
                ->exists();

            if ($activeExists) {
                Thrower::default()->message('run', Typer::assertString(\__('A run is already active for this recipe.')))->throw();
            }

            $limits = $kind === ScrapeRun::KIND_TEST
                ? ['rows' => 100, 'bytes' => 5_000_000, 'requests' => 100, 'pages' => 10, 'seconds' => 120]
                : ['rows' => 100_000, 'bytes' => 250_000_000, 'requests' => 10_000, 'pages' => 1_000, 'seconds' => 3_600];

            return ScrapeRun::create([
                'id' => (string) Str::uuid7(),
                'recipe_id' => $recipe->getKey(),
                'recipe_version_id' => $version->getKey(),
                'user_id' => $user->getKey(),
                'kind' => $kind,
                'status' => ScrapeRun::STATUS_QUEUED,
                'progress' => 0,
                'row_count' => 0,
                'byte_count' => 0,
                'request_count' => 0,
                'limits' => $limits,
            ]);
        });

        Resolver::resolveQueueingDispatcher()->dispatch(new ExecuteScrapeRunJob($run->getId()));

        return $run;
    }
}
