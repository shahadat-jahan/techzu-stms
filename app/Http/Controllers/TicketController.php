<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Http\Requests\ChangeStatusRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $ticketService)
    {
    }

    public function index(Request $request): View
    {
        $tickets = Ticket::query()
            ->visibleTo($request->user())
            ->active()
            ->with(['customer', 'agent', 'category'])
            ->latest()
            ->paginate(config('support.pagination.per_page'))
            ->withQueryString();

        return view('tickets.index', compact('tickets'));
    }

    public function resolvedIndex(Request $request): View
    {
        $tickets = Ticket::query()
            ->visibleTo($request->user())
            ->closedOrResolved()
            ->with(['customer', 'agent', 'category'])
            ->latest()
            ->paginate(config('support.pagination.per_page'))
            ->withQueryString();

        return view('tickets.resolved', compact('tickets'));
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->ensureVisible($ticket, $request->user());

        $ticket->load(['customer', 'agent', 'category']);
        $activities = $ticket->activities()
            ->with('actor')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('tickets.show', compact('ticket', 'activities'));
    }

    public function create(): View
    {
        $categories = $this->activeCategories();

        return view('tickets.create', compact('categories'));
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $ticket = $this->ticketService->create($request->validated(), $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} created successfully.");
    }

    public function edit(Request $request, Ticket $ticket): View
    {
        $this->ensureVisible($ticket, $request->user());

        $categories = $this->activeCategories();

        return view('tickets.edit', compact('ticket', 'categories'));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->ensureVisible($ticket, $request->user());

        $this->ticketService->update($ticket, $request->validated(), $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', 'Ticket updated successfully.');
    }

    public function updateStatus(ChangeStatusRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->ensureVisible($ticket, $request->user());

        $this->ticketService->changeStatus(
            $ticket,
            TicketStatus::from($request->validated('status')),
            $request->user(),
        );

        return Redirect::route('tickets.show', $ticket)
            ->with('success', 'Ticket status updated successfully.');
    }

    public function resolve(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->ensureVisible($ticket, $request->user());

        $this->ticketService->resolve($ticket, $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', 'Ticket resolved.');
    }

    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->ensureVisible($ticket, $request->user());

        $this->ticketService->close($ticket, $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', 'Ticket closed.');
    }

    public function reopen(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->ensureVisible($ticket, $request->user());

        $this->ticketService->reopen($ticket, $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', 'Ticket reopened.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Category>
     */
    private function activeCategories(): Collection
    {
        return Category::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Interim visibility guard; replaced by TicketPolicy authorization in Phase 4.
     */
    private function ensureVisible(Ticket $ticket, User $user): void
    {
        abort_unless(
            Ticket::visibleTo($user)->whereKey($ticket)->exists(),
            403
        );
    }
}
