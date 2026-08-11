<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScrapeRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScrapeRow>
 */
class ScrapeRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => ScrapeRunFactory::new(),
            'sequence' => \fake()->unique()->numberBetween(1, 100_000),
            'payload' => ['value' => \fake()->word()],
        ];
    }
}
