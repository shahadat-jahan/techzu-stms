<?php

namespace App\Filters;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TicketFilter
{
    public function apply(Builder $query, User $user, array $filters): Builder
    {
        if (! empty($filters['number'])) {
            $number = mb_strtolower(trim((string) $filters['number']));
            $query->whereRaw('LOWER(ticket_number) LIKE ?', ["%{$number}%"]);
        }

        if (! empty($filters['keyword'])) {
            $keyword = mb_strtolower(trim((string) $filters['keyword']));
            $query->where(function (Builder $searchQuery) use ($keyword) {
                $searchQuery->whereRaw('LOWER(ticket_number) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$keyword}%"]);
            });
        }

        if (! empty($filters['status'])) {
            $status = TicketStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if (! empty($filters['priority'])) {
            $priority = Priority::tryFrom((string) $filters['priority']);
            if ($priority !== null) {
                $query->where('priority', $priority->value);
            }
        }

        if (! empty($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }

        if (! empty($filters['assigned_to'])) {
            if ($filters['assigned_to'] === 'unassigned') {
                $query->whereNull('assigned_to');
            } elseif (is_numeric($filters['assigned_to'])) {
                $query->where('assigned_to', (int) $filters['assigned_to']);
            }
        }

        if (! empty($filters['customer_id']) && ! $user->isCustomer()) {
            $query->where('created_by', (int) $filters['customer_id']);
        }

        if (! empty($filters['created_from'])) {
            try {
                $query->where('created_at', '>=', Carbon::parse($filters['created_from'])->startOfDay());
            } catch (\Exception $e) {
                // Invalid date filter value — skip filter rather than crash
            }
        }

        if (! empty($filters['created_to'])) {
            try {
                $query->where('created_at', '<=', Carbon::parse($filters['created_to'])->endOfDay());
            } catch (\Exception $e) {
                // Invalid date filter value — skip filter rather than crash
            }
        }

        if (! empty($filters['sla_status'])) {
            match ($filters['sla_status']) {
                'breached' => $query->slaBreached(),
                'approaching' => $query->slaApproaching(),
                'within' => $query->slaWithin(),
                default => null,
            };
        }

        $this->applySorting($query, $filters);

        return $query;
    }

    private function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        match ($sortBy) {
            'due_at' => $query->orderByRaw("due_at IS NULL, due_at {$sortDir}"),
            'priority' => $query->orderBy('priority', $sortDir),
            default => $query->orderBy('created_at', $sortDir),
        };
    }
}
