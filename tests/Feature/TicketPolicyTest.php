<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketReply;
use App\Models\User;

it('authorizes admin for every ticket action', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    foreach ([
        'viewAny' => true,
        'view' => true,
        'create' => true,
        'update' => true,
        'accept' => true,
        'assign' => true,
        'reply' => true,
        'addInternalNote' => true,
        'changeStatus' => true,
        'changePriority' => true,
        'resolve' => true,
        'close' => true,
        'reopen' => true,
        'delete' => true,
    ] as $ability => $expected) {
        expect(Gate::forUser($admin)->allows($ability, $ticket))->toBeTrue();
    }
});

it('authorizes an available agent for its assigned and unassigned tickets', function () {
    $agent = User::factory()->availableAgent()->create();
    $assigned = Ticket::factory()->create(['assigned_to' => $agent->id, 'last_assigned_at' => now()]);
    $unassigned = Ticket::factory()->create();
    $theirs = Ticket::factory()->create(['assigned_to' => User::factory()->availableAgent()->create()->id]);

    expect(Gate::forUser($agent)->allows('view', $assigned))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('view', $unassigned))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('view', $theirs))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('create', Ticket::class))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('accept', $assigned))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('accept', $unassigned))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('reply', $assigned))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('reply', $theirs))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('addInternalNote', $assigned))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('addInternalNote', $theirs))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('update', $assigned))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('update', $unassigned))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('update', $theirs))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('delete', $theirs))->toBeFalse();
});



it('authorizes a customer only on their own tickets', function () {
    $customer = User::factory()->create();
    $own = Ticket::factory()->create(['created_by' => $customer->id]);
    $foreign = Ticket::factory()->create(['created_by' => User::factory()->create()->id]);

    expect(Gate::forUser($customer)->allows('view', $own))->toBeTrue()
        ->and(Gate::forUser($customer)->allows('view', $foreign))->toBeFalse()
        ->and(Gate::forUser($customer)->allows('update', $own))->toBeTrue()
        ->and(Gate::forUser($customer)->allows('update', $foreign))->toBeFalse()
        ->and(Gate::forUser($customer)->allows('accept', $own))->toBeFalse()
        ->and(Gate::forUser($customer)->allows('assign', $own))->toBeFalse()
        ->and(Gate::forUser($customer)->allows('reply', $own))->toBeTrue()
        ->and(Gate::forUser($customer)->allows('reply', $foreign))->toBeFalse()
        ->and(Gate::forUser($customer)->allows('addInternalNote', $own))->toBeFalse()
        ->and(Gate::forUser($customer)->allows('changeStatus', $own))->toBeTrue()
        ->and(Gate::forUser($customer)->allows('resolve', $own))->toBeTrue()
        ->and(Gate::forUser($customer)->allows('delete', $own))->toBeFalse();
});

it('lets staff update only their own tickets', function () {
    $agent = User::factory()->availableAgent()->create();
    $own = Ticket::factory()->assigned()->create(['assigned_to' => $agent->id]);
    $otherAgent = User::factory()->availableAgent()->create();
    $foreign = Ticket::factory()->create(['assigned_to' => $otherAgent->id]);

    expect(Gate::forUser($agent)->allows('update', $own))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('update', $foreign))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('changePriority', $own))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('changePriority', $foreign))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('changeStatus', $own))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('changeStatus', $foreign))->toBeFalse();
});

it('authorizes customer actions on their own ticket but not on owned ones', function () {
    $customer = User::factory()->create();
    $own = Ticket::factory()->create(['created_by' => $customer->id]);

    expect(Gate::forUser($customer)->allows('viewAny', Ticket::class))->toBeTrue()
        ->and(Gate::forUser($customer)->allows('create', Ticket::class))->toBeTrue()
        ->and(Gate::forUser($customer)->allows('accept', $own))->toBeFalse()
        ->and(Gate::forUser($customer)->allows('assign', $own))->toBeFalse()
        ->and(Gate::forUser($customer)->allows('addInternalNote', $own))->toBeFalse()
        ->and(Gate::forUser($customer)->allows('delete', $own))->toBeFalse();
});

it('forbids a customer from viewing or editing another customer ticket over HTTP', function () {
    $customer = User::factory()->create();
    $other = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $other->id]);

    $this->actingAs($customer)
        ->get(route('tickets.show', $ticket))
        ->assertForbidden()
        ->assertSessionHasNoErrors();

    $this->actingAs($customer)
        ->get(route('tickets.edit', $ticket))
        ->assertForbidden();
});

it('keeps internal notes out of a customer activity timeline', function () {
    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);
    $agent = User::factory()->agent()->create();

    TicketReply::factory()->internal()->create(['ticket_id' => $ticket->id, 'user_id' => $agent->id]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'actor_id' => $customer->id,
        'action' => 'replied',
        'meta' => ['message' => 'Public reply', 'is_internal' => false],
    ]);

    $activity = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'actor_id' => $agent->id,
        'action' => 'internal_note_added',
        'meta' => ['message' => 'Secret internal note about the customer', 'is_internal' => true],
    ]);

    $this->actingAs($customer)
        ->get(route('tickets.show', $ticket))
        ->assertDontSee('Secret internal note about the customer');

    expect($ticket->activities()->visibleTo($customer)->count())->toBe(1);
});

