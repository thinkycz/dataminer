<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CollectorConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectorConnection>
 */
class CollectorConnectionFactory extends Factory
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
            'name' => 'Fixture connection',
            'origin' => 'https://example.com',
            'kind' => 'bearer',
            'status' => 'ready',
            'credentials' => ['token' => 'fixture-token'],
        ];
    }
}
