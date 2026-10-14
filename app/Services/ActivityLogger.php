<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class ActivityLogger
{
    public function logCreated(Ticket $ticket, User $actor): void
    {
        $this->log('created', $ticket, $actor, [], sprintf('Ticket created by %s', $actor->name));
    }

    public function logUpdated(Ticket $ticket, array $fields, User $actor): void
    {
        $labels = array_map(
            fn (string $field): string => ucwords(str_replace('_', ' ', $field)),
            $fields,
        );

        $this->log(
            'updated',
            $ticket,
            $actor,
            ['changed_fields' => $fields],
            sprintf('Ticket updated by %s (%s)', $actor->name, implode(', ', $labels)),
        );
    }

    public function logStatusChanged(Ticket $ticket, TicketStatus $oldStatus, TicketStatus $newStatus, User $actor, ?string $note = null): void
    {
        $this->log(
            'status_changed',
            $ticket,
            $actor,
            $this->statusMeta($oldStatus, $newStatus, $note),
            sprintf('Status changed from %s to %s by %s', $oldStatus->label(), $newStatus->label(), $actor->name),
        );
    }

    public function logPriorityChanged(Ticket $ticket, Priority $oldPriority, Priority $newPriority, User $actor): void
    {
        $this->log(
            'priority_changed',
            $ticket,
            $actor,
            [
                'old_priority' => $oldPriority->value,
                'new_priority' => $newPriority->value,
            ],
            sprintf('Priority changed from %s to %s by %s', $oldPriority->label(), $newPriority->label(), $actor->name),
        );
    }

    public function logResolved(Ticket $ticket, TicketStatus $oldStatus, User $actor): void
    {
        $this->log(
            'resolved',
            $ticket,
            $actor,
            $this->statusMeta($oldStatus, TicketStatus::Resolved),
            sprintf('Ticket resolved by %s', $actor->name),
        );
    }

    public function logClosed(Ticket $ticket, TicketStatus $oldStatus, User $actor): void
    {
        $this->log(
            'closed',
            $ticket,
            $actor,
            $this->statusMeta($oldStatus, TicketStatus::Closed),
            sprintf('Ticket closed by %s', $actor->name),
        );
    }

    public function logReopened(Ticket $ticket, TicketStatus $oldStatus, TicketStatus $newStatus, User $actor): void
    {
        $this->log(
            'reopened',
            $ticket,
            $actor,
            $this->statusMeta($oldStatus, $newStatus),
            sprintf('Ticket reopened from %s to %s by %s', $oldStatus->label(), $newStatus->label(), $actor->name),
        );
    }

    public function log(string $action, Ticket $ticket, User $actor, array $meta = [], ?string $message = null): void
    {
        if ($message !== null) {
            $meta['message'] = $message;
        }

        $ticket->activities()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'meta' => $meta,
        ]);
    }

    private function statusMeta(TicketStatus $oldStatus, TicketStatus $newStatus, ?string $note = null): array
    {
        $meta = [
            'old_status' => $oldStatus->value,
            'new_status' => $newStatus->value,
        ];

        if (filled($note)) {
            $meta['note'] = $note;
        }

        return $meta;
    }
}
