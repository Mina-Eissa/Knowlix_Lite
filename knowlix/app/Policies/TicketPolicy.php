<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
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
        return true; // tenant isolation already handled by BelongsToWorkspace global scope
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->role === UserRole::Admin ||
        $ticket->assignee_id === $user->id ||
        ($ticket->requester_id === $user->id && $ticket->status=== TicketStatus::Open);
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->role === UserRole::Admin  ;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->role === UserRole::Admin || ($ticket->requester_id === $user->id && $ticket->status=== TicketStatus::Open);
    }
}
