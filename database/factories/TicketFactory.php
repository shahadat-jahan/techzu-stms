<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_number' => sprintf('TKT-%d-%06d', now()->year, fake()->unique()->numberBetween(1, 999999)),
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(Priority::cases()),
            'status' => TicketStatus::Open,
            'category_id' => Category::factory(),
            'created_by' => User::factory()->customer(),
            'assigned_to' => null,
            'due_at' => now()->addDay(),
            'sla_warning_at' => now()->addHours(20),
            'external_ref' => null,
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => User::factory()->availableAgent(),
            'last_assigned_at' => now(),
        ]);
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    public function priority(Priority $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Closed,
            'resolved_at' => $attributes['resolved_at'] ?? now(),
            'closed_at' => now(),
        ]);
    }

    public function breached(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Open,
            'sla_warning_at' => now()->subHours(3),
            'due_at' => now()->subHour(),
        ]);
    }

    public function approachingBreach(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Open,
            'sla_warning_at' => now()->subMinutes(30),
            'due_at' => now()->addHour(),
        ]);
    }

    public function withExternalRef(string $externalRef): static
    {
        return $this->state(fn (array $attributes) => [
            'external_ref' => $externalRef,
        ]);
    }
}
