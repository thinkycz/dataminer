<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ScrapeRun;
use App\Scraping\ScrapeRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExecuteScrapeRunJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum attempts because browser runs are not implicitly repeatable.
     */
    public int $tries = 1;

    /**
     * Worker timeout in seconds.
     */
    public int $timeout = 3_660;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly string $runId) {}

    /**
     * Execute the job.
     */
    public function handle(ScrapeRunner $runner): void
    {
        $run = ScrapeRun::query()->find($this->runId);

        if ($run instanceof ScrapeRun) {
            $runner->execute($run);
        }
    }
}
