<?php

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Services\TicketService;

it('writes a created activity with a human readable message', function () {
    $customer = User::factory()->create();
    $service = app(TicketService::class);

    $ticket = $service->create([
        'subject' => 'Slow billing portal',
        'description' => 'The billing portal takes minutes to load.',
        'priority' => Priority::Medium->value,
        'category_id' => Category::factory()->create()->id,
    ], $customer);

    $activity = TicketActivity::sole();

    expect($activity->ticket_id)->toBe($ticket->id)
        ->and($activity->actor_id)->toBe($customer->id)
        ->and($activity->action)->toBe('created')
        ->and($activity->meta['message'])->toContain($customer->name);
});

it('records old and new status plus the note for status changes', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create();

    app(TicketService::class)->changeStatus($ticket, TicketStatus::InProgress, $agent, 'Picked up the ticket');

    $activity = TicketActivity::where('action', 'status_changed')->sole();

    expect($activity->meta['old_status'])->toBe('open')
        ->and($activity->meta['new_status'])->toBe('in_progress')
        ->and($activity->meta['note'])->toBe('Picked up the ticket')
        ->and($activity->meta['message'])->toContain('Open', 'In Progress', $agent->name);
});

it('records distinct activities for resolved, closed, and reopened actions', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create();
    $service = app(TicketService::class);

    $service->resolve($ticket, $agent);
    $service->close($ticket->refresh(), $agent);
    $service->reopen($ticket->refresh(), $agent);

    expect(TicketActivity::orderBy('id')->pluck('action')->all())->toBe(['resolved', 'closed', 'reopened']);
});

it('logs an updated activity when ticket details change', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create();
    $newCategory = Category::factory()->create();

    app(TicketService::class)->update($ticket, [
        'subject' => 'Renamed subject',
        'category_id' => $newCategory->id,
    ], $agent);

    $activity = TicketActivity::where('action', 'updated')->sole();

    expect($activity->meta['changed_fields'])->toEqualCanonicalizing(['subject', 'category_id'])
        ->and($activity->meta['message'])->toContain($agent->name, 'Subject', 'Category');
});

it('logs nothing when an update makes no changes', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create();

    app(TicketService::class)->update($ticket, [
        'subject' => $ticket->subject,
        'description' => $ticket->description,
    ], $agent);

    expect(TicketActivity::count())->toBe(0);
});

it('logs separate activities when details and priority change together', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->priority(Priority::Medium)->create();

    app(TicketService::class)->update($ticket, [
        'subject' => 'Both changed',
        'priority' => Priority::High->value,
    ], $agent);

    expect(TicketActivity::orderBy('id')->pluck('action')->all())->toBe(['priority_changed', 'updated']);
});

it('renders the activity timeline in chronological order on the ticket page', function () {
    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);
    $firstActor = User::factory()->agent()->create();
    $secondActor = User::factory()->agent()->create();
    $firstTime = now()->subMinutes(10);
    $secondTime = now()->subMinutes(5);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'actor_id' => $firstActor->id,
        'action' => 'created',
        'meta' => ['message' => 'First activity entry'],
        'created_at' => $firstTime,
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'actor_id' => $secondActor->id,
        'action' => 'status_changed',
        'meta' => ['message' => 'Second activity entry'],
        'created_at' => $secondTime,
    ]);

    $this->actingAs($customer)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSeeInOrder(['First activity entry', 'Second activity entry'])
        ->assertSee('by '.$firstActor->name)
        ->assertSee('by '.$secondActor->name)
        ->assertSee($firstTime->format('M j, Y H:i:s'))
        ->assertSee($secondTime->format('M j, Y H:i:s'));
});
