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
use Illuminate\Support\Collection;

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

        $weightedStatuses = $this->weightedSample(TicketStatus::cases(), [
            TicketStatus::Open->value => 120,
            TicketStatus::InProgress->value => 40,
            TicketStatus::WaitingForCustomer->value => 20,
            TicketStatus::Resolved->value => 14,
            TicketStatus::Closed->value => 6,
        ]);

        $weightedPriorities = $this->weightedSample(Priority::cases(), [
            Priority::Low->value => 30,
            Priority::Medium->value => 100,
            Priority::High->value => 50,
            Priority::Critical->value => 20,
        ]);

        $assignedAgents = $agents->isNotEmpty() ? $agents->shuffle() : collect();

        Ticket::unguard();

        for ($i = 0; $i < 200; $i++) {
            $customer = $customers->random();
            $category = $categories->random();
            $priority = $weightedPriorities->shift();
            $status = $weightedStatuses->shift();

            $daysAgo = mt_rand(0, 30);
            $createdAt = Carbon::now()->subDays($daysAgo);

            $assignedTo = null;
            $lastAssignedAt = null;
            if ($status !== TicketStatus::Open && $assignedAgents->isNotEmpty()) {
                $assignedTo = $assignedAgents->shift()->id;
                $lastAssignedAt = clone $createdAt;
                $lastAssignedAt->addHours(mt_rand(1, 48));
            }

            $dueAt = $slaService->calculateDue($priority, $createdAt);
            $warningAt = $slaService->calculateWarning($priority, $createdAt);

            $resolvedAt = null;
            $closedAt = null;
            if (in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
                $resolvedAt = clone $createdAt;
                $resolvedAt->addDays(mt_rand(1, 5));
            }
            if ($status === TicketStatus::Closed) {
                $closedAt = $resolvedAt ? clone $resolvedAt : clone $createdAt;
                $closedAt->addDays(mt_rand(1, 3));
            }

            $ticketNumber = sprintf('TKT-%d-%06d', $createdAt->year, 500000 + $i);

            $ticket = Ticket::create([
                'ticket_number' => $ticketNumber,
                'subject' => fake()->sentence(mt_rand(4, 8)),
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

            $ticket->activities()->create([
                'actor_id' => $customer->id,
                'ticket_id' => $ticket->id,
                'action' => 'created',
                'meta' => [
                    'is_internal' => false,
                    'actor_name' => $customer->name,
                    'priority' => $priority->value,
                    'status' => $status->value,
                ],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        Ticket::reguard();
    }

    private function weightedSample($items, array $weights): Collection
    {
        $weighted = collect();

        foreach ($items as $item) {
            $weight = $weights[$item->value] ?? 0;
            for ($w = 0; $w < $weight; $w++) {
                $weighted->push($item);
            }
        }

        return $weighted->shuffle()->values();
    }
}
