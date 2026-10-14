<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function __construct(
        private readonly TicketNumberGenerator $numberGenerator,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function create(array $data, User $creator): Ticket
    {
        return DB::transaction(function () use ($data, $creator) {
            $priority = $data['priority'];

            if (! $priority instanceof Priority) {
                $priority = Priority::from($priority);
            }

            $ticket = Ticket::create([
                'ticket_number' => $this->numberGenerator->generate(),
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $priority,
                'status' => TicketStatus::Open,
                'category_id' => $data['category_id'],
                'created_by' => $creator->id,
                'external_ref' => $data['external_ref'] ?? null,
                // TODO: Phase 6 - SlaService calculates due_at / sla_warning_at from the priority's policy.
                'due_at' => null,
                'sla_warning_at' => null,
            ]);

            $this->activityLogger->logCreated($ticket, $creator);

            return $ticket;
        });
    }

    /**
     * Updates are change-detected: a single save persists all dirty fields and
     * each kind of change writes its own activity row in this transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $oldPriority = $ticket->priority;

            $ticket->fill(Arr::only($data, ['subject', 'description', 'category_id']));

            if (array_key_exists('priority', $data)) {
                $ticket->priority = Priority::from($data['priority']);
            }

            $changedFields = array_keys($ticket->getDirty());

            if ($changedFields === []) {
                return $ticket;
            }

            $ticket->save();

            if ($oldPriority !== $ticket->priority) {
                $this->activityLogger->logPriorityChanged($ticket, $oldPriority, $ticket->priority, $actor);
            }

            $detailsChanged = array_values(array_diff($changedFields, ['priority']));

            if ($detailsChanged !== []) {
                $this->activityLogger->logUpdated($ticket, $detailsChanged, $actor);
            }

            return $ticket;
        });
    }

    public function changeStatus(Ticket $ticket, TicketStatus $newStatus, User $actor, ?string $note = null): Ticket
    {
        return $this->transitionTo($ticket, $newStatus, $actor, 'status_changed', $note);
    }

    public function changePriority(Ticket $ticket, Priority $newPriority, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $newPriority, $actor) {
            if ($ticket->priority === $newPriority) {
                return $ticket;
            }

            $oldPriority = $ticket->priority;
            $ticket->priority = $newPriority;
            $ticket->save();

            $this->activityLogger->logPriorityChanged($ticket, $oldPriority, $newPriority, $actor);

            return $ticket;
        });
    }

    public function resolve(Ticket $ticket, User $actor): Ticket
    {
        return $this->transitionTo($ticket, TicketStatus::Resolved, $actor, 'resolved');
    }

    public function close(Ticket $ticket, User $actor): Ticket
    {
        return $this->transitionTo($ticket, TicketStatus::Closed, $actor, 'closed');
    }

    public function reopen(Ticket $ticket, User $actor): Ticket
    {
        $newStatus = match ($ticket->status) {
            TicketStatus::Resolved => TicketStatus::InProgress,
            TicketStatus::Closed => TicketStatus::Open,
            default => throw ValidationException::withMessages([
                'status' => 'Only resolved or closed tickets can be reopened.',
            ]),
        };

        return $this->transitionTo($ticket, $newStatus, $actor, 'reopened');
    }

    private function transitionTo(Ticket $ticket, TicketStatus $newStatus, User $actor, string $action, ?string $note = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $newStatus, $actor, $action, $note) {
            // Concurrent requests each bind their own copy of the ticket, so the transition
            // must be validated against the committed row under a lock; otherwise two racing
            // transitions both pass and write duplicate, contradictory activity rows.
            $lockedTicket = Ticket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = $lockedTicket->status;

            if (! $oldStatus->canTransitionTo($newStatus)) {
                throw ValidationException::withMessages([
                    'status' => sprintf('Cannot change status from %s to %s.', $oldStatus->label(), $newStatus->label()),
                ]);
            }

            $ticket->status = $newStatus;

            if ($newStatus === TicketStatus::Resolved) {
                $ticket->resolved_at = now();
            } elseif ($newStatus === TicketStatus::Closed) {
                $ticket->closed_at = now();
            } else {
                $ticket->resolved_at = null;
                $ticket->closed_at = null;
            }

            $ticket->save();

            $this->logTransition($ticket, $oldStatus, $newStatus, $actor, $action, $note);

            return $ticket;
        });
    }

    private function logTransition(Ticket $ticket, TicketStatus $oldStatus, TicketStatus $newStatus, User $actor, string $action, ?string $note): void
    {
        match ($action) {
            'resolved' => $this->activityLogger->logResolved($ticket, $oldStatus, $actor),
            'closed' => $this->activityLogger->logClosed($ticket, $oldStatus, $actor),
            'reopened' => $this->activityLogger->logReopened($ticket, $oldStatus, $newStatus, $actor),
            default => $this->activityLogger->logStatusChanged($ticket, $oldStatus, $newStatus, $actor, $note),
        };
    }
}
