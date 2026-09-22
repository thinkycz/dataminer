<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ScheduleValidity;
use App\Models\User;
use App\Scraping\RecipeRepository;
use App\Scraping\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class ScheduleController
{
    use ValidatesWebRequests;

    /**
     * Create or update one owned recipe schedule.
     */
    public function update(Request $request, int $recipe): RedirectResponse
    {
        $owned = (new RecipeRepository())->findOwned($recipe, User::mustAuth());
        $validity = new ScheduleValidity();
        $validated = $this->validateRequest($request, [
            'cadence' => $validity->cadence()->required()->toArray(),
            'timezone' => $validity->timezone()->required()->toArray(),
            'local_time' => $validity->localTime()->required()->toArray(),
            'weekday' => $validity->weekday()->nullable()->toArray(),
            'cron_expression' => $validity->cronExpression()->nullable()->toArray(),
        ]);

        try {
            (new ScheduleService())->configure(
                $owned,
                User::mustAuth(),
                $validated->assertString('cadence'),
                $validated->assertString('timezone'),
                $validated->assertString('local_time'),
                $validated->parseNullableInt('weekday'),
                $validated->parseNullableString('cron_expression'),
            );
        } catch (InvalidArgumentException $exception) {
            Thrower::default()->message('schedule', Typer::assertString(\__('schedule_unavailable')))->throw();
        }

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Pause future slots.
     */
    public function pause(int $recipe): RedirectResponse
    {
        $owned = (new RecipeRepository())->findOwned($recipe, User::mustAuth());
        try {
            (new ScheduleService())->pause($owned, User::mustAuth());
        } catch (InvalidArgumentException $exception) {
            Thrower::default()->message('schedule', Typer::assertString(\__('schedule_unavailable')))->throw();
        }

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Resume at the next future local slot.
     */
    public function resume(int $recipe): RedirectResponse
    {
        $owned = (new RecipeRepository())->findOwned($recipe, User::mustAuth());
        try {
            (new ScheduleService())->resume($owned, User::mustAuth());
        } catch (InvalidArgumentException $exception) {
            Thrower::default()->message('schedule', Typer::assertString(\__('schedule_unavailable')))->throw();
        }

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Start a full collection using the pinned approved version.
     */
    public function runNow(int $recipe): RedirectResponse
    {
        $owned = (new RecipeRepository())->findOwned($recipe, User::mustAuth());
        try {
            $run = (new ScheduleService())->runNow($owned, User::mustAuth());
        } catch (InvalidArgumentException $exception) {
            Thrower::default()->message('schedule', Typer::assertString(\__('schedule_unavailable')))->throw();
        }

        return Resolver::resolveRedirector()->to('/runs/' . $run->getId());
    }
}
