<?php

use App\Enums\SlaStatus;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

it('reports within sla while the warning window has not been reached', function (): void {
    $ticket = Ticket::factory()->create([
        'sla_warning_at' => now()->addHour(),
        'due_at' => now()->addHours(4),
    ]);

    expect($ticket->slaStatus())->toBe(SlaStatus::Within);
});

it('reports approaching once the warning window is reached but before the deadline', function (): void {
    $ticket = Ticket::factory()->create([
        'sla_warning_at' => now()->subMinutes(30),
        'due_at' => now()->addHours(4),
    ]);

    expect($ticket->slaStatus())->toBe(SlaStatus::Approaching);
});

it('reports breached once the deadline has passed', function (): void {
    $ticket = Ticket::factory()->create([
        'sla_warning_at' => now()->subHours(2),
        'due_at' => now()->subHour(),
    ]);

    expect($ticket->slaStatus())->toBe(SlaStatus::Breached);
});

it('reports within sla when no deadline is set', function (): void {
    $ticket = Ticket::factory()->create([
        'sla_warning_at' => null,
        'due_at' => null,
    ]);

    expect($ticket->slaStatus())->toBe(SlaStatus::Within);
});

it('reports sla met when a resolved ticket finished before its deadline', function (): void {
    $ticket = Ticket::factory()->resolved()->create([
        'due_at' => now()->addHour(),
        'resolved_at' => now()->subMinutes(10),
    ]);

    expect($ticket->slaStatus())->toBe(SlaStatus::Met);
});

it('reports sla breached when a resolved ticket finished after its deadline', function (): void {
    $ticket = Ticket::factory()->resolved()->create([
        'due_at' => now()->subHour(),
        'resolved_at' => now()->subMinutes(10),
    ]);

    expect($ticket->slaStatus())->toBe(SlaStatus::Breached);
});

it('reports sla breached when a closed ticket finished after its deadline', function (): void {
    $ticket = Ticket::factory()->closed()->create([
        'due_at' => now()->subHour(),
        'closed_at' => now()->subMinutes(10),
    ]);

    expect($ticket->slaStatus())->toBe(SlaStatus::Breached);
});

it('agrees with the sla scopes for active tickets', function (): void {
    $within = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_warning_at' => now()->addHour(),
        'due_at' => now()->addHours(4),
    ]);

    $approaching = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_warning_at' => now()->subMinutes(30),
        'due_at' => now()->addHours(4),
    ]);

    $breached = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_warning_at' => now()->subHours(2),
        'due_at' => now()->subHour(),
    ]);

    expect($within->fresh()->slaStatus())->toBe(SlaStatus::Within)
        ->and($approaching->fresh()->slaStatus())->toBe(SlaStatus::Approaching)
        ->and($breached->fresh()->slaStatus())->toBe(SlaStatus::Breached)
        ->and(Ticket::slaWithin()->whereKey($within)->exists())->toBeTrue()
        ->and(Ticket::slaApproaching()->whereKey($approaching)->exists())->toBeTrue()
        ->and(Ticket::slaBreached()->whereKey($breached)->exists())->toBeTrue();
});

it('renders the sla badge on the ticket page', function (): void {
    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'created_by' => $customer->id,
        'sla_warning_at' => now()->subMinutes(30),
        'due_at' => now()->addHours(4),
    ]);

    $this->actingAs($customer)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Approaching Breach');
});
