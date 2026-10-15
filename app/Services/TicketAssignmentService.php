<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TicketAssignmentService
{
    public function __construct(
        private readonly ActivityLoggerService $activityLogger,
        private readonly LeastLoadedAssignmentService $leastLoadedAssignmentService,
    ) {}

    public function accept(Ticket $ticket, User $agent): bool
    {
        return DB::transaction(function () use ($ticket, $agent) {
            $rows = Ticket::whereKey($ticket->id)
                ->whereNull('assigned_to')
                ->update([
                    'assigned_to' => $agent->id,
                    'status' => TicketStatus::InProgress,
                    'last_assigned_at' => now(),
                ]);

            if ($rows === 0) {
                return false; // 409 - already assigned
            }

            $this->activityLogger->logAssigned($ticket, $agent, $agent); // actor = agent accepting
            return true;
        });
    }

    public function assign(Ticket $ticket, User $targetAgent, User $actor): bool
    {
        return DB::transaction(function () use ($ticket, $targetAgent, $actor) {
            $lockedTicket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $oldAgent = $lockedTicket->agent;

            $lockedTicket->assigned_to = $targetAgent->id;
            $lockedTicket->last_assigned_at = now();
            $lockedTicket->save();

            if ($oldAgent) {
                $this->activityLogger->logReassigned($ticket, $oldAgent, $targetAgent, $actor);
            } else {
                $this->activityLogger->logAssigned($ticket, $targetAgent, $actor);
            }

            return true;
        });
    }

    public function assignTicket(Ticket $ticket): ?User
    {
        return $this->leastLoadedAssignmentService->assignTicket($ticket);
    }

    public function autoAssignUnassigned(User $actor): array
    {
        $unassignedTickets = Ticket::unassigned()->active()->get();
        $assigned = 0;
        $skipped = 0;

        foreach ($unassignedTickets as $ticket) {
            $agent = $this->leastLoadedAssignmentService->assignTicket($ticket);
            if ($agent) {
                $ticket->assigned_to = $agent->id;
                $ticket->last_assigned_at = now();
                $ticket->save();
                $this->activityLogger->logAssigned($ticket, $agent, $actor);
                $assigned++;
            } else {
                $skipped++;
            }
        }

        return ['assigned' => $assigned, 'skipped' => $skipped];
    }
}
