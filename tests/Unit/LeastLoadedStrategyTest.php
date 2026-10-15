<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\LeastLoadedAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns null when no agents available', function () {
    $strategy = app(LeastLoadedAssignmentService::class);
    $ticket = Ticket::factory()->create();

    expect($strategy->assignTicket($ticket))->toBeNull();
});

it('returns null when all agents are at max capacity', function () {
    User::factory()->availableAgent()->create(['max_active_tickets' => 1]);

    Ticket::factory()->create(['assigned_to' => User::first()->id]);

    $strategy = app(LeastLoadedAssignmentService::class);
    $ticket = Ticket::factory()->create();

    expect($strategy->assignTicket($ticket))->toBeNull();
});

it('prefers agents mapped to the ticket category', function () {
    $category = Category::factory()->create();
    $specialist = User::factory()->availableAgent()->create();
    $specialist->categories()->attach($category);

    User::factory()->availableAgent()->create();

    $strategy = app(LeastLoadedAssignmentService::class);
    $ticket = Ticket::factory()->create(['category_id' => $category->id]);

    expect($strategy->assignTicket($ticket)->id)->toBe($specialist->id);
});

it('falls back to all available agents when no category match', function () {
    $category = Category::factory()->create();
    $agent = User::factory()->availableAgent()->create();

    $strategy = app(LeastLoadedAssignmentService::class);
    $ticket = Ticket::factory()->create(['category_id' => $category->id]);

    expect($strategy->assignTicket($ticket)->id)->toBe($agent->id);
});

it('skips agents at max_active_tickets', function () {
    $busy = User::factory()->availableAgent()->create(['max_active_tickets' => 1]);
    $free = User::factory()->availableAgent()->create(['max_active_tickets' => 1]);

    Ticket::factory()->create(['assigned_to' => $busy->id]);

    $strategy = app(LeastLoadedAssignmentService::class);
    $ticket = Ticket::factory()->create();

    expect($strategy->assignTicket($ticket)->id)->toBe($free->id);
});

it('chooses the least loaded agent', function () {
    $busy = User::factory()->availableAgent()->create(['max_active_tickets' => 5]);
    $free = User::factory()->availableAgent()->create(['max_active_tickets' => 5]);

    Ticket::factory()->create(['assigned_to' => $busy->id]);

    $strategy = app(LeastLoadedAssignmentService::class);
    $ticket = Ticket::factory()->create();

    expect($strategy->assignTicket($ticket)->id)->toBe($free->id);
});

it('tie-breaks by oldest last assignment, nulls first', function () {
    $recentlyAssigned = User::factory()->availableAgent()->create();
    $longAgoAssigned = User::factory()->availableAgent()->create();
    $neverAssigned = User::factory()->availableAgent()->create();

    Ticket::factory()->create([
        'assigned_to' => $recentlyAssigned->id,
        'last_assigned_at' => now()->subHour(),
    ]);
    Ticket::factory()->create([
        'assigned_to' => $longAgoAssigned->id,
        'last_assigned_at' => now()->subDays(5),
    ]);

    $strategy = app(LeastLoadedAssignmentService::class);
    $ticket = Ticket::factory()->create();

    expect($strategy->assignTicket($ticket)->id)->toBe($neverAssigned->id);
});

it('ignores unavailable agents', function () {
    User::factory()->unavailableAgent()->create();

    $strategy = app(LeastLoadedAssignmentService::class);
    $ticket = Ticket::factory()->create();

    expect($strategy->assignTicket($ticket))->toBeNull();
});
