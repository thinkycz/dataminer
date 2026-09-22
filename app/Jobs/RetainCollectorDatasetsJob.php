<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ScrapeRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Thinkycz\LaravelCore\Support\Resolver;

class RetainCollectorDatasetsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Dispatch one dataset per job, preserving legacy history by default.
     */
    public function __construct(private readonly string|null $runId = null) {}

    /**
     * Delete expired artifacts except the latest usable comparison baseline.
     */
    public function handle(): void
    {
        if ($this->runId === null) {
            foreach (ScrapeRun::query()->where('retention_managed', true)->whereNotNull('finished_at')->where('finished_at', '<', \now()->subDays(30))->cursor() as $run) {
                Resolver::resolveDatabaseManager()->transaction(static function () use ($run): void {
                    Resolver::resolveQueueingDispatcher()->dispatch((new self($run->getId()))->afterCommit());
                });
            }

            return;
        }
        $run = ScrapeRun::query()->find($this->runId);
        if (!$run instanceof ScrapeRun || !$run->hasManagedRetention() || $run->getFinishedAt() === null || $run->getFinishedAt()->isAfter(\now()->subDays(30))) {
            return;
        }
        $version = $run->recipeVersion()->getResults();
        $baseline = $version?->runs()->getQuery()->where('complete', true)->where('status', ScrapeRun::STATUS_COMPLETED)->where('kind', '!=', ScrapeRun::KIND_TEST)->orderByDesc('finished_at')->get()->first(static fn(ScrapeRun $candidate): bool => \in_array($candidate->getComparison()['status'] ?? null, ['baseline', 'compared'], true));
        if ($baseline instanceof ScrapeRun && $baseline->getId() === $run->getId()) {
            return;
        }
        if ($run->getArtifactDisk() !== null) {
            Resolver::resolveFilesystemManager()->disk($run->getArtifactDisk())->delete(\array_values(\array_filter([$run->getCsvPath(), $run->getJsonPath()], static fn(string|null $path): bool => $path !== null)));
        }
        Resolver::resolveDatabaseManager()->transaction(static function () use ($run): void {
            $run->rows()->delete();
            $run->columns()->delete();
            $run->update(['csv_path' => null, 'json_path' => null, 'logs' => null, 'error' => null, 'diagnostics' => null, 'retention_managed' => false]);
        });
    }
}
