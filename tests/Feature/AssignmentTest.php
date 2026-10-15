<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

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
