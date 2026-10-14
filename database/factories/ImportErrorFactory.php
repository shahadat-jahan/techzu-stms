<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\ImportError;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportError>
 */
class ImportErrorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_id' => Import::factory(),
            'row_number' => fake()->numberBetween(2, 1000),
            'raw_data' => [
                'subject' => fake()->sentence(3),
                'email' => 'invalid-email',
                'priority' => 'urgent',
            ],
            'error_message' => 'Invalid email; priority must be one of: low, medium, high, critical.',
        ];
    }
}
