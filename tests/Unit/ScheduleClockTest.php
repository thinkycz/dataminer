<?php

declare(strict_types=1);

use App\Models\CollectorSchedule;
use App\Scraping\ScheduleClock;
use Illuminate\Support\Carbon;

/**
 * Build an unsaved schedule for calendar calculations.
 */
function clockSchedule(string $cadence, string $time, int|null $weekday = null): CollectorSchedule
{
    $schedule = new CollectorSchedule();
    $schedule->forceFill([
        'cadence' => $cadence,
        'timezone' => 'Europe/Prague',
        'local_time' => $time,
        'weekday' => $weekday,
    ]);

    return $schedule;
}

\test('spring missing local time is skipped', function (): void {
    $clock = new ScheduleClock();
    $next = $clock->next(\clockSchedule('daily', '02:30'), Carbon::parse('2026-03-28 01:31:00', 'UTC'));

    \expect($next->toDateTimeString())->toBe('2026-03-30 00:30:00');
});

\test('autumn repeated local time is not scheduled twice', function (): void {
    $clock = new ScheduleClock();
    $schedule = \clockSchedule('daily', '02:30');
    $first = $clock->next($schedule, Carbon::parse('2026-10-24 01:00:00', 'UTC'));
    $second = $clock->next($schedule, $first, $clock->slotKey($schedule, $first));

    \expect($first->toDateTimeString())->toBe('2026-10-25 00:30:00')
        ->and($second->toDateTimeString())->toBe('2026-10-26 01:30:00');
});

\test('hourly cadence skips the repeated autumn wall clock slot', function (): void {
    $clock = new ScheduleClock();
    $schedule = \clockSchedule('hourly', '00:30');
    $first = $clock->next($schedule, Carbon::parse('2026-10-25 00:00:00', 'UTC'));
    $second = $clock->next($schedule, $first, $clock->slotKey($schedule, $first));

    \expect($first->toDateTimeString())->toBe('2026-10-25 00:30:00')
        ->and($second->toDateTimeString())->toBe('2026-10-25 02:30:00');
});

\test('advanced cron accepts safe intervals and rejects faster schedules', function (): void {
    $clock = new ScheduleClock();
    $clock->validate('advanced', 'Europe/Prague', '09:05', null, '5,35 * * * *');
    $clock->validate('daily', 'Europe/Prague', '09:05', null);
    \expect(fn() => $clock->validate('advanced', 'Europe/Prague', '09:00', null, '*/5 * * * *'))
        ->toThrow(InvalidArgumentException::class);
    \expect(fn() => $clock->validate('advanced', 'Europe/Prague', '09:00', null, '5,55 * * * *'))
        ->toThrow(InvalidArgumentException::class);
});
