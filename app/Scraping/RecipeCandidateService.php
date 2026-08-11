<?php

declare(strict_types=1);

namespace App\Scraping;

use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeCandidateService
{
    /**
     * Validate and persist an approved tool call, then dispatch its test.
     */
    public function acceptApprovedToolCall(Request $request): string
    {
        $recipeId = Typer::assertInt($request['recipe_id']);
        $source = Typer::assertString($request['source']);
        $callId = Typer::assertString($request->toolCallId());
        (new RecipeSourceValidator())->validate($source);

        $version = Resolver::resolveDatabaseManager()->transaction(function () use ($recipeId, $source, $callId, $request): RecipeVersion {
            $existing = RecipeVersion::query()->where('approval_call_id', $callId)->first();

            if ($existing instanceof RecipeVersion) {
                return $existing;
            }

            $recipe = Recipe::query()->lockForUpdate()->findOrFail($recipeId);
            $latestVersion = $recipe->versions()->getQuery()->max('version');
            $nextVersion = $latestVersion === null ? 1 : Typer::assertInt($latestVersion) + 1;

            $version = RecipeVersion::create([
                'recipe_id' => $recipe->getKey(),
                'version' => $nextVersion,
                'source' => $source,
                'checksum' => \hash('sha256', $source),
                'proposed_columns' => Typer::assertArray($request['proposed_columns']),
                'generation_summary' => Typer::assertString($request['generation_summary']),
                'generation_reason' => Typer::assertString($request['generation_reason']),
                'status' => RecipeVersion::STATUS_TESTING,
                'approval_call_id' => $callId,
            ]);

            $recipe->update(['status' => Recipe::STATUS_TESTING]);

            return $version;
        });

        if (!$version->runs()->getQuery()->where('kind', 'test')->exists()) {
            $recipe = $version->recipe()->getResults();
            if ($recipe instanceof Recipe) {
                $user = $recipe->user()->getResults();
                if ($user instanceof User) {
                    (new ScrapeRunService())->start($recipe, $version, $user, 'test');
                }
            }
        }

        return 'Approved candidate stored as immutable version ' . $version->getVersion() . ' and its bounded test was queued.';
    }
}
