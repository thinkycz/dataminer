<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RecipeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeVersion>
 */
class RecipeVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => RecipeFactory::new(),
            'version' => 1,
            'source' => 'export async function scrape(context) { context.emit({ value: "example" }); }',
            'checksum' => \hash('sha256', 'export async function scrape(context) { context.emit({ value: "example" }); }'),
            'proposed_columns' => [['key' => 'value', 'label' => 'Value', 'type' => 'string']],
            'generation_summary' => \fake()->sentence(),
            'generation_reason' => 'initial',
            'provider' => 'fake',
            'model' => 'fake-model',
            'usage' => null,
            'status' => RecipeVersion::STATUS_DRAFT,
            'test_summary' => null,
            'approval_call_id' => null,
            'approved_at' => null,
        ];
    }
}
