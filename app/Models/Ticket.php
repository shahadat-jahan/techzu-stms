<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'ticket_number',
    'subject',
    'description',
    'priority',
    'status',
    'category_id',
    'created_by',
    'assigned_to',
    'due_at',
    'sla_warning_at',
    'resolved_at',
    'closed_at',
    'escalated_approaching_at',
    'escalated_breached_at',
    'last_assigned_at',
    'external_ref',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => TicketStatus::class,
            'due_at' => 'datetime',
            'sla_warning_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'escalated_approaching_at' => 'datetime',
            'escalated_breached_at' => 'datetime',
            'last_assigned_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function visibleReplies(User $user)
    {
        return $this->replies()->visibleTo($user);
    }

    public function visibleAttachments(User $user)
    {
        return $this->attachments()->visibleTo($user);
    }

    public function visibleActivities(User $user)
    {
        return $this->activities()->visibleTo($user);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TicketStatus::Open,
            TicketStatus::InProgress,
            TicketStatus::WaitingForCustomer,
        ]);
    }

    public function scopeClosedOrResolved(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TicketStatus::Resolved,
            TicketStatus::Closed,
        ]);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeAssignedTo(Builder $query, User|int $agent): Builder
    {
        return $query->where('assigned_to', $agent instanceof User ? $agent->id : $agent);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match (true) {
            $user->isAdmin() => $query,
            $user->isAgent() => $query->where(function (Builder $agentQuery) use ($user) {
                $agentQuery->assignedTo($user)->orWhereNull('assigned_to');
            }),
            default => $query->where('created_by', $user->id),
        };
    }

    public function scopeSlaBreached(Builder $query): Builder
    {
        return $query->active()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    public function scopeSlaApproaching(Builder $query): Builder
    {
        return $query->active()
            ->whereNotNull('sla_warning_at')
            ->whereNotNull('due_at')
            ->where('sla_warning_at', '<=', now())
            ->where('due_at', '>', now());
    }

    public function scopeSlaWithin(Builder $query): Builder
    {
        return $query->active()
            ->where(function (Builder $withinWindow) {
                $withinWindow->whereNull('due_at')
                    ->orWhere('due_at', '>=', now());
            })
            ->where(function (Builder $warningNotReached) {
                $warningNotReached->whereNull('sla_warning_at')
                    ->orWhere('sla_warning_at', '>', now());
            });
    }
}
