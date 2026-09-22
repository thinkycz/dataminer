<?php

declare(strict_types=1);

use App\Jobs\DispatchDueCollectorsJob;
use App\Scraping\ScheduleService;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Resolver;

\test('due collector tick records a UTC heartbeat before dispatching schedules', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00', 'UTC'));
    try {
        $schedules = Mockery::mock(ScheduleService::class);
        $schedules->shouldReceive('dispatchDue')->once()->andReturn(0);

        (new DispatchDueCollectorsJob())->handle($schedules);

        \expect(Resolver::resolveCacheManager()->get('collector_scheduler.last_tick_at'))
            ->toBe('2026-09-22T12:00:00+00:00');
    } finally {
        Carbon::setTestNow();
    }
});
