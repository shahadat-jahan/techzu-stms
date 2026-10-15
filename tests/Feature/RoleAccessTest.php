<?php

use App\Models\Ticket;
use App\Models\User;

it('shows ticket management actions to agents and admins', function () {
    $agent = User::factory()->availableAgent()->create();
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

    $this->actingAs($agent)->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Actions');

    $this->actingAs($admin)->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Actions');
});

it('hides ticket management actions from customers', function () {
    $customer = User::factory()->customer()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

    $this->actingAs($customer)->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertDontSee('Actions');
});
