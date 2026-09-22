<?php

declare(strict_types=1);

namespace App\Scraping;

use App\Models\CollectorConnection;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Models\User;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeCandidateService
{
    /**
     * Save a validated mutable draft while preserving the active version.
     */
    public function saveDraft(Recipe $recipe, User $user, RecipeDefinition $definition): void
    {
        $this->assertOwner($recipe, $user);
        (new CollectorConnectionService())->forDefinition($definition, $user);
        $recipe->update(['setup_draft' => $definition->toArray()]);
    }

    /**
     * Snapshot a draft as an immutable candidate and queue a bounded preview.
     */
    public function preview(Recipe $recipe, User $user): ScrapeRun
    {
        $this->assertOwner($recipe, $user);

        return Resolver::resolveDatabaseManager()->transaction(function () use ($recipe, $user): ScrapeRun {
            $draft = Recipe::query()->findOrFail($recipe->getKey())->getSetupDraft();
            $definition = RecipeDefinition::fromArray($draft ?? []);
            if ($definition->getConnectionId() !== null) {
                CollectorConnection::query()->where('user_id', $user->getKey())->lockForUpdate()->findOrFail($definition->getConnectionId());
            }
            $locked = Recipe::query()->lockForUpdate()->findOrFail($recipe->getKey());
            \abort_unless($draft === $locked->getSetupDraft(), 409);
            $latest = $locked->versions()->getQuery()->max('version');
            $version = RecipeVersion::create([
                'recipe_id' => $locked->getKey(),
                'version' => $latest === null ? 1 : Typer::assertInt($latest) + 1,
                'source' => '',
                'definition_format' => 'definition',
                'schema_version' => 1,
                'definition' => $definition->toArray(),
                'checksum' => $definition->checksum(),
                'proposed_columns' => [],
                'generation_reason' => 'manual',
                'status' => RecipeVersion::STATUS_TESTING,
            ]);

            return (new ScrapeRunService())->start($locked, $version, $user, ScrapeRun::KIND_TEST);
        });
    }

    /**
     * Enforce ownership even when called outside an HTTP controller.
     */
    private function assertOwner(Recipe $recipe, User $user): void
    {
        \abort_unless($recipe->user()->whereKey($user->getKey())->exists(), 404);
    }
}
