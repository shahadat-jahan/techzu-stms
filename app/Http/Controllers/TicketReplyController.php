<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketReplyRequest;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class TicketReplyController extends Controller
{
    public function __construct(private readonly TicketService $ticketService)
    {
    }

    public function store(StoreTicketReplyRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize($request->validated('is_internal', false) ? 'addInternalNote' : 'reply', $ticket);

        $this->ticketService->addReply($ticket, $request->validated(), $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', $request->validated('is_internal', false) ? 'Internal note added.' : 'Reply added.');
    }
}
