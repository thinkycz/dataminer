<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Ai\RecipeGenerationService;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Scraping\RecipeRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeVersionController
{
    /**
     * Activate a successfully tested immutable version.
     */
    public function approve(int $recipe, int $recipeVersion): RedirectResponse
    {
        $repository = new RecipeRepository();
        $owned = $repository->findOwned($recipe, User::mustAuth());
        $version = $repository->findOwnedVersion($owned, $recipeVersion);
        if ($version->getStatus() !== RecipeVersion::STATUS_TESTED) {
            Thrower::default()->message('version', Typer::assertString(\__('Only a tested version can be approved.')))->throw();
        }

        Resolver::resolveDatabaseManager()->transaction(static function () use ($owned, $version): void {
            $owned->versions()->getQuery()->where('status', RecipeVersion::STATUS_APPROVED)->update(['status' => RecipeVersion::STATUS_TESTED]);
            $version->update(['status' => RecipeVersion::STATUS_APPROVED, 'approved_at' => \now()]);
            $owned->update(['active_version_id' => $version->getKey(), 'status' => Recipe::STATUS_READY]);
        });
        Inertia::flash('success', \__('Recipe version approved.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Reject a draft or tested version.
     */
    public function reject(int $recipe, int $recipeVersion): RedirectResponse
    {
        $repository = new RecipeRepository();
        $owned = $repository->findOwned($recipe, User::mustAuth());
        $version = $repository->findOwnedVersion($owned, $recipeVersion);
        if ($owned->getActiveVersionId() === $version->getKey()) {
            Thrower::default()->message('version', Typer::assertString(\__('The active version cannot be rejected.')))->throw();
        }
        $version->update(['status' => RecipeVersion::STATUS_REJECTED]);
        Inertia::flash('success', \__('Recipe version rejected.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Explicitly request an AI repair with sanitized failure context.
     */
    public function repair(int $recipe, int $recipeVersion): RedirectResponse
    {
        $user = User::mustAuth();
        $repository = new RecipeRepository();
        $owned = $repository->findOwned($recipe, $user);
        $version = $repository->findOwnedVersion($owned, $recipeVersion);
        $failedRun = $version->runs()->getQuery()->where('status', ScrapeRun::STATUS_FAILED)->latest()->first();
        (new RecipeGenerationService())->queue($owned, $user, 'repair', $version, $failedRun?->getError());
        Inertia::flash('success', \__('Repair generation queued.'));

        return Resolver::resolveRedirector()->back();
    }
}
