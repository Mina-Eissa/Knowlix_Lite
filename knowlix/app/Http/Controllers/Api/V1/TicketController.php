<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\TransitionTicketStatusRequest;
use App\Http\Resources\TicketResource;
use App\Enums\TicketStatus;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TicketController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Ticket::class);

        $tickets = Ticket::query()
            ->with(['requester', 'assignee'])
            ->latest()
            ->paginate(15);

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request, TicketService $ticketService)
    {
        $ticket = $ticketService->create($request->validated(), $request->user());

        return new TicketResource($ticket)->response()->setStatusCode(201);
    }

    public function show(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        return new TicketResource($ticket);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket)
    {
        $ticket->update($request->validated());

        return new TicketResource($ticket);
    }

    public function destroy(Ticket $ticket)
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();

        return response()->noContent();
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket, TicketService $ticketService)
    {
        $assignee = \App\Models\User::findOrFail($request->validated('assignee_id'));

        $ticket = $ticketService->assign($ticket, $assignee);

        return new TicketResource($ticket);
    }

    public function transitionStatus(TransitionTicketStatusRequest $request, Ticket $ticket, TicketService $ticketService)
    {
        $ticket = $ticketService->transitionStatus(
            $ticket,
            \App\Enums\TicketStatus::from($request->validated('status'))
        );

        return new TicketResource($ticket);
    }
}
