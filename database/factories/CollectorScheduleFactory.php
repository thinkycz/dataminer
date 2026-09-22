<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CollectorSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectorSchedule>
 */
class CollectorScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => RecipeFactory::new(),
            'user_id' => UserFactory::new(),
            'recipe_version_id' => RecipeVersionFactory::new(),
            'cadence' => 'daily',
            'timezone' => 'Europe/Prague',
            'local_time' => '09:00',
            'cron_expression' => null,
            'weekday' => null,
            'status' => CollectorSchedule::STATUS_ACTIVE,
            'next_run_at' => \now()->addDay(),
            'last_slot_key' => null,
        ];
    }
}
