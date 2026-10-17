<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        return $this->canAccess($user, $attachment);
    }

    public function download(User $user, Attachment $attachment): bool
    {
        return $this->canAccess($user, $attachment);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->isAdmin()
            || ($attachment->user_id === $user->id && $this->canAccess($user, $attachment));
    }

    private function canAccess(User $user, Attachment $attachment): bool
    {
        $ticket = $attachment->ticket();

        if ($ticket === null) {
            return false;
        }

        if (! $user->can('view', $ticket)) {
            return false;
        }

        return $user->isAdmin() || $user->isAgent() || ! $attachment->isInternal();
    }
}
