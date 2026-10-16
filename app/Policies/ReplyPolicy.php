<?php

namespace App\Policies;

use App\Models\TicketReply;
use App\Models\User;

class ReplyPolicy
{
    public function view(User $user, TicketReply $reply): bool
    {
        return $user->can('view', $reply->ticket)
            && ($reply->is_internal === false || $user->isAdmin() || $user->isAgent());
    }

    public function update(User $user, TicketReply $reply): bool
    {
        return $user->isAdmin() || $reply->user_id === $user->id;
    }

    public function delete(User $user, TicketReply $reply): bool
    {
        return $this->update($user, $reply);
    }
}
