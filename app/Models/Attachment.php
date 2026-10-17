<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['attachable_type', 'attachable_id', 'user_id', 'original_name', 'path', 'mime_type', 'size'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): ?Ticket
    {
        return match (true) {
            $this->attachable instanceof Ticket => $this->attachable,
            $this->attachable instanceof TicketReply => $this->attachable->ticket,
            default => null,
        };
    }

    public function isInternal(): bool
    {
        return $this->attachable instanceof TicketReply && $this->attachable->is_internal;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isAgent()) {
            return $query;
        }

        return $query->where(function (Builder $visible) {
            $visible->where('attachable_type', Ticket::class)
                ->orWhere(function (Builder $replyLinked) {
                    $replyLinked->where('attachable_type', TicketReply::class)
                        ->whereIn(
                            'attachable_id',
                            TicketReply::query()->where('is_internal', false)->select('id'),
                        );
                });
        });
    }
}
