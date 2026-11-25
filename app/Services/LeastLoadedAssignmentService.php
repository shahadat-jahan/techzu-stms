<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
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

        return $eligible->sortBy(function (User $agent): array {
            $timestamp = PHP_INT_MAX;
            if ($agent->last_assignment_at) {
                $timestamp = is_numeric($agent->last_assignment_at)
                    ? (int) $agent->last_assignment_at
                    : Carbon::parse($agent->last_assignment_at)->timestamp;
            }

            return [
                $agent->active_tickets_count,
                $timestamp,
            ];
        })->first();
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
