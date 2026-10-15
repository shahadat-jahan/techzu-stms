<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return match (true) {
            $user->isAdmin() => true,
            $user->isAgent() => $ticket->assigned_to === null || $ticket->assigned_to === $user->id,
            default => $ticket->created_by === $user->id,
        };
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function accept(User $user, Ticket $ticket): bool
    {
        return ($user->isAdmin() || $user->isAgent()) && $ticket->assigned_to === null;
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }

    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return ($user->isAdmin() || $user->isAgent()) && $this->view($user, $ticket);
    }

    public function changeStatus(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    public function changePriority(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    public function resolve(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    public function close(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    public function reopen(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }
}