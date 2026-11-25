<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Models\Ticket;
use App\Models\User;
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
            DB::afterCommit(function () use ($ticket, $agent): void {
                TicketAssigned::dispatch($ticket, $agent);
            });

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

            DB::afterCommit(function () use ($ticket, $targetAgent): void {
                TicketAssigned::dispatch($ticket, $targetAgent);
            });

            return true;
        });
    }

    public function assignTicket(Ticket $ticket): ?User
    {
        return $this->leastLoadedAssignmentService->assignTicket($ticket);
    }

    public function autoAssignUnassigned(User $actor): array
    {
        $assigned = 0;
        $skipped = 0;

        // Process active unassigned tickets in chunks to avoid memory spikes
        Ticket::unassigned()
            ->active()
            ->chunkById(100, function ($tickets) use ($actor, &$assigned, &$skipped) {
                foreach ($tickets as $ticket) {
                    $agent = $this->leastLoadedAssignmentService->assignTicket($ticket);

                    if (! $agent) {
                        $skipped++;

                        continue;
                    }

                    $affected = DB::transaction(function () use ($ticket, $agent, $actor) {
                        $rows = Ticket::whereKey($ticket->id)
                            ->whereNull('assigned_to')
                            ->update([
                                'assigned_to' => $agent->id,
                                'last_assigned_at' => now(),
                            ]);

                        if ($rows === 0) {
                            return 0;
                        }

                        $this->activityLogger->logAssigned($ticket, $agent, $actor);

                        DB::afterCommit(function () use ($ticket, $agent): void {
                            TicketAssigned::dispatch($ticket->fresh(), $agent);
                        });

                        return 1;
                    });

                    if ($affected === 1) {
                        $assigned++;
                    } else {
                        $skipped++;
                    }
                }
            });

        return ['assigned' => $assigned, 'skipped' => $skipped];
    }
}
