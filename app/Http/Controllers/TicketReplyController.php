<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketReplyRequest;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class TicketReplyController extends Controller
{
    public function __construct(private readonly TicketService $ticketService) {}

    public function store(StoreTicketReplyRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize($request->validated('is_internal', false) ? 'addInternalNote' : 'reply', $ticket);

        $this->ticketService->addReply($ticket, $request->validated(), $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', $request->validated('is_internal', false) ? 'Internal note added.' : 'Reply added.');
    }

    public function update(StoreTicketReplyRequest $request, Ticket $ticket, TicketReply $reply): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $reply);

        $data = $request->validated();

        $this->ticketService->updateReply($ticket, $reply, $data, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $data['is_internal'] ?? false ? 'Internal note updated.' : 'Reply updated.',
                'body' => $reply->body,
            ]);
        }

        return Redirect::route('tickets.show', $ticket)
            ->with('success', $data['is_internal'] ?? false ? 'Internal note updated.' : 'Reply updated.');
    }
}
