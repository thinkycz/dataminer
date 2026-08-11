<?php

declare(strict_types=1);

namespace App\Scraping;

use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RecipeRepository
{
    /**
     * Find a recipe owned by the user.
     */
    public function findOwned(int $id, User $user): Recipe
    {
        $recipe = Recipe::query()
            ->whereKey($id)
            ->where('user_id', $user->getKey())
            ->first();

        if (!$recipe instanceof Recipe) {
            throw new NotFoundHttpException();
        }

        return $recipe;
    }

    /**
     * Find a version belonging to the owned recipe.
     */
    public function findOwnedVersion(Recipe $recipe, int $versionId): RecipeVersion
    {
        $version = $recipe->versions()->getQuery()->whereKey($versionId)->first();

        if (!$version instanceof RecipeVersion) {
            throw new NotFoundHttpException();
        }

        return $version;
    }

    /**
     * Find a run owned by the user.
     */
    public function findOwnedRun(string $id, User $user): ScrapeRun
    {
        $run = ScrapeRun::query()
            ->whereKey($id)
            ->where('user_id', $user->getKey())
            ->first();

        if (!$run instanceof ScrapeRun) {
            throw new NotFoundHttpException();
        }

        return $run;
    }

    /**
     * Build the owned recipe query.
     *
     * @return Builder<Recipe>
     */
    public function ownedQuery(User $user): Builder
    {
        return Recipe::query()->where('user_id', $user->getKey());
    }
}
