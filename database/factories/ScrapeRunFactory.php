<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScrapeRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScrapeRun>
 */
class ScrapeRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'recipe_id' => RecipeFactory::new(),
            'recipe_version_id' => RecipeVersionFactory::new(),
            'user_id' => UserFactory::new(),
            'kind' => ScrapeRun::KIND_TEST,
            'status' => ScrapeRun::STATUS_QUEUED,
            'progress' => 0,
            'row_count' => 0,
            'byte_count' => 0,
            'request_count' => 0,
            'limits' => ['rows' => 100, 'bytes' => 1_000_000, 'requests' => 50, 'seconds' => 120],
            'logs' => null,
            'error' => null,
            'artifact_disk' => null,
            'json_path' => null,
            'csv_path' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
