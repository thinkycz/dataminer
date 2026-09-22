<?php

declare(strict_types=1);

namespace App\Scraping;

use App\Jobs\SendCollectorOutcomeJob;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRow;
use App\Models\ScrapeRun;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class RunOutcomeService
{
    /**
     * Record immutable comparisons and notify only meaningful transitions.
     */
    public function record(ScrapeRun $run): void
    {
        if ($run->getKind() === ScrapeRun::KIND_TEST || $run->getStatus() === ScrapeRun::STATUS_CANCELLED) {
            return;
        }
        $recipe = $run->recipe()->getResults();
        $version = $run->recipeVersion()->getResults();
        if (!$recipe instanceof Recipe || !$version instanceof RecipeVersion || $version->getDefinitionFormat() === 'legacy_js') {
            return;
        }
        Resolver::resolveDatabaseManager()->transaction(function () use ($run, $recipe, $version): void {
            $locked = Recipe::query()->lockForUpdate()->findOrFail($recipe->getKey());
            if ($run->refresh()->getComparison() !== null) {
                return;
            }
            $kind = \str_contains($run->getError() ?? '', 'auth_expired') || \preg_match('/HTTP (401|403)/', $run->getError() ?? '') === 1 ? 'session_expired' : 'failure';
            $comparison = ['status' => 'incomplete', 'added' => 0, 'changed' => 0, 'missing' => 0];
            if ($run->getStatus() === ScrapeRun::STATUS_COMPLETED && $run->isComplete()) {
                $definition = $version->getDefinition();
                if ($definition === null) {
                    return;
                }
                $settings = $definition->toArray()['comparison'];
                $baseline = $version->runs()->getQuery()->where('status', ScrapeRun::STATUS_COMPLETED)->where('complete', true)->where('kind', '!=', ScrapeRun::KIND_TEST)->where('id', '!=', $run->getId())->whereNotNull('comparison')->orderByDesc('finished_at')->get()->first(static fn(ScrapeRun $candidate): bool => \in_array($candidate->getComparison()['status'] ?? null, ['baseline', 'compared'], true));
                $previous = $baseline instanceof ScrapeRun ? $baseline->rows()->getQuery()->orderBy('sequence')->cursor()->map(static fn(ScrapeRow $row): array => $row->getPayload()) : [];
                $comparison = (new DatasetComparisonService())->compare($previous, $run->rows()->getQuery()->cursor()->map(static fn(ScrapeRow $row): array => $row->getPayload()), $settings['identity'], $settings['fields']);
                if ($baseline === null && $comparison['status'] === 'compared') {
                    $comparison = ['status' => 'baseline', 'added' => 0, 'changed' => 0, 'missing' => 0];
                }
                $kind = $comparison['status'] === 'compared' && $comparison['added'] + $comparison['changed'] + $comparison['missing'] > 0 ? 'changes' : 'success';
            }
            $run->update(['comparison' => $comparison]);
            $prior = $locked->getLastOutcome();
            $locked->update(['last_outcome' => $kind]);
            $event = $kind === 'success' && \in_array($prior, ['failure', 'session_expired'], true) ? 'recovery' : $kind;
            if ($event === 'success' || (\in_array($event, ['failure', 'session_expired'], true) && $prior === $event)) {
                return;
            }
            $inserted = Resolver::resolveDatabaseManager()->table('collector_events')->insertOrIgnore([
                'recipe_id' => $recipe->getKey(), 'run_id' => $run->getId(), 'kind' => $event,
                'summary' => Typer::assertString(\json_encode($comparison)), 'created_at' => \now(), 'updated_at' => \now(),
            ]);
            if ($inserted > 0 && $locked->wantsEmailNotifications()) {
                Resolver::resolveQueueingDispatcher()->dispatch((new SendCollectorOutcomeJob($locked->getKey(), $event))->afterCommit());
            }
        });
    }
}
