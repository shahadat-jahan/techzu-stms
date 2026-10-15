<?php

namespace Tests\Unit;

use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('accepts unassigned ticket successfully', function () {
    $agent = User::factory()->availableAgent()->create();
    $ticket = Ticket::factory()->create();

    $service = app(TicketAssignmentService::class);
    $result = $service->accept($ticket, $agent);

    expect($result)->toBeTrue();
    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($agent->id);
    expect($ticket->status->value)->toBe('in_progress');
    expect($ticket->last_assigned_at)->not->toBeNull();
});

it('returns false when ticket already assigned', function () {
    $agent1 = User::factory()->availableAgent()->create();
    $agent2 = User::factory()->availableAgent()->create();
    $ticket = Ticket::factory()->create([
        'assigned_to' => $agent1->id,
        'last_assigned_at' => now(),
    ]);

    $service = app(TicketAssignmentService::class);
    $result = $service->accept($ticket, $agent2);

    expect($result)->toBeFalse();
    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($agent1->id);
});

it('assigns ticket to available agent', function () {
    $agent = User::factory()->availableAgent()->create();
    $ticket = Ticket::factory()->create();

    $service = app(TicketAssignmentService::class);
    $result = $service->assign($ticket, $agent, $agent);

    expect($result)->toBeTrue();
    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($agent->id);
});

it('reassigns ticket from one agent to another', function () {
    $oldAgent = User::factory()->availableAgent()->create();
    $newAgent = User::factory()->availableAgent()->create();
    $ticket = Ticket::factory()->create([
        'assigned_to' => $oldAgent->id,
        'last_assigned_at' => now(),
    ]);

    $service = app(TicketAssignmentService::class);
    $result = $service->assign($ticket, $newAgent, $newAgent);

    expect($result)->toBeTrue();
    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($newAgent->id);
});

it('auto-assigns unassigned tickets', function () {
    $agent = User::factory()->availableAgent()->create();
    $ticket = Ticket::factory()->create();

    $service = app(TicketAssignmentService::class);
    $results = $service->autoAssignUnassigned($agent);

    expect($results['assigned'])->toBe(1);
    expect($results['skipped'])->toBe(0);
    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($agent->id);
});

it('skips tickets when no agents available', function () {
    $ticket = Ticket::factory()->create();

    $service = app(TicketAssignmentService::class);
    $results = $service->autoAssignUnassigned(User::factory()->admin()->create());

    expect($results['assigned'])->toBe(0);
    expect($results['skipped'])->toBe(1);
    $ticket->refresh();
    expect($ticket->assigned_to)->toBeNull();
});
