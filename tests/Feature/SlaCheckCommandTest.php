<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Events\TicketSlaApproaching;
use App\Events\TicketSlaBreached;
use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function approachingTicket(string $number): Ticket
{
    $agent = User::factory()->create();

    return Ticket::create([
        'ticket_number' => $number,
        'subject' => 'Approaching',
        'description' => 'Warning reached, still inside the resolution window.',
        'priority' => Priority::Medium,
        'status' => TicketStatus::Open,
        'category_id' => Category::factory()->create()->id,
        'created_by' => $agent->id,
        'assigned_to' => $agent->id,
        'sla_warning_at' => now()->subHour(),
        'due_at' => now()->addHour(),
        'external_ref' => null,
    ]);
}

function breachedTicket(string $number, TicketStatus $status = TicketStatus::Open): Ticket
{
    $agent = User::factory()->create();

    return Ticket::create([
        'ticket_number' => $number,
        'subject' => 'Breached',
        'description' => 'Past the resolution deadline.',
        'priority' => Priority::Medium,
        'status' => $status,
        'category_id' => Category::factory()->create()->id,
        'created_by' => $agent->id,
        'assigned_to' => $agent->id,
        'sla_warning_at' => now()->subHours(2),
        'due_at' => now()->subHour(),
        'external_ref' => null,
    ]);
}

it('dispatches the approaching event once per ticket', function (): void {
    SlaPolicy::create(['priority' => Priority::Medium->value, 'resolution_minutes' => 1440, 'warning_minutes' => 1152, 'is_active' => true]);
    $ticket = approachingTicket('TKT-2026-000001');

    Event::fake();

    $this->artisan('sla:check');
    $this->artisan('sla:check');

    Event::assertDispatched(TicketSlaApproaching::class, fn ($event): bool => $event->ticket->id === $ticket->id);
    Event::assertDispatchedTimes(TicketSlaApproaching::class, 1);
});

it('dispatches the breached event once per ticket', function (): void {
    SlaPolicy::create(['priority' => Priority::Medium->value, 'resolution_minutes' => 1440, 'warning_minutes' => 1152, 'is_active' => true]);
    $ticket = breachedTicket('TKT-2026-000002');

    Event::fake();

    $this->artisan('sla:check');
    $this->artisan('sla:check');

    Event::assertDispatched(TicketSlaBreached::class, fn ($event): bool => $event->ticket->id === $ticket->id);
    Event::assertDispatchedTimes(TicketSlaBreached::class, 1);
});

it('excludes resolved and closed tickets', function (TicketStatus $status): void {
    SlaPolicy::create(['priority' => Priority::Medium->value, 'resolution_minutes' => 1440, 'warning_minutes' => 1152, 'is_active' => true]);
    breachedTicket('TKT-2026-000003', $status);

    Event::fake();

    $this->artisan('sla:check');

    Event::assertNotDispatched(TicketSlaApproaching::class);
    Event::assertNotDispatched(TicketSlaBreached::class);
})->with([TicketStatus::Resolved, TicketStatus::Closed]);

it('excludes soft deleted tickets', function (): void {
    SlaPolicy::create(['priority' => Priority::Medium->value, 'resolution_minutes' => 1440, 'warning_minutes' => 1152, 'is_active' => true]);
    breachedTicket('TKT-2026-000004')->delete();

    Event::fake();

    $this->artisan('sla:check');

    Event::assertNotDispatched(TicketSlaApproaching::class);
    Event::assertNotDispatched(TicketSlaBreached::class);
});

it('records the escalation timestamps', function (): void {
    SlaPolicy::create(['priority' => Priority::Medium->value, 'resolution_minutes' => 1440, 'warning_minutes' => 1152, 'is_active' => true]);
    $ticket = approachingTicket('TKT-2026-000005');

    $this->artisan('sla:check');

    $ticket->refresh();

    expect($ticket->warning_notified_at)->not->toBeNull()
        ->and($ticket->escalated_at)->toBeNull();
});

it('writes an escalated activity when a ticket breaches', function (): void {
    SlaPolicy::create(['priority' => Priority::Medium->value, 'resolution_minutes' => 1440, 'warning_minutes' => 1152, 'is_active' => true]);
    $ticket = breachedTicket('TKT-2026-000006');

    $this->artisan('sla:check');

    $activity = $ticket->activities()->where('action', 'escalated')->sole();

    expect($activity->actor_id)->toBeNull()
        ->and($activity->meta['message'])->toContain('breached');
});
