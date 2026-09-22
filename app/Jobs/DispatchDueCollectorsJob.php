<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Scraping\ScheduleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Resolver;

class DispatchDueCollectorsJob implements ShouldQueue
{
    use Queueable;

    /**
     * A scheduler tick only claims due slots and queues runs.
     */
    public function handle(ScheduleService $schedules): void
    {
        Resolver::resolveCacheManager()->put('collector_scheduler.last_tick_at', Carbon::now('UTC')->toIso8601String(), 3_600);
        $schedules->dispatchDue();
    }
}
