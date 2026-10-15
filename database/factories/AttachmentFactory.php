<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachable_type' => Ticket::class,
            'attachable_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'original_name' => fake()->word().'.pdf',
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 5 * 1024 * 1024),
        ];
    }

    public function forTicket(Ticket $ticket): static
    {
        return $this->state(fn (array $attributes) => [
            'attachable_type' => Ticket::class,
            'attachable_id' => $ticket->id,
        ]);
    }

    public function withMime(string $mime): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => $mime,
            'path' => 'attachments/'.fake()->uuid().'.'.Str::afterLast($mime, '/'),
            'original_name' => fake()->word().'.'.Str::afterLast($mime, '/'),
        ]);
    }
}
