<?php

namespace Database\Seeders;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', 'customer')->get();
        $agents = User::where('role', 'agent')->get();
        $categories = Category::all();

        if ($customers->isEmpty() || $categories->isEmpty()) {
            $this->command?->warn('Run UserSeeder and CategorySeeder first.');

            return;
        }

        $slaService = app(SlaService::class);
        $statuses = TicketStatus::cases();
        $priorities = Priority::cases();

        $count = 200;

        for ($i = 0; $i < $count; $i++) {
            $customer = $customers->random();
            $category = $categories->random();
            $priority = $priorities[array_rand($priorities)];
            $status = $statuses[array_rand($statuses)];

            $createdAt = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));

            $assignedTo = null;
            $lastAssignedAt = null;
            if (rand(0, 1) === 1 && $agents->isNotEmpty()) {
                $agent = $agents->random();
                $assignedTo = $agent->id;
                $lastAssignedAt = $createdAt->copy()->addHours(rand(1, 48));
            }

            $resolvedAt = null;
            $closedAt = null;
            if (in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
                $resolvedAt = $createdAt->copy()->addDays(rand(1, 5));
            }
            if ($status === TicketStatus::Closed) {
                $closedAt = ($resolvedAt ?? $createdAt)->copy()->addDays(rand(1, 3));
            }

            $dueAt = $slaService->calculateDue($priority, $createdAt);
            $warningAt = $slaService->calculateWarning($priority, $createdAt);

            Ticket::factory()->create([
                'ticket_number' => sprintf('TKT-%d-%06d', $createdAt->year, 100000 + $i),
                'subject' => fake()->sentence(6),
                'description' => fake()->paragraph(),
                'priority' => $priority,
                'status' => $status,
                'category_id' => $category->id,
                'created_by' => $customer->id,
                'assigned_to' => $assignedTo,
                'last_assigned_at' => $lastAssignedAt,
                'due_at' => $dueAt,
                'sla_warning_at' => $warningAt,
                'resolved_at' => $resolvedAt,
                'closed_at' => $closedAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'external_ref' => null,
            ]);
        }
    }
}
