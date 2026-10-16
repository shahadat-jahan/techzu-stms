<?php

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('filters tickets by exact ticket number or prefix', function () {
    $admin = User::factory()->admin()->create();
    $target = Ticket::factory()->create(['ticket_number' => 'TKT-2026-000042', 'subject' => 'Target Ticket']);
    $other = Ticket::factory()->create(['ticket_number' => 'TKT-2026-000099', 'subject' => 'Other Ticket']);

    $response = $this->actingAs($admin)->get(route('tickets.index', ['number' => 'TKT-2026-000042']));
    $response->assertOk()
        ->assertSee($target->ticket_number)
        ->assertDontSee($other->ticket_number);

    $prefixResponse = $this->actingAs($admin)->get(route('tickets.index', ['number' => '000042']));
    $prefixResponse->assertOk()
        ->assertSee($target->ticket_number)
        ->assertDontSee($other->ticket_number);
});

it('filters tickets by keyword matching subject and description case-insensitively', function () {
    $admin = User::factory()->admin()->create();
    $target = Ticket::factory()->create(['subject' => 'PostgreSQL Database Connection Issue', 'description' => 'Normal text']);
    $other = Ticket::factory()->create(['subject' => 'Billing inquiry', 'description' => 'Something else']);

    $response = $this->actingAs($admin)->get(route('tickets.index', ['keyword' => 'postgresql']));
    $response->assertOk()
        ->assertSee($target->ticket_number)
        ->assertDontSee($other->ticket_number);

    $descResponse = $this->actingAs($admin)->get(route('tickets.index', ['keyword' => 'Something else']));
    $descResponse->assertOk()
        ->assertSee($other->ticket_number)
        ->assertDontSee($target->ticket_number);
});

it('filters tickets by status, priority, and category', function () {
    $admin = User::factory()->admin()->create();
    $cat1 = Category::factory()->create(['name' => 'Database']);
    $cat2 = Category::factory()->create(['name' => 'Billing']);

    $criticalDb = Ticket::factory()->create([
        'category_id' => $cat1->id,
        'priority' => Priority::Critical,
        'status' => TicketStatus::Open,
    ]);
    $lowBilling = Ticket::factory()->create([
        'category_id' => $cat2->id,
        'priority' => Priority::Low,
        'status' => TicketStatus::InProgress,
    ]);

    $response = $this->actingAs($admin)->get(route('tickets.index', [
        'category' => $cat1->id,
        'priority' => Priority::Critical->value,
        'status' => TicketStatus::Open->value,
    ]));

    $response->assertOk()
        ->assertSee($criticalDb->ticket_number)
        ->assertDontSee($lowBilling->ticket_number);
});

it('filters tickets by assigned agent and unassigned pool', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->availableAgent()->create();
    $assigned = Ticket::factory()->create(['assigned_to' => $agent->id]);
    $unassigned = Ticket::factory()->create(['assigned_to' => null]);

    $assignedRes = $this->actingAs($admin)->get(route('tickets.index', ['assigned_to' => $agent->id]));
    $assignedRes->assertOk()
        ->assertSee($assigned->ticket_number)
        ->assertDontSee($unassigned->ticket_number);

    $unassignedRes = $this->actingAs($admin)->get(route('tickets.index', ['assigned_to' => 'unassigned']));
    $unassignedRes->assertOk()
        ->assertSee($unassigned->ticket_number)
        ->assertDontSee($assigned->ticket_number);
});

it('filters tickets by customer when viewed by admin', function () {
    $admin = User::factory()->admin()->create();
    $customer1 = User::factory()->customer()->create();
    $customer2 = User::factory()->customer()->create();

    $t1 = Ticket::factory()->create(['created_by' => $customer1->id]);
    $t2 = Ticket::factory()->create(['created_by' => $customer2->id]);

    $response = $this->actingAs($admin)->get(route('tickets.index', ['customer_id' => $customer1->id]));
    $response->assertOk()
        ->assertSee($t1->ticket_number)
        ->assertDontSee($t2->ticket_number);
});

it('filters tickets by created date range', function () {
    $admin = User::factory()->admin()->create();
    $old = Ticket::factory()->create(['created_at' => now()->subDays(10)]);
    $recent = Ticket::factory()->create(['created_at' => now()]);

    $response = $this->actingAs($admin)->get(route('tickets.index', [
        'created_from' => now()->subDays(2)->toDateString(),
        'created_to' => now()->addDay()->toDateString(),
    ]));

    $response->assertOk()
        ->assertSee($recent->ticket_number)
        ->assertDontSee($old->ticket_number);
});

it('does not crash on invalid created_from date filter', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->create(['subject' => 'Valid Ticket']);

    $response = $this->actingAs($admin)->get(route('tickets.index', [
        'created_from' => 'not-a-date',
    ]));

    $response->assertOk()
        ->assertSee('Valid Ticket');
});

it('does not crash on invalid created_to date filter', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->create(['subject' => 'Valid Ticket']);

    $response = $this->actingAs($admin)->get(route('tickets.index', [
        'created_to' => 'invalid-date',
    ]));

    $response->assertOk()
        ->assertSee('Valid Ticket');
});

it('does not crash on both invalid date filters', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->create(['subject' => 'Valid Ticket']);

    $response = $this->actingAs($admin)->get(route('tickets.index', [
        'created_from' => 'garbage',
        'created_to' => 'also-garbage',
    ]));

    $response->assertOk()
        ->assertSee('Valid Ticket');
});

it('filters tickets by SLA status: breached, approaching, within', function () {
    $admin = User::factory()->admin()->create();
    $breached = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'due_at' => now()->subHour(),
        'sla_warning_at' => now()->subHours(2),
    ]);
    $approaching = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'due_at' => now()->addHour(),
        'sla_warning_at' => now()->subMinutes(10),
    ]);
    $within = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'due_at' => now()->addDays(2),
        'sla_warning_at' => now()->addDay(),
    ]);

    $breachedRes = $this->actingAs($admin)->get(route('tickets.index', ['sla_status' => 'breached']));
    $breachedRes->assertOk()
        ->assertSee($breached->ticket_number)
        ->assertDontSee($approaching->ticket_number)
        ->assertDontSee($within->ticket_number);

    $approachingRes = $this->actingAs($admin)->get(route('tickets.index', ['sla_status' => 'approaching']));
    $approachingRes->assertOk()
        ->assertSee($approaching->ticket_number)
        ->assertDontSee($breached->ticket_number)
        ->assertDontSee($within->ticket_number);

    $withinRes = $this->actingAs($admin)->get(route('tickets.index', ['sla_status' => 'within']));
    $withinRes->assertOk()
        ->assertSee($within->ticket_number)
        ->assertDontSee($breached->ticket_number)
        ->assertDontSee($approaching->ticket_number);
});

it('prevents customers from filtering tickets created by other customers', function () {
    $customer = User::factory()->customer()->create();
    $otherCustomer = User::factory()->customer()->create();

    $ownTicket = Ticket::factory()->create(['created_by' => $customer->id, 'subject' => 'My Secret Issue']);
    $otherTicket = Ticket::factory()->create(['created_by' => $otherCustomer->id, 'subject' => 'Other Secret Issue']);

    $response = $this->actingAs($customer)->get(route('tickets.index', [
        'customer_id' => $otherCustomer->id,
    ]));

    $response->assertOk()
        ->assertSee($ownTicket->ticket_number)
        ->assertDontSee($otherTicket->ticket_number);

    $keywordResponse = $this->actingAs($customer)->get(route('tickets.index', [
        'keyword' => 'Other Secret Issue',
    ]));

    $keywordResponse->assertOk()
        ->assertDontSee($otherTicket->ticket_number);
});

it('prevents agents from seeing other agents tickets when filtering by assigned_to', function () {
    $agent = User::factory()->availableAgent()->create();
    $otherAgent = User::factory()->availableAgent()->create();

    $myTicket = Ticket::factory()->create(['assigned_to' => $agent->id]);
    $otherTicket = Ticket::factory()->create(['assigned_to' => $otherAgent->id]);

    $response = $this->actingAs($agent)->get(route('tickets.index', [
        'assigned_to' => $otherAgent->id,
    ]));

    $response->assertOk()
        ->assertDontSee($myTicket->ticket_number)
        ->assertDontSee($otherTicket->ticket_number);
});

it('preserves query parameters during pagination', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->count(15)->create(['priority' => Priority::High]);

    $response = $this->actingAs($admin)->get(route('tickets.index', [
        'priority' => Priority::High->value,
    ]));

    $response->assertOk();
    $tickets = $response->viewData('tickets');
    expect($tickets->nextPageUrl())->toContain('priority=high');
});

it('executes a constant number of queries on ticket listing regardless of row count (N+1 guard)', function () {
    $admin = User::factory()->admin()->create();

    Ticket::factory()->count(3)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin)->get(route('tickets.index'))->assertOk();
    $smallCount = count(DB::getQueryLog());

    Ticket::factory()->count(20)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin)->get(route('tickets.index'))->assertOk();
    $largeCount = count(DB::getQueryLog());

    expect($largeCount)->toBe($smallCount);
});
