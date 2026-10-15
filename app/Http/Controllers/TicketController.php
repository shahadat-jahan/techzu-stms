<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Http\Requests\AcceptTicketRequest;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\ChangeStatusRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentService;
use App\Services\TicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly TicketAssignmentService $assignmentService,
    ) {}

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
        $this->authorize('view', $ticket);

        $ticket->load(['customer', 'agent', 'category']);

        $user = $request->user();

        $replies = $ticket->visibleReplies($user)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $attachments = $ticket->visibleAttachments($user)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $activities = $ticket->visibleActivities($user)
            ->with('actor')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $availableAgents = null;
        if ($user !== null && $user->isAdmin()) {
            $availableAgents = User::where('role', 'agent')
                ->where('is_available', true)
                ->withCount([
                    'tickets as active_tickets_count' => function ($query) {
                        $query->whereIn('status', [
                            TicketStatus::Open->value,
                            TicketStatus::InProgress->value,
                            TicketStatus::WaitingForCustomer->value,
                        ]);
                    },
                ])
                ->get();
        }

        return view('tickets.show', compact('ticket', 'replies', 'attachments', 'activities', 'availableAgents'));
    }

    public function create(): View
    {
        $this->authorize('create', Ticket::class);

        $categories = $this->activeCategories();

        return view('tickets.create', compact('categories'));
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $this->authorize('create', Ticket::class);

        $ticket = $this->ticketService->create($request->validated(), $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} created successfully.");
    }

    public function edit(Request $request, Ticket $ticket): View
    {
        $this->authorize('update', $ticket);

        $categories = $this->activeCategories();

        return view('tickets.edit', compact('ticket', 'categories'));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $this->ticketService->update($ticket, $request->validated(), $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', 'Ticket updated successfully.');
    }

    public function updateStatus(ChangeStatusRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('changeStatus', $ticket);

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
        $this->authorize('resolve', $ticket);

        $this->ticketService->resolve($ticket, $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', 'Ticket resolved.');
    }

    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('close', $ticket);

        $this->ticketService->close($ticket, $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', 'Ticket closed.');
    }

    public function reopen(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('reopen', $ticket);

        $this->ticketService->reopen($ticket, $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', 'Ticket reopened.');
    }

    public function accept(AcceptTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('accept', $ticket);

        $success = $this->assignmentService->accept($ticket, $request->user());
        if (! $success) {
            return back()->with('error', 'Ticket was already assigned by another agent.');
        }

        return back()->with('success', 'Ticket accepted successfully.');
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('assign', $ticket);

        $agent = User::findOrFail($request->validated('user_id'));
        $this->assignmentService->assign($ticket, $agent, $request->user());

        return back()->with('success', 'Ticket assigned successfully.');
    }

    public function assignUnassigned(Request $request): RedirectResponse
    {
        $this->authorize('assign', Ticket::class);

        $results = $this->assignmentService->autoAssignUnassigned($request->user());
        return back()->with('success', "Auto-assigned {$results['assigned']} tickets, skipped {$results['skipped']}.");
    }

    /**
     * @return Collection<int, Category>
     */
    private function activeCategories(): Collection
    {
        return Category::where('is_active', true)->orderBy('name')->get();
    }
}
