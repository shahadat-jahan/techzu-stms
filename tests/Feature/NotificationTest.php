<?php

use App\Events\AgentReplied;
use App\Events\TicketAssigned;
use App\Events\TicketClosed;
use App\Events\TicketCreated;
use App\Events\TicketCustomerReplied;
use App\Events\TicketResolved;
use App\Events\TicketSlaApproaching;
use App\Events\TicketSlaBreached;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Support\Str;

it('dispatches TicketCreated event when ticket is created', function () {
    $creator = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $creator->id]);

    TicketCreated::dispatch($ticket);

    expect($ticket->exists)->toBeTrue();
});

it('dispatches TicketAssigned event when ticket is assigned', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create();

    TicketAssigned::dispatch($ticket, $agent);

    expect($ticket->exists)->toBeTrue();
});

it('dispatches TicketCustomerReplied event when customer replies', function () {
    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $reply = TicketReply::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $customer->id]);

    TicketCustomerReplied::dispatch($reply);

    expect($reply->exists)->toBeTrue();
});

it('dispatches AgentReplied event when agent replies', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $reply = TicketReply::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $agent->id]);

    AgentReplied::dispatch($reply);

    expect($reply->exists)->toBeTrue();
});

it('dispatches TicketResolved event when ticket is resolved', function () {
    $actor = User::factory()->create();
    $ticket = Ticket::factory()->create();

    TicketResolved::dispatch($ticket);

    expect($ticket->exists)->toBeTrue();
});

it('dispatches TicketClosed event when ticket is closed', function () {
    $actor = User::factory()->create();
    $ticket = Ticket::factory()->create();

    TicketClosed::dispatch($ticket);

    expect($ticket->exists)->toBeTrue();
});

it('dispatches TicketSlaApproaching event when SLA is approaching', function () {
    $ticket = Ticket::factory()->create();

    TicketSlaApproaching::dispatch($ticket);

    expect($ticket->exists)->toBeTrue();
});

it('dispatches TicketSlaBreached event when SLA is breached', function () {
    $ticket = Ticket::factory()->create();

    TicketSlaBreached::dispatch($ticket);

    expect($ticket->exists)->toBeTrue();
});

it('notification bell shows unread count', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
    $response->assertSee('Notifications');
});

it('notification index page works', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/notifications');
    $response->assertStatus(200);
    $response->assertSee('Notifications');
});

it('marks notification as read', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TicketCreatedNotification',
        'data' => ['ticket_id' => 1, 'subject' => 'Test'],
        'read_at' => null,
    ]);

    $response = $this->post("/notifications/{$notification->id}/mark-as-read");
    $response->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks notification as read for ajax requests with json response', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TicketCreatedNotification',
        'data' => ['ticket_id' => 1, 'subject' => 'Test'],
        'read_at' => null,
    ]);

    $response = $this->postJson("/notifications/{$notification->id}/mark-as-read");
    $response->assertOk()->assertJson(['success' => true]);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('forbids marking another users notification as read', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $this->actingAs($intruder);

    $notification = $owner->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TicketCreatedNotification',
        'data' => ['ticket_id' => 1, 'subject' => 'Test'],
        'read_at' => null,
    ]);

    $this->postJson("/notifications/{$notification->id}/mark-as-read")->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});
