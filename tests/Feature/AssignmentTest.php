<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentService;

it('agents can accept unassigned tickets', function () {
    $agent = User::factory()->availableAgent()->create();
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($agent)
        ->post(route('tickets.accept', $ticket));

    $response->assertRedirect();
    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($agent->id);
    expect($ticket->status->value)->toBe('in_progress');
});

it('forbids an agent from accepting an already assigned ticket', function () {
    $agent1 = User::factory()->availableAgent()->create();
    $agent2 = User::factory()->availableAgent()->create();
    $ticket = Ticket::factory()->create([
        'assigned_to' => $agent1->id,
        'last_assigned_at' => now(),
    ]);

    $response = $this->actingAs($agent2)
        ->post(route('tickets.accept', $ticket));

    $response->assertStatus(403);
    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($agent1->id);
});

it('admins can assign tickets to available agents', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->availableAgent()->create();
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('tickets.assign', $ticket), ['user_id' => $agent->id]);

    $response->assertRedirect();
    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($agent->id);
});

it('auto-assigns tickets on creation', function () {
    $agent = User::factory()->availableAgent()->create();
    $customer = User::factory()->customer()->create();
    $category = Category::factory()->create();

    $response = $this->actingAs($customer)
        ->post(route('tickets.store'), [
            'subject' => 'Test Ticket',
            'description' => 'Test Description',
            'priority' => 'medium',
            'category_id' => $category->id,
        ]);

    $response->assertRedirect();
    $ticket = Ticket::where('subject', 'Test Ticket')->first();
    expect($ticket->assigned_to)->toBe($agent->id);
});

it('admins can trigger auto-assign unassigned', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->availableAgent()->create();
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('tickets.assign-unassigned'));

    $response->assertRedirect();
    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($agent->id);
});

it('only one agent wins when two accept the same unassigned ticket concurrently', function () {
    $agent1 = User::factory()->availableAgent()->create();
    $agent2 = User::factory()->availableAgent()->create();
    $ticket = Ticket::factory()->create(); // unassigned

    // Both agents see the ticket as unassigned at the start.
    // The atomic conditional update ensures only one wins.
    $service = app(TicketAssignmentService::class);

    $result1 = $service->accept($ticket, $agent1);
    $result2 = $service->accept($ticket, $agent2);

    expect($result1 + $result2)->toBe(1); // exactly one succeeds
    $ticket->refresh();
    expect($ticket->assigned_to)->not->toBeNull();
    expect($ticket->status->value)->toBe('in_progress');

    // The winner's activity row exists; only one assignment activity.
    expect($ticket->activities()->where('action', 'assigned')->count())->toBe(1);
});

it('customers cannot accept tickets', function () {
    $customer = User::factory()->customer()->create();
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($customer)
        ->post(route('tickets.accept', $ticket));

    $response->assertStatus(403);
});

it('agents cannot assign tickets', function () {
    $agent = User::factory()->availableAgent()->create();
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($agent)
        ->post(route('tickets.assign', $ticket), ['user_id' => $agent->id]);

    $response->assertStatus(403);
});

it('rejects assignment to a non-agent user', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('tickets.assign', $ticket), ['user_id' => $customer->id]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('user_id');
});

it('rejects assignment to an unavailable agent', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->unavailableAgent()->create();
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('tickets.assign', $ticket), ['user_id' => $agent->id]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('user_id');
});
