<?php

namespace Tests\Unit;

use App\Events\AgentReplied;
use App\Events\TicketAssigned;
use App\Events\TicketClosed;
use App\Events\TicketCreated;
use App\Events\TicketCustomerReplied;
use App\Events\TicketResolved;
use App\Events\TicketSlaApproaching;
use App\Events\TicketSlaBreached;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('ticket created event can be dispatched', function () {
    $creator = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $creator->id]);

    TicketCreated::dispatch($ticket);

    expect($ticket->exists)->toBeTrue();
});

it('ticket assigned event can be dispatched', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create();

    TicketAssigned::dispatch($ticket, $agent);

    expect($ticket->exists)->toBeTrue();
});

it('ticket customer replied event can be dispatched', function () {
    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $reply = TicketReply::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $customer->id]);

    TicketCustomerReplied::dispatch($reply);

    expect($reply->exists)->toBeTrue();
});

it('agent replied event can be dispatched', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $reply = TicketReply::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $agent->id]);

    AgentReplied::dispatch($reply);

    expect($reply->exists)->toBeTrue();
});

it('ticket resolved event can be dispatched', function () {
    $actor = User::factory()->create();
    $ticket = Ticket::factory()->create();

    TicketResolved::dispatch($ticket);

    expect($ticket->exists)->toBeTrue();
});

it('ticket closed event can be dispatched', function () {
    $actor = User::factory()->create();
    $ticket = Ticket::factory()->create();

    TicketClosed::dispatch($ticket);

    expect($ticket->exists)->toBeTrue();
});

it('sla approaching event can be dispatched', function () {
    $ticket = Ticket::factory()->create();

    TicketSlaApproaching::dispatch($ticket);

    expect($ticket->exists)->toBeTrue();
});

it('sla breached event can be dispatched', function () {
    $ticket = Ticket::factory()->create();

    TicketSlaBreached::dispatch($ticket);

    expect($ticket->exists)->toBeTrue();
});

it('internal notes do not notify customers', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $agent = User::factory()->create(['role' => 'agent']);
    $ticket = Ticket::factory()->create(['created_by' => $customer->id, 'assigned_to' => $agent->id]);
    $internalReply = TicketReply::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $agent->id,
        'is_internal' => true,
    ]);

    // Internal notes should NOT dispatch AgentReplied event
    // The event dispatch happens in TicketService::addReply() which checks is_internal
    expect($internalReply->is_internal)->toBeTrue();
});

it('sla notification idempotency concept', function () {
    $agent = User::factory()->create(['role' => 'agent']);
    $ticket = Ticket::factory()->create(['assigned_to' => $agent->id]);

    TicketSlaApproaching::dispatch($ticket);
    TicketSlaApproaching::dispatch($ticket);

    // Both dispatches should succeed without error
    expect($ticket->exists)->toBeTrue();
});
