<?php

declare(strict_types=1);

namespace App\Scraping;

use App\Models\CollectorSchedule;
use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ScheduleClock
{
    /**
     * Find the next UTC instant, skipping a local wall clock slot already claimed.
     */
    public function next(CollectorSchedule $schedule, Carbon $after, string|null $lastSlotKey = null): Carbon
    {
        $cron = new CronExpression($this->expression($schedule));
        $cursor = $after->copy();
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $candidate = Carbon::instance($cron->getNextRunDate($cursor, 0, false, $schedule->getTimezone()))->utc();
            $key = $this->slotKey($schedule, $candidate);
            $wall = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $key, new DateTimeZone('UTC'));
            if (($lastSlotKey === null || $key > $lastSlotKey) && $wall instanceof DateTimeImmutable && $cron->isDue($wall, 'UTC')) {
                return $candidate;
            }
            $cursor = $candidate;
        }

        throw new InvalidArgumentException('No distinct future schedule slot exists.');
    }

    /**
     * Validate preset settings or an advanced five-field cron expression.
     */
    public function validate(string $cadence, string $timezone, string $localTime, int|null $weekday, string|null $cronExpression = null): void
    {
        if (!\in_array($cadence, ['every_15_minutes', 'hourly', 'daily', 'weekly', 'advanced'], true) ||
            !\in_array($timezone, DateTimeZone::listIdentifiers(), true) ||
            \preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $localTime) !== 1 ||
            ($cadence === 'weekly' && ($weekday === null || $weekday < 1 || $weekday > 7)) ||
            ($cadence !== 'weekly' && $weekday !== null) ||
            ($cadence === 'advanced') !== ($cronExpression !== null)) {
            throw new InvalidArgumentException('Invalid schedule settings.');
        }

        if ($cronExpression !== null) {
            $parts = \preg_split('/\\s+/', \mb_trim($cronExpression));
            if (!\is_array($parts) || \count($parts) !== 5 || !CronExpression::isValidExpression($cronExpression)) {
                throw new InvalidArgumentException('A valid five-field cron expression is required.');
            }
            $minutes = [];
            $minutesExpression = new CronExpression($parts[0] . ' * * * *');
            for ($minute = 0; $minute < 60; ++$minute) {
                $date = new DateTimeImmutable('2026-01-01 00:' . \sprintf('%02d', $minute) . ':00', new DateTimeZone('UTC'));
                if ($minutesExpression->isDue($date, 'UTC')) {
                    $minutes[] = $minute;
                }
            }
            if ($minutes === []) {
                throw new InvalidArgumentException('Cron expression has no minute slots.');
            }
            if (\count($minutes) > 1) {
                for ($index = 0; $index < \count($minutes); ++$index) {
                    $next = $minutes[($index + 1) % \count($minutes)] + ($index === \count($minutes) - 1 ? 60 : 0);
                    if ($next - $minutes[$index] < 15) {
                        throw new InvalidArgumentException('Cron schedules must be at least fifteen minutes apart.');
                    }
                }
            }
        }
    }

    /**
     * Stable local slot key for duplicate prevention.
     */
    public function slotKey(CollectorSchedule $schedule, Carbon $instant): string
    {
        return $instant->copy()->setTimezone($schedule->getTimezone())->format('Y-m-d H:i');
    }

    /**
     * Convert a preset to a standard five-field cron expression.
     */
    private function expression(CollectorSchedule $schedule): string
    {
        $minute = (int) \mb_substr($schedule->getLocalTime(), 3, 2);
        $hour = (int) \mb_substr($schedule->getLocalTime(), 0, 2);

        return match ($schedule->getCadence()) {
            'every_15_minutes' => '*/15 * * * *',
            'hourly' => $minute . ' * * * *',
            'daily' => $minute . ' ' . $hour . ' * * *',
            'weekly' => $minute . ' ' . $hour . ' * * ' . ($schedule->getWeekday() === 7 ? 0 : $schedule->getWeekday()),
            'advanced' => $schedule->getCronExpression() ?? throw new InvalidArgumentException('Missing cron expression.'),
            default => throw new InvalidArgumentException('Unsupported schedule cadence.'),
        };
    }
}
