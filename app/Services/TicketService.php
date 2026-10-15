<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function __construct(
        private readonly TicketNumberGeneratorService $numberGenerator,
        private readonly ActivityLoggerService $activityLogger,
        private readonly TicketAssignmentService $assignmentService,
        private readonly SlaService $slaService,
    ) {}

    public function create(array $data, User $creator): Ticket
    {
        return DB::transaction(function () use ($data, $creator) {
            $priority = $data['priority'];

            if (! $priority instanceof Priority) {
                $priority = Priority::from($data['priority']);
            }

            $ticket = Ticket::create([
                'ticket_number' => $this->numberGenerator->generate(now()->year),
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $priority,
                'status' => TicketStatus::Open,
                'category_id' => $data['category_id'],
                'created_by' => $creator->id,
                'external_ref' => $data['external_ref'] ?? null,
                'due_at' => $this->slaService->calculateDue($priority, now()),
                'sla_warning_at' => $this->slaService->calculateWarning($priority, now()),
            ]);

            $this->activityLogger->logCreated($ticket, $creator);

            // Auto-assignment after ticket creation
            if ($ticket->assigned_to === null) {
                $assignedAgent = $this->assignmentService->assignTicket($ticket);
                if ($assignedAgent) {
                    $ticket->assigned_to = $assignedAgent->id;
                    $ticket->last_assigned_at = now();
                    $ticket->save();
                    $this->activityLogger->logAssigned($ticket, $assignedAgent, $creator);
                }
            }

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

                // Recalculate SLA when priority changes
                $this->recalculateSla($ticket, $actor);
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

            // Recalculate SLA when priority changes
            $this->recalculateSla($ticket, $actor);

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

    /**
     * @param  array<string, mixed>  $data
     */
    public function addReply(Ticket $ticket, array $data, User $creator): TicketReply
    {
        return DB::transaction(function () use ($ticket, $data, $creator) {
            $reply = $ticket->replies()->create([
                'user_id' => $creator->id,
                'body' => $data['body'],
                'is_internal' => (bool) ($data['is_internal'] ?? false),
            ]);

            $this->activityLogger->logReplyCreated($ticket, $reply, $creator);

            if (! $reply->is_internal
                && $creator->isCustomer()
                && $ticket->status === TicketStatus::WaitingForCustomer) {
                $this->performTransition($ticket, TicketStatus::InProgress, $creator, 'status_changed');
            }

            return $reply;
        });
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Attachment>
     */
    public function addAttachments(Ticket $ticket, array $files, User $creator): Collection
    {
        return DB::transaction(function () use ($ticket, $files, $creator) {
            $attachments = new Collection;

            foreach ($files as $file) {
                $attachment = $ticket->attachments()->create([
                    'user_id' => $creator->id,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $file->store('attachments', 'local'),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);

                $this->activityLogger->logAttachmentAdded($ticket, $attachment, $creator);

                $attachments->push($attachment);
            }

            return $attachments;
        });
    }

    private function transitionTo(Ticket $ticket, TicketStatus $newStatus, User $actor, string $action, ?string $note = null): Ticket
    {
        return DB::transaction(fn (): Ticket => $this->performTransition($ticket, $newStatus, $actor, $action, $note));
    }

    private function performTransition(Ticket $ticket, TicketStatus $newStatus, User $actor, string $action, ?string $note = null): Ticket
    {
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

    private function recalculateSla(Ticket $ticket, User $actor): void
    {
        $oldDueAt = $ticket->due_at;
        $oldWarningAt = $ticket->sla_warning_at;

        $this->slaService->applyTo($ticket);

        if ($oldDueAt?->eq($ticket->due_at) === true && $oldWarningAt?->eq($ticket->sla_warning_at) === true) {
            return;
        }

        $ticket->save();

        $this->activityLogger->logSlaRecalculated($ticket, $oldDueAt, $oldWarningAt, $ticket->due_at, $ticket->sla_warning_at, $actor);
    }
}
