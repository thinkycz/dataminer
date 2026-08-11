<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScrapeRunColumn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScrapeRunColumn>
 */
class ScrapeRunColumnFactory extends Factory
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
            'key' => \fake()->unique()->slug(2),
            'label' => \fake()->words(2, true),
            'type' => 'string',
            'position' => \fake()->unique()->numberBetween(0, 100),
        ];
    }
}
