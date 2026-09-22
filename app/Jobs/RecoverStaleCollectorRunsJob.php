<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ScrapeRun;
use App\Scraping\RunOutcomeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Resolver;

class RecoverStaleCollectorRunsJob implements ShouldQueue
{
    use Queueable;

    /**
     * An unset ID makes this the collection dispatcher.
     */
    public function __construct(private readonly string|null $runId = null) {}

    /**
     * Mark runs that outlived the worker timeout and emit outcome hooks once.
     */
    public function handle(RunOutcomeService $outcomes): void
    {
        $cutoff = Carbon::now('UTC')->subSeconds(4_200);
        if ($this->runId === null) {
            foreach (ScrapeRun::query()->where('retention_managed', true)
                ->whereIn('status', [ScrapeRun::STATUS_QUEUED, ScrapeRun::STATUS_RUNNING])
                ->where(static function (Builder $query) use ($cutoff): void {
                    $query->where('heartbeat_at', '<', $cutoff)
                        ->orWhere(static function (Builder $missing) use ($cutoff): void {
                            $missing->whereNull('heartbeat_at')->where('created_at', '<', $cutoff);
                        });
                })->cursor() as $run) {
                Resolver::resolveDatabaseManager()->transaction(static function () use ($run): void {
                    Resolver::resolveQueueingDispatcher()->dispatch((new self($run->getId()))->afterCommit());
                });
            }

            return;
        }

        $this->recoverOne($this->runId, $cutoff, $outcomes);
    }

    /**
     * Recheck a single run after the queue delay before marking it failed.
     */
    private function recoverOne(string $id, Carbon $cutoff, RunOutcomeService $outcomes): void
    {
        $failed = Resolver::resolveDatabaseManager()->transaction(static function () use ($id, $cutoff): ScrapeRun|null {
            $run = ScrapeRun::query()->whereKey($id)->lockForUpdate()->first();
            if (!$run instanceof ScrapeRun || !$run->hasManagedRetention() || !$run->isActive()) {
                return null;
            }
            $heartbeat = $run->getAttribute('heartbeat_at');
            $timestamp = $heartbeat instanceof Carbon ? $heartbeat : $run->getCreatedAt();
            if ($timestamp->greaterThanOrEqualTo($cutoff)) {
                return null;
            }
            $run->forceFill([
                'status' => ScrapeRun::STATUS_FAILED,
                'complete' => false,
                'error' => 'worker_timeout',
                'finished_at' => Carbon::now('UTC'),
            ])->save();

            return $run;
        });
        if ($failed instanceof ScrapeRun) {
            $outcomes->record($failed);
        }
    }
}
