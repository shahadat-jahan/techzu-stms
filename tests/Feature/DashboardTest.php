<?php

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

it('redirects guests to login when accessing dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('renders dashboard with stats for authenticated user', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->create(['status' => TicketStatus::Open, 'priority' => Priority::Critical]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertViewIs('dashboard')
        ->assertViewHas('stats')
        ->assertSee('Total Open')
        ->assertSee('Critical Priority')
        ->assertSee('Unassigned Pool');
});

it('returns JSON stats for polling when requested', function () {
    $agent = User::factory()->availableAgent()->create();
    Ticket::factory()->create(['assigned_to' => $agent->id]);

    $response = $this->actingAs($agent)
        ->getJson(route('dashboard'));

    $response->assertOk()
        ->assertJsonStructure([
            'summary' => ['total_open', 'unassigned', 'critical', 'breached', 'approaching', 'resolved'],
            'by_priority',
            'by_agent',
        ]);
});

it('renders dashboard cards linking to pre-filtered ticket listings', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee(route('tickets.index'))
        ->assertSee(route('tickets.index', ['assigned_to' => 'unassigned']))
        ->assertSee(route('tickets.index', ['priority' => 'critical']))
        ->assertSee(route('tickets.index', ['sla_status' => 'breached']))
        ->assertSee(route('tickets.index', ['sla_status' => 'approaching']))
        ->assertSee(route('tickets.resolved'));
});
