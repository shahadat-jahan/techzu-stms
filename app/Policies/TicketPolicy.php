<?php

namespace App\Policies;

use App\Enums\TicketStatus;
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
        return $user->isCustomer() || $user->isAdmin();
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $ticket->status !== TicketStatus::Closed && $this->view($user, $ticket);
    }

    public function accept(User $user, Ticket $ticket): bool
    {
        return ($user->isAdmin() || $user->isAgent()) && $ticket->assigned_to === null;
    }

    public function assign(User $user, ?Ticket $ticket = null): bool
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
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isAgent()) {
            return $ticket->assigned_to === $user->id;
        }

        return false;
    }

    public function changePriority(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    public function resolve(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isAgent()) {
            return $ticket->assigned_to === $user->id;
        }

        return false;
    }

    public function close(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isAgent()) {
            return $ticket->assigned_to === $user->id;
        }

        // Customer can close their own ticket once it is resolved.
        return $user->isCustomer()
            && $ticket->created_by === $user->id
            && $ticket->status === TicketStatus::Resolved;
    }

    public function reopen(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isAgent()) {
            return $ticket->assigned_to === $user->id;
        }

        return $user->isCustomer() && $ticket->created_by === $user->id;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }
}
