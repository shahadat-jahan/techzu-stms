<?php

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Validation\ValidationException;

it('applies valid status transitions and stamps timestamps', function () {
    $service = app(TicketService::class);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create();

    $service->changeStatus($ticket, TicketStatus::InProgress, $agent);

    expect($ticket->refresh()->status)->toBe(TicketStatus::InProgress);

    $service->resolve($ticket, $agent);
    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Resolved)
        ->and($ticket->resolved_at)->not->toBeNull();

    $service->close($ticket, $agent);
    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and($ticket->closed_at)->not->toBeNull()
        ->and($ticket->resolved_at)->not->toBeNull();
});

it('refuses an invalid status transition and logs nothing', function () {
    $service = app(TicketService::class);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->closed()->create();

    expect(fn () => $service->changeStatus($ticket, TicketStatus::InProgress, $agent))
        ->toThrow(ValidationException::class);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Closed)
        ->and(TicketActivity::count())->toBe(0);
});

it('reopens a resolved ticket into in progress and clears resolved_at', function () {
    $service = app(TicketService::class);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->resolved()->create();

    $service->reopen($ticket, $agent);

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::InProgress)
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->activities()->where('action', 'reopened')->count())->toBe(1);
});

it('reopens a closed ticket into open and clears both timestamps', function () {
    $service = app(TicketService::class);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->closed()->create();

    $service->reopen($ticket, $agent);

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull();
});

it('refuses to reopen an active ticket', function () {
    $service = app(TicketService::class);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create();

    expect(fn () => $service->reopen($ticket, $agent))->toThrow(ValidationException::class);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Open)
        ->and(TicketActivity::count())->toBe(0);
});

it('keeps quiet when the priority does not actually change', function () {
    $service = app(TicketService::class);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->priority(Priority::High)->create();

    $service->changePriority($ticket, Priority::High, $agent);

    expect($ticket->activities()->count())->toBe(0);
});

it('records old and new priority when the priority changes', function () {
    $service = app(TicketService::class);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->priority(Priority::Medium)->create();

    $service->changePriority($ticket, Priority::Critical, $agent);

    $activity = $ticket->activities()->where('action', 'priority_changed')->sole();

    expect($ticket->refresh()->priority)->toBe(Priority::Critical)
        ->and($activity->meta['old_priority'])->toBe('medium')
        ->and($activity->meta['new_priority'])->toBe('critical');
});
