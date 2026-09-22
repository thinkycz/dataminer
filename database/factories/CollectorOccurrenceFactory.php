<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CollectorOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectorOccurrence>
 */
class CollectorOccurrenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_id' => CollectorScheduleFactory::new(),
            'recipe_version_id' => RecipeVersionFactory::new(),
            'slot_key' => \now('Europe/Prague')->format('Y-m-d H:i'),
            'due_at' => \now(),
            'run_id' => null,
            'status' => CollectorOccurrence::STATUS_QUEUED,
            'reason' => null,
        ];
    }
}
