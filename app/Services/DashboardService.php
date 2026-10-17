<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Cache\TaggableStore;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const DASHBOARD_CACHE_SECONDS = 60;

    public function getStats(User $user): array
    {
        $cacheKey = "dashboard:user:{$user->id}:{$user->role->value}";
        $tags = ['dashboard', "dashboard:user:{$user->id}"];

        if ($this->supportsTags()) {
            return Cache::tags($tags)->remember($cacheKey, self::DASHBOARD_CACHE_SECONDS, function () use ($user) {
                return [
                    'summary' => $this->getSummaryCounts($user),
                    'by_priority' => $this->getPriorityBreakdown($user),
                    'by_agent' => $user->isCustomer() ? [] : $this->getAgentWorkload(),
                ];
            });
        }

        return Cache::remember($cacheKey, self::DASHBOARD_CACHE_SECONDS, function () use ($user) {
            return [
                'summary' => $this->getSummaryCounts($user),
                'by_priority' => $this->getPriorityBreakdown($user),
                'by_agent' => $user->isCustomer() ? [] : $this->getAgentWorkload(),
            ];
        });
    }

    public function flush(): void
    {
        if ($this->supportsTags()) {
            Cache::tags(['dashboard'])->flush();
        }
    }

    private function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }

    private function getSummaryCounts(User $user): array
    {
        $now = now()->toDateTimeString();
        $activeStatuses = self::activeStatusValues();

        $row = Ticket::query()
            ->visibleTo($user)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status IN ('{$activeStatuses}') THEN 1 ELSE 0 END), 0) as total_open,
                COALESCE(SUM(CASE WHEN status IN ('{$activeStatuses}') AND assigned_to IS NULL THEN 1 ELSE 0 END), 0) as unassigned,
                COALESCE(SUM(CASE WHEN status IN ('{$activeStatuses}') AND priority = ? THEN 1 ELSE 0 END), 0) as critical,
                COALESCE(SUM(CASE WHEN status IN ('{$activeStatuses}') AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END), 0) as breached,
                COALESCE(SUM(CASE WHEN status IN ('{$activeStatuses}') AND sla_warning_at IS NOT NULL AND due_at IS NOT NULL AND sla_warning_at <= ? AND due_at > ? THEN 1 ELSE 0 END), 0) as approaching,
                COALESCE(SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END), 0) as resolved
            ", [
                Priority::Critical->value,
                $now,
                $now,
                $now,
                TicketStatus::Resolved->value,
                TicketStatus::Closed->value,
            ])
            ->first();

        return [
            'total_open' => (int) ($row->total_open ?? 0),
            'unassigned' => (int) ($row->unassigned ?? 0),
            'critical' => (int) ($row->critical ?? 0),
            'breached' => (int) ($row->breached ?? 0),
            'approaching' => (int) ($row->approaching ?? 0),
            'resolved' => (int) ($row->resolved ?? 0),
        ];
    }

    private function getPriorityBreakdown(User $user): array
    {
        $counts = Ticket::query()
            ->visibleTo($user)
            ->active()
            ->select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->all();

        $result = [];
        foreach (Priority::cases() as $priority) {
            $result[$priority->value] = (int) ($counts[$priority->value] ?? 0);
        }

        return $result;
    }

    private function getAgentWorkload(): array
    {
        return User::query()
            ->where('role', Role::Agent->value)
            ->leftJoin('tickets', function (JoinClause $join) {
                $join->on('tickets.assigned_to', '=', 'users.id')
                    ->whereIn('tickets.status', self::activeStatusList())
                    ->whereNull('tickets.deleted_at');
            })
            ->select('users.id', 'users.name', 'users.email', DB::raw('COUNT(tickets.id) as active_tickets_count'))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('active_tickets_count')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'active_tickets_count' => (int) $agent->active_tickets_count,
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function activeStatusList(): array
    {
        return [
            TicketStatus::Open->value,
            TicketStatus::InProgress->value,
            TicketStatus::WaitingForCustomer->value,
        ];
    }

    private static function activeStatusValues(): string
    {
        return implode("', '", self::activeStatusList());
    }
}
