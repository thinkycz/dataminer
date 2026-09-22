<?php

declare(strict_types=1);

namespace App\Scraping;

use App\Models\CollectorConnection;
use App\Models\CollectorOccurrence;
use App\Models\CollectorSchedule;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Exceptions\ValidationException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class ScheduleService
{
    /**
     * Save one schedule for an owned recipe and pin its approved version.
     */
    public function configure(Recipe $recipe, User $user, string $cadence, string $timezone, string $localTime, int|null $weekday, string|null $cronExpression = null): CollectorSchedule
    {
        $clock = new ScheduleClock();
        $clock->validate($cadence, $timezone, $localTime, $weekday, $cronExpression);

        return Resolver::resolveDatabaseManager()->transaction(function () use ($recipe, $user, $cadence, $timezone, $localTime, $weekday, $cronExpression, $clock): CollectorSchedule {
            $schedule = CollectorSchedule::query()->where('recipe_id', $recipe->getKey())->lockForUpdate()->first();
            $version = $this->approvedVersion($recipe->refresh(), $user);
            if (!$schedule instanceof CollectorSchedule) {
                $schedule = new CollectorSchedule();
                $schedule->forceFill(['recipe_id' => $recipe->getKey(), 'user_id' => $user->getKey()]);
            }

            $schedule->forceFill([
                'recipe_version_id' => $version->getKey(),
                'cadence' => $cadence,
                'timezone' => $timezone,
                'local_time' => $localTime,
                'cron_expression' => $cronExpression,
                'weekday' => $weekday,
                'status' => CollectorSchedule::STATUS_ACTIVE,
                'last_slot_key' => null,
            ]);
            $schedule->setAttribute('next_run_at', $clock->next($schedule, Carbon::now('UTC')));
            $schedule->save();

            return $schedule;
        }, 3);
    }

    /**
     * Pause future occurrences without touching active runs.
     */
    public function pause(Recipe $recipe, User $user): CollectorSchedule
    {
        $schedule = $this->ownedSchedule($recipe, $user);
        $schedule->forceFill(['status' => CollectorSchedule::STATUS_PAUSED, 'next_run_at' => null])->save();

        return $schedule;
    }

    /**
     * Resume from the next future slot, coalescing any downtime.
     */
    public function resume(Recipe $recipe, User $user): CollectorSchedule
    {
        return Resolver::resolveDatabaseManager()->transaction(function () use ($recipe, $user): CollectorSchedule {
            $schedule = CollectorSchedule::query()->where('recipe_id', $recipe->getKey())->where('user_id', $user->getKey())->lockForUpdate()->first();
            if (!$schedule instanceof CollectorSchedule) {
                throw new InvalidArgumentException('This recipe has no schedule.');
            }
            $this->validatePinnedVersion($recipe, $schedule);
            $schedule->forceFill([
                'status' => CollectorSchedule::STATUS_ACTIVE,
                'next_run_at' => (new ScheduleClock())->next($schedule, Carbon::now('UTC'), $schedule->getLastSlotKey()),
            ])->save();

            return $schedule;
        }, 3);
    }

    /**
     * Start a manual collection with the same pinned approved version and run lock.
     */
    public function runNow(Recipe $recipe, User $user): ScrapeRun
    {
        $schedule = $this->ownedSchedule($recipe, $user);
        $version = $this->validatePinnedVersion($recipe, $schedule);

        return (new ScrapeRunService())->start($recipe, $version, $user, 'scheduled');
    }

    /**
     * Dispatch at most one due occurrence per schedule, even after long downtime.
     */
    public function dispatchDue(int $limit = 100): int
    {
        $ids = CollectorSchedule::query()
            ->where('status', CollectorSchedule::STATUS_ACTIVE)
            ->where('next_run_at', '<=', Carbon::now('UTC'))
            ->orderBy('next_run_at')
            ->limit($limit)
            ->pluck('id')
            ->values()
            ->all();

        return $this->claimEach($ids, 0);
    }

    /**
     * Read a schedule only through its owned recipe.
     */
    public function forRecipe(Recipe $recipe, User $user): CollectorSchedule|null
    {
        \abort_unless($recipe->user()->whereKey($user->getKey())->exists(), 404);
        $schedule = CollectorSchedule::query()->where('recipe_id', $recipe->getKey())->where('user_id', $user->getKey())->first();

        return $schedule instanceof CollectorSchedule ? $schedule : null;
    }

    /**
     * Claim one schedule at a time and recurse through the bounded due set.
     *
     * @param array<int, mixed> $ids
     */
    private function claimEach(array $ids, int $position): int
    {
        if (!\array_key_exists($position, $ids)) {
            return 0;
        }

        return ($this->claim(Typer::assertInt($ids[$position])) ? 1 : 0)
            + $this->claimEach($ids, $position + 1);
    }

    /**
     * Claim a due slot under a row lock and unique database constraint.
     */
    private function claim(int $id): bool
    {
        return Resolver::resolveDatabaseManager()->transaction(function () use ($id): bool {
            $schedule = CollectorSchedule::query()->whereKey($id)->lockForUpdate()->first();
            $now = Carbon::now('UTC');
            if (!$schedule instanceof CollectorSchedule || $schedule->getStatus() !== CollectorSchedule::STATUS_ACTIVE) {
                return false;
            }
            $due = $schedule->getNextRunAt();
            if ($due === null || $due->greaterThan($now)) {
                return false;
            }

            $clock = new ScheduleClock();
            $slotKey = $clock->slotKey($schedule, $due);
            $schedule->forceFill([
                'last_slot_key' => $slotKey,
                'next_run_at' => $clock->next($schedule, $now, $slotKey),
            ])->save();

            $occurrence = CollectorOccurrence::create([
                'schedule_id' => $schedule->getKey(),
                'recipe_version_id' => $schedule->getRecipeVersionId(),
                'slot_key' => $slotKey,
                'due_at' => $due,
                'status' => CollectorOccurrence::STATUS_QUEUED,
            ]);

            $recipe = $schedule->recipe()->getResults();
            $user = $schedule->user()->getResults();
            if (!$recipe instanceof Recipe || !$user instanceof User) {
                $occurrence->forceFill(['status' => CollectorOccurrence::STATUS_SKIPPED, 'reason' => 'missing_owner'])->save();

                return true;
            }

            try {
                $version = $this->validatePinnedVersion($recipe, $schedule);
                $run = (new ScrapeRunService())->start($recipe, $version, $user, 'scheduled');
                $occurrence->forceFill(['run_id' => $run->getId()])->save();
            } catch (InvalidArgumentException|ValidationException $exception) {
                $occurrence->forceFill(['status' => CollectorOccurrence::STATUS_SKIPPED, 'reason' => 'unavailable'])->save();
            }

            return true;
        });
    }

    /**
     * Require ownership and one configured schedule.
     */
    private function ownedSchedule(Recipe $recipe, User $user): CollectorSchedule
    {
        $schedule = $this->forRecipe($recipe, $user);
        if (!$schedule instanceof CollectorSchedule) {
            throw new InvalidArgumentException('This recipe has no schedule.');
        }

        return $schedule;
    }

    /**
     * Require a tested, approved, structured version and a ready connection.
     */
    private function approvedVersion(Recipe $recipe, User $user): RecipeVersion
    {
        \abort_unless($recipe->user()->whereKey($user->getKey())->exists(), 404);
        $version = $recipe->activeVersion()->getResults();
        if (!$version instanceof RecipeVersion || $version->getStatus() !== RecipeVersion::STATUS_APPROVED || $version->getDefinition() === null) {
            throw new InvalidArgumentException('Approve a tested definition before scheduling.');
        }
        $this->assertConnectionReady($version, $user);

        return $version;
    }

    /**
     * Retain the original approved version until the user configures another schedule.
     */
    private function validatePinnedVersion(Recipe $recipe, CollectorSchedule $schedule): RecipeVersion
    {
        $version = $recipe->versions()->whereKey($schedule->getRecipeVersionId())->first();
        $user = $schedule->user()->getResults();
        if (!$version instanceof RecipeVersion || !$user instanceof User || $version->getStatus() !== RecipeVersion::STATUS_APPROVED || $version->getDefinition() === null) {
            throw new InvalidArgumentException('The scheduled definition is no longer approved.');
        }
        $this->assertConnectionReady($version, $user);

        return $version;
    }

    /**
     * Reconnect before scheduled access to a protected source.
     */
    private function assertConnectionReady(RecipeVersion $version, User $user): void
    {
        $connectionId = $version->getDefinition()?->getConnectionId();
        if ($connectionId === null) {
            return;
        }
        $connection = CollectorConnection::query()->whereKey($connectionId)->where('user_id', $user->getKey())->lockForUpdate()->first();
        if (!$connection instanceof CollectorConnection || $connection->getStatus() !== 'ready' || !$connection->hasVerifiedState()) {
            throw new InvalidArgumentException('Reconnect the source before scheduling.');
        }
    }
}
