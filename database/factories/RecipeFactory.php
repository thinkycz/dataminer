<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new(),
            'name' => \fake()->sentence(3),
            'start_url' => \fake()->url(),
            'instructions' => \fake()->paragraph(),
            'status' => Recipe::STATUS_DRAFT,
            'active_version_id' => null,
            'ai_conversation_id' => null,
        ];
    }
}
