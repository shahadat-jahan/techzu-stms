<?php

namespace Tests\Unit;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('computes accurate conditional aggregate metrics for an admin', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->availableAgent()->create();

    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'priority' => Priority::Critical,
        'assigned_to' => null,
        'due_at' => now()->subHours(2),
        'sla_warning_at' => now()->subHours(4),
    ]);

    Ticket::factory()->create([
        'status' => TicketStatus::InProgress,
        'priority' => Priority::High,
        'assigned_to' => $agent->id,
        'due_at' => now()->addHour(),
        'sla_warning_at' => now()->subMinute(),
    ]);

    Ticket::factory()->resolved()->create([
        'assigned_to' => $agent->id,
    ]);

    $service = app(DashboardService::class);
    $stats = $service->getStats($admin);

    expect($stats['summary']['total_open'])->toBe(2)
        ->and($stats['summary']['unassigned'])->toBe(1)
        ->and($stats['summary']['critical'])->toBe(1)
        ->and($stats['summary']['breached'])->toBe(1)
        ->and($stats['summary']['approaching'])->toBe(1)
        ->and($stats['summary']['resolved'])->toBe(1);

    expect($stats['by_priority'][Priority::Critical->value])->toBe(1)
        ->and($stats['by_priority'][Priority::High->value])->toBe(1);

    $agentWorkload = collect($stats['by_agent'])->firstWhere('id', $agent->id);
    expect($agentWorkload['active_tickets_count'])->toBe(1);
});

it('scopes dashboard metrics strictly for customer', function () {
    $customer = User::factory()->customer()->create();
    $other = User::factory()->customer()->create();

    Ticket::factory()->create(['created_by' => $customer->id, 'status' => TicketStatus::Open]);
    Ticket::factory()->create(['created_by' => $other->id, 'status' => TicketStatus::Open]);

    $service = app(DashboardService::class);
    $stats = $service->getStats($customer);

    expect($stats['summary']['total_open'])->toBe(1);
    expect($stats['by_agent'])->toBeEmpty();
});

it('caches dashboard metrics for 60 seconds', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->create(['status' => TicketStatus::Open]);

    $service = app(DashboardService::class);
    $first = $service->getStats($admin);

    Ticket::factory()->create(['status' => TicketStatus::Open]);
    $cached = $service->getStats($admin);

    expect($cached['summary']['total_open'])->toBe($first['summary']['total_open']);
});
