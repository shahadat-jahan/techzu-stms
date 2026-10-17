<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class LeastLoadedAssignmentService
{
    private const ACTIVE_STATUSES = [
        TicketStatus::Open->value,
        TicketStatus::InProgress->value,
        TicketStatus::WaitingForCustomer->value,
    ];

    public function assignTicket(Ticket $ticket): ?User
    {
        $agents = $this->availableAgents($ticket->category_id);

        if ($agents->isEmpty()) {
            $agents = $this->availableAgents(null);
        }

        $eligible = $agents->filter(
            fn (User $agent): bool => $agent->active_tickets_count < $agent->getMaxActiveTickets()
        );

        if ($eligible->isEmpty()) {
            return null;
        }

        return $eligible->sortBy(fn (User $agent): array => [
            $agent->active_tickets_count,
            $agent->last_assignment_at?->timestamp ?? PHP_INT_MAX,
        ])->first();
    }

    private function availableAgents(?int $categoryId): Collection
    {
        $query = User::where('role', 'agent')
            ->where('is_available', true)
            ->withCount([
                'tickets as active_tickets_count' => fn ($query) => $query->whereIn('status', self::ACTIVE_STATUSES),
            ])
            ->withMax('tickets as last_assignment_at', 'last_assigned_at');

        if ($categoryId !== null) {
            $query->whereHas('categories', fn ($query) => $query->whereKey($categoryId));
        }

        return $query->get();
    }
}
