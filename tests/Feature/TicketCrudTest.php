<?php

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

it('lets a customer create a ticket with a sequential number', function () {
    $customer = User::factory()->create();
    $category = Category::factory()->create();

    $response = $this->actingAs($customer)->post(route('tickets.store'), [
        'subject' => 'VPN not connecting',
        'description' => 'Cannot reach the office VPN since this morning.',
        'priority' => Priority::High->value,
        'category_id' => $category->id,
    ]);

    $ticket = Ticket::sole();

    $response->assertRedirect(route('tickets.show', $ticket));

    expect($ticket->ticket_number)->toBe(sprintf('TKT-%d-000001', now()->year))
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->priority)->toBe(Priority::High)
        ->and($ticket->created_by)->toBe($customer->id)
        ->and($ticket->due_at)->toBeNull()
        ->and($ticket->sla_warning_at)->toBeNull()
        ->and($ticket->activities()->where('action', 'created')->count())->toBe(1);
});

it('increments the ticket number for each new ticket', function () {
    $customer = User::factory()->create();
    $category = Category::factory()->create();
    $payload = [
        'subject' => 'Repeated issue',
        'description' => 'Same description reused.',
        'priority' => Priority::Low->value,
        'category_id' => $category->id,
    ];

    $this->actingAs($customer)->post(route('tickets.store'), $payload);
    $this->actingAs($customer)->post(route('tickets.store'), $payload);

    expect(Ticket::orderBy('id')->pluck('ticket_number')->all())->toBe([
        sprintf('TKT-%d-000001', now()->year),
        sprintf('TKT-%d-000002', now()->year),
    ]);
});

it('validates required fields on ticket creation', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)->post(route('tickets.store'), [])
        ->assertSessionHasErrors(['subject', 'description', 'priority', 'category_id']);
});

it('forbids an agent from creating a ticket', function () {
    $agent = User::factory()->agent()->create();
    $category = Category::factory()->create();

    $this->actingAs($agent)->post(route('tickets.store'), [
        'subject' => 'Agent created ticket',
        'description' => 'Should be rejected.',
        'priority' => Priority::Low->value,
        'category_id' => $category->id,
    ])->assertForbidden();

    expect(Ticket::where('subject', 'Agent created ticket')->exists())->toBeFalse();
});

it('rejects a duplicate external reference', function () {
    $customer = User::factory()->customer()->create();
    $category = Category::factory()->create();
    Ticket::factory()->withExternalRef('CRM-1')->create();

    $this->actingAs($customer)->post(route('tickets.store'), [
        'subject' => 'Duplicate import row',
        'description' => 'Should be rejected.',
        'priority' => Priority::Low->value,
        'category_id' => $category->id,
        'external_ref' => 'CRM-1',
    ])->assertSessionHasErrors('external_ref');
});

it('lets staff update subject, description, priority, and category', function () {
    $agent = User::factory()->agent()->create();
    $newCategory = Category::factory()->create();
    $ticket = Ticket::factory()->priority(Priority::Medium)->create();

    $response = $this->actingAs($agent)->patch(route('tickets.update', $ticket), [
        'subject' => 'Updated subject',
        'description' => 'Updated description',
        'priority' => Priority::Critical->value,
        'category_id' => $newCategory->id,
    ]);

    $response->assertRedirect(route('tickets.show', $ticket));

    $ticket->refresh();

    expect($ticket->subject)->toBe('Updated subject')
        ->and($ticket->priority)->toBe(Priority::Critical)
        ->and($ticket->category_id)->toBe($newCategory->id)
        ->and($ticket->activities()->where('action', 'priority_changed')->count())->toBe(1)
        ->and($ticket->activities()->where('action', 'updated')->count())->toBe(1);
});

it('ignores priority when a customer updates a ticket', function () {
    $customer = User::factory()->create();
    $ticket = Ticket::factory()->priority(Priority::Medium)->create(['created_by' => $customer->id]);

    $this->actingAs($customer)->patch(route('tickets.update', $ticket), [
        'subject' => 'Customer edit',
        'description' => 'Edited by the customer',
        'priority' => Priority::Critical->value,
    ])->assertRedirect(route('tickets.show', $ticket));

    $ticket->refresh();

    expect($ticket->subject)->toBe('Customer edit')
        ->and($ticket->priority)->toBe(Priority::Medium)
        ->and($ticket->activities()->where('action', 'priority_changed')->count())->toBe(0)
        ->and($ticket->activities()->where('action', 'updated')->count())->toBe(1);
});

it('resolves, closes, and reopens a ticket through the endpoints', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($agent)->post(route('tickets.resolve', $ticket))
        ->assertRedirect(route('tickets.show', $ticket));

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Resolved)
        ->and($ticket->resolved_at)->not->toBeNull();

    $this->actingAs($agent)->post(route('tickets.close', $ticket));

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and($ticket->closed_at)->not->toBeNull();

    $this->actingAs($agent)->post(route('tickets.reopen', $ticket));

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull();
});

it('rejects an invalid status transition without writing an activity', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->closed()->create();

    $this->actingAs($agent)
        ->patch(route('tickets.status', $ticket), ['status' => TicketStatus::InProgress->value])
        ->assertSessionHasErrors('status');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Closed)
        ->and($ticket->activities()->count())->toBe(0);
});

it('forbids customers from viewing or editing another customers ticket', function () {
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $otherCustomer->id]);

    $this->actingAs($customer)->get(route('tickets.show', $ticket))->assertForbidden();
    $this->actingAs($customer)->get(route('tickets.edit', $ticket))->assertForbidden();
});
