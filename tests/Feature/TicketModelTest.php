<?php

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketReply;
use App\Models\User;

it('exposes customer, agent, category, replies, activities, and attachments relationships', function () {
    $customer = User::factory()->create();
    $agent = User::factory()->availableAgent()->create();
    $ticket = Ticket::factory()->create([
        'created_by' => $customer->id,
        'assigned_to' => $agent->id,
    ]);
    $reply = TicketReply::factory()->create(['ticket_id' => $ticket->id]);
    $activity = TicketActivity::factory()->create(['ticket_id' => $ticket->id]);
    $attachment = Attachment::factory()->forTicket($ticket)->create();

    expect($ticket->customer->id)->toBe($customer->id)
        ->and($ticket->agent->id)->toBe($agent->id)
        ->and($ticket->category)->not->toBeNull()
        ->and($ticket->replies->pluck('id'))->toContain($reply->id)
        ->and($ticket->activities->pluck('id'))->toContain($activity->id)
        ->and($ticket->attachments->pluck('id'))->toContain($attachment->id);
});

it('casts priority and status to enums', function () {
    $ticket = Ticket::factory()->priority(Priority::Critical)->status(TicketStatus::InProgress)->create();

    expect($ticket->priority)->toBe(Priority::Critical)
        ->and($ticket->status)->toBe(TicketStatus::InProgress);
});

it('supports attachments on replies', function () {
    $reply = TicketReply::factory()->create();
    $attachment = Attachment::factory()->create([
        'attachable_type' => TicketReply::class,
        'attachable_id' => $reply->id,
    ]);

    expect($reply->attachments->pluck('id'))->toContain($attachment->id);
});

it('scopes active and closed-or-resolved tickets by status', function () {
    $open = Ticket::factory()->status(TicketStatus::Open)->create();
    $inProgress = Ticket::factory()->status(TicketStatus::InProgress)->create();
    $waiting = Ticket::factory()->status(TicketStatus::WaitingForCustomer)->create();
    $resolved = Ticket::factory()->resolved()->create();
    $closed = Ticket::factory()->closed()->create();

    expect(Ticket::active()->pluck('id')->all())->toEqualCanonicalizing([$open->id, $inProgress->id, $waiting->id])
        ->and(Ticket::closedOrResolved()->pluck('id')->all())->toEqualCanonicalizing([$resolved->id, $closed->id]);
});

it('scopes unassigned tickets', function () {
    $agent = User::factory()->availableAgent()->create();
    $unassigned = Ticket::factory()->create();
    $assigned = Ticket::factory()->create(['assigned_to' => $agent->id]);

    expect(Ticket::unassigned()->pluck('id'))->toContain($unassigned->id)
        ->and(Ticket::unassigned()->pluck('id'))->not->toContain($assigned->id);
});

it('scopes tickets assigned to a specific agent', function () {
    $agent = User::factory()->availableAgent()->create();
    $otherAgent = User::factory()->availableAgent()->create();
    $mine = Ticket::factory()->create(['assigned_to' => $agent->id]);
    $theirs = Ticket::factory()->create(['assigned_to' => $otherAgent->id]);

    expect(Ticket::assignedTo($agent)->pluck('id'))->toContain($mine->id)
        ->and(Ticket::assignedTo($agent)->pluck('id'))->not->toContain($theirs->id);
});

it('shows customers only their own tickets', function () {
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();
    $own = Ticket::factory()->create(['created_by' => $customer->id]);
    $foreign = Ticket::factory()->create(['created_by' => $otherCustomer->id]);

    expect(Ticket::visibleTo($customer)->pluck('id'))->toContain($own->id)
        ->and(Ticket::visibleTo($customer)->pluck('id'))->not->toContain($foreign->id);
});

it('shows agents their assigned tickets and the unassigned pool but not other agents tickets', function () {
    $agent = User::factory()->availableAgent()->create();
    $otherAgent = User::factory()->availableAgent()->create();
    $assignedToMe = Ticket::factory()->create(['assigned_to' => $agent->id]);
    $unassigned = Ticket::factory()->create();
    $assignedToOther = Ticket::factory()->create(['assigned_to' => $otherAgent->id]);

    $visibleIds = Ticket::visibleTo($agent)->pluck('id');

    expect($visibleIds)->toContain($assignedToMe->id)
        ->and($visibleIds)->toContain($unassigned->id)
        ->and($visibleIds)->not->toContain($assignedToOther->id);
});

it('shows admins all tickets', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->availableAgent()->create();
    $customer = User::factory()->create();
    $assigned = Ticket::factory()->create(['assigned_to' => $agent->id, 'created_by' => $customer->id]);
    $unassigned = Ticket::factory()->create(['created_by' => $customer->id]);

    expect(Ticket::visibleTo($admin)->pluck('id')->all())->toEqualCanonicalizing([$assigned->id, $unassigned->id]);
});

it('scopes sla breached, approaching, and within tickets against fixed timestamps', function () {
    $breached = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_warning_at' => now()->subHours(3),
        'due_at' => now()->subHour(),
    ]);
    $approaching = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_warning_at' => now()->subMinutes(30),
        'due_at' => now()->addHour(),
    ]);
    $within = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_warning_at' => now()->addHours(2),
        'due_at' => now()->addHours(4),
    ]);

    expect(Ticket::slaBreached()->pluck('id')->all())->toBe([$breached->id])
        ->and(Ticket::slaApproaching()->pluck('id')->all())->toBe([$approaching->id])
        ->and(Ticket::slaWithin()->pluck('id'))->toContain($within->id)
        ->and(Ticket::slaWithin()->pluck('id'))->not->toContain($breached->id, $approaching->id);
});

it('excludes resolved and closed tickets from sla scopes even past due', function () {
    Ticket::factory()->resolved()->create(['due_at' => now()->subDay()]);
    Ticket::factory()->closed()->create(['due_at' => now()->subDay()]);

    expect(Ticket::slaBreached()->count())->toBe(0)
        ->and(Ticket::slaApproaching()->count())->toBe(0)
        ->and(Ticket::slaWithin()->count())->toBe(0);
});

it('excludes soft-deleted tickets from queries', function () {
    $ticket = Ticket::factory()->create();
    $ticket->delete();

    expect(Ticket::count())->toBe(0);
});
