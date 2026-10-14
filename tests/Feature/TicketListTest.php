<?php

use App\Models\Ticket;
use App\Models\User;

it('redirects guests to the login page', function () {
    $this->get('/tickets')->assertRedirect(route('login'));
    $this->get('/tickets/resolved')->assertRedirect(route('login'));
});

it('paginates the active ticket list', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->count(15)->create();

    $this->actingAs($admin)->get(route('tickets.index'))
        ->assertOk()
        ->assertViewHas('tickets', fn ($tickets): bool => $tickets->count() === 10 && $tickets->total() === 15);

    $this->actingAs($admin)->get(route('tickets.index', ['page' => 2]))
        ->assertOk()
        ->assertViewHas('tickets', fn ($tickets): bool => $tickets->count() === 5);
});

it('paginates the resolved ticket list', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->count(12)->resolved()->create();

    $this->actingAs($admin)->get(route('tickets.resolved'))
        ->assertOk()
        ->assertViewHas('tickets', fn ($tickets): bool => $tickets->count() === 10 && $tickets->total() === 12);
});

it('shows a customer only their own tickets on both lists', function () {
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();

    $ownActive = Ticket::factory()->create(['created_by' => $customer->id]);
    $foreignActive = Ticket::factory()->create(['created_by' => $otherCustomer->id]);
    $ownResolved = Ticket::factory()->resolved()->create(['created_by' => $customer->id]);
    $foreignResolved = Ticket::factory()->resolved()->create(['created_by' => $otherCustomer->id]);

    $this->actingAs($customer)->get(route('tickets.index'))
        ->assertSee($ownActive->ticket_number)
        ->assertDontSee($foreignActive->ticket_number);

    $this->actingAs($customer)->get(route('tickets.resolved'))
        ->assertSee($ownResolved->ticket_number)
        ->assertDontSee($foreignResolved->ticket_number);
});

it('shows an agent their assigned tickets and the unassigned pool only', function () {
    $agent = User::factory()->availableAgent()->create();
    $otherAgent = User::factory()->availableAgent()->create();

    $mine = Ticket::factory()->create(['assigned_to' => $agent->id]);
    $unassigned = Ticket::factory()->create();
    $theirs = Ticket::factory()->create(['assigned_to' => $otherAgent->id]);

    $this->actingAs($agent)->get(route('tickets.index'))
        ->assertSee($mine->ticket_number)
        ->assertSee($unassigned->ticket_number)
        ->assertDontSee($theirs->ticket_number);
});

it('shows an admin every ticket', function () {
    $admin = User::factory()->admin()->create();

    $first = Ticket::factory()->create();
    $second = Ticket::factory()->resolved()->create();

    $this->actingAs($admin)->get(route('tickets.index'))
        ->assertSee($first->ticket_number)
        ->assertDontSee($second->ticket_number);

    $this->actingAs($admin)->get(route('tickets.resolved'))
        ->assertSee($second->ticket_number);
});

it('separates active tickets from resolved and closed ones', function () {
    $admin = User::factory()->admin()->create();

    $active = Ticket::factory()->create();
    $resolved = Ticket::factory()->resolved()->create();
    $closed = Ticket::factory()->closed()->create();

    $this->actingAs($admin)->get(route('tickets.index'))
        ->assertSee($active->ticket_number)
        ->assertDontSee($resolved->ticket_number)
        ->assertDontSee($closed->ticket_number);
});
