<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Jobs\DeliverWebhookEvent;
use App\Enums\WebhookEventStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketService
{
    private const ALLOWED_TRANSITIONS = [
        'open' => ['pending'],
        'pending' => ['resolved'],
        'resolved' => ['closed', 'open'],
        'closed' => ['open'],
    ];

    public function create(array $data, User $requester): Ticket
    {
        return DB::transaction(function () use ($data, $requester) {
            $ticket = Ticket::create([
                ...$data,
                'requester_id' => $requester->id,
                'status' => TicketStatus::Open,
                'priority' => $data['priority'] ?? TicketPriority::Normal,
            ]);

            $event = WebhookEvent::create([
                'workspace_id' => $ticket->workspace_id,
                'event_id' => (string) Str::ulid(),
                'type' => 'ticket.created',
                'payload' => [
                    'ticket_id' => $ticket->id,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status->value,
                    'priority' => $ticket->priority->value,
                    'requester_id' => $ticket->requester_id,
                ],
                'status' => WebhookEventStatus::Pending,
            ]);

            DeliverWebhookEvent::dispatch($event)->afterCommit();

            return $ticket->fresh(['requester', 'assignee']);
        });
    }

    public function assign(Ticket $ticket, User $assignee): Ticket
    {
        if ($assignee->workspace_id !== $ticket->workspace_id) {
            throw ValidationException::withMessages([
                'assignee_id' => 'Assignee must belong to the same workspace as the ticket.',
            ]);
        }

        $ticket->update([
            'assignee_id' => $assignee->id,
        ]);

        return $ticket->fresh(['requester', 'assignee']);
    }

    public function transitionStatus(Ticket $ticket, TicketStatus $newStatus): Ticket
    {
        $current = $ticket->status->value;
        $target = $newStatus->value;

        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        if (! in_array($target, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from '{$current}' to '{$target}'.",
            ]);
        }

        return DB::transaction(function () use ($ticket, $current, $target, $newStatus) {
            $ticket->update([
                'status' => $newStatus,
            ]);

            $event = WebhookEvent::create([
                'workspace_id' => $ticket->workspace_id,
                'event_id' => (string) Str::ulid(),
                'type' => 'ticket.status_changed',
                'payload' => [
                    'ticket_id' => $ticket->id,
                    'from_status' => $current,
                    'to_status' => $target,
                ],
                'status' => WebhookEventStatus::Pending,
            ]);

            DeliverWebhookEvent::dispatch($event)->afterCommit();

            return $ticket->fresh(['requester', 'assignee']);
        });
    }
}
