<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'original_filename' => 'tickets.csv',
            'path' => 'imports/'.fake()->uuid().'.csv',
            'status' => Import::STATUS_PENDING,
            'total_rows' => 0,
            'processed_rows' => 0,
            'imported_rows' => 0,
            'failed_rows' => 0,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Import::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Import::STATUS_COMPLETED,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Import::STATUS_FAILED,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
            'error_message' => 'Import failed while processing row 42.',
        ]);
    }
}
