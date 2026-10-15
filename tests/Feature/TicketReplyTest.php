<?php

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;

it('creates a public reply for the customer and logs the activity', function () {
    $customer = User::factory()->create();
    $category = Category::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

    $this->actingAs($customer)
        ->post(route('tickets.replies.store', $ticket), [
            'body' => 'Can someone check my VPN settings?',
        ])
        ->assertRedirect(route('tickets.show', $ticket))
        ->assertSessionHas('success');

    $reply = TicketReply::sole();
    expect($reply->ticket_id)->toBe($ticket->id)
        ->and($reply->user_id)->toBe($customer->id)
        ->and($reply->is_internal)->toBeFalse()
        ->and($reply->body)->toBe('Can someone check my VPN settings?');

    expect($ticket->activities()->where('action', 'replied')->count())->toBe(1);
});

it('does not auto-transition a public reply on a non-waiting ticket', function () {
    $customer = User::factory()->create();
    $ticket = Ticket::factory()->status(TicketStatus::Open)->create(['created_by' => $customer->id]);

    $this->actingAs($customer)
        ->post(route('tickets.replies.store', $ticket), ['body' => 'Public follow-up.']);

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Open);
});

it('updates the ticket when the customer sends an internal note', function () {
    $customer = User::factory()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->status(TicketStatus::WaitingForCustomer)->create(['created_by' => $customer->id]);

    $this->actingAs($agent)
        ->post(route('tickets.replies.store', $ticket), [
            'body' => 'Internal note with request details.',
            'is_internal' => '1',
        ])
        ->assertRedirect(route('tickets.show', $ticket));

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::WaitingForCustomer)
        ->and($ticket->activities()->where('action', 'internal_note_added')->count())->toBe(1);
});


it('transitions a waiting-for-customer ticket to in_progress on customer reply', function () {
    $customer = User::factory()->create();
    $ticket = Ticket::factory()->status(TicketStatus::WaitingForCustomer)->create(['created_by' => $customer->id]);

    $this->actingAs($customer)
        ->post(route('tickets.replies.store', $ticket), ['body' => 'Here is the info you asked for.']);

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::InProgress)
        ->and($ticket->activities()->where('action', 'status_changed')->count())->toBe(1);
});

it('does not transition a non-waiting ticket when an agent replies', function () {
    $customer = User::factory()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->status(TicketStatus::WaitingForCustomer)->create(['created_by' => $customer->id]);

    $this->actingAs($agent)
        ->post(route('tickets.replies.store', $ticket), ['body' => 'Waiting on the customer.']);

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::WaitingForCustomer);
});

it('disallows an internal note from a customer', function () {
    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

    $this->actingAs($customer)
        ->from(route('tickets.show', $ticket))
        ->post(route('tickets.replies.store', $ticket), [
            'body' => 'Fake internal note',
            'is_internal' => '1',
        ])
        ->assertRedirect(route('tickets.show', $ticket))
        ->assertSessionHasErrors('is_internal');
});

it('records an internal note as a separate activity for staff only', function () {
    $customer = User::factory()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

    $this->actingAs($agent)
        ->post(route('tickets.replies.store', $ticket), [
            'body' => 'Customer email says the portal is down.',
            'is_internal' => '1',
        ])
        ->assertRedirect(route('tickets.show', $ticket));

    $reply = TicketReply::sole();
    expect($reply->is_internal)->toBeTrue()
        ->and($ticket->activities()->where('action', 'internal_note_added')->count())->toBe(1);
});

it('blocks a customer from replying on another customer ticket', function () {
    $customer = User::factory()->create();
    $other = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $other->id]);

    $this->actingAs($customer)
        ->post(route('tickets.replies.store', $ticket), ['body' => 'Hello!'])
        ->assertForbidden();
});

it('shows only non-internal replies to a customer', function () {
    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);
    $agent = User::factory()->agent()->create();

    TicketReply::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $customer->id, 'body' => 'Public reply']);
    TicketReply::factory()->internal()->create(['ticket_id' => $ticket->id, 'user_id' => $agent->id]);

    $this->actingAs($customer)
        ->get(route('tickets.show', $ticket))
        ->assertSee('Public reply')
        ->assertDontSee('Internal note');
});
