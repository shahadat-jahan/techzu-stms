<?php

namespace Tests\Unit;

use App\Enums\Priority;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Services\SlaService;
use App\Services\TicketService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    foreach ([0, 1, 2, 3, 4] as $day) {
        BusinessHour::create([
            'day_of_week' => $day,
            'is_open' => true,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
        ]);
    }

    foreach ([5, 6] as $day) {
        BusinessHour::create([
            'day_of_week' => $day,
            'is_open' => false,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
        ]);
    }
});

it('calculates due_at for each priority using business hours', function (): void {
    SlaPolicy::create(['priority' => Priority::Low, 'resolution_minutes' => 2880, 'warning_minutes' => 2304]);
    SlaPolicy::create(['priority' => Priority::Medium, 'resolution_minutes' => 1440, 'warning_minutes' => 1152]);
    SlaPolicy::create(['priority' => Priority::High, 'resolution_minutes' => 480, 'warning_minutes' => 384]);
    SlaPolicy::create(['priority' => Priority::Critical, 'resolution_minutes' => 120, 'warning_minutes' => 96]);

    $service = app(SlaService::class);
    $start = CarbonImmutable::parse('2026-01-01 09:00:00', 'Asia/Dhaka'); // Thursday

    expect(businessClock($service->calculateDue(Priority::Critical, $start)))->toBe('2026-01-01 11:00:00')
        ->and(businessClock($service->calculateDue(Priority::High, $start)))->toBe('2026-01-01 17:00:00')
        ->and(businessClock($service->calculateDue(Priority::Medium, $start)))->toBe('2026-01-05 15:00:00')
        ->and(businessClock($service->calculateDue(Priority::Low, $start)))->toBe('2026-01-08 12:00:00');
});

it('calculates warning_at at 80 percent of the resolution window', function (): void {
    SlaPolicy::create(['priority' => Priority::Medium, 'resolution_minutes' => 1440, 'warning_minutes' => 1152]);

    $service = app(SlaService::class);
    $start = CarbonImmutable::parse('2026-01-01 09:00:00', 'Asia/Dhaka');

    $dueAt = $service->calculateDue(Priority::Medium, $start);
    $warningAt = $service->calculateWarning(Priority::Medium, $start);

    expect(businessClock($warningAt))->toBe('2026-01-05 10:12:00')
        ->and($warningAt->lessThan($dueAt))->toBeTrue()
        ->and($warningAt->greaterThan($start))->toBeTrue();
});

it('falls back to config defaults when no policy exists', function (): void {
    $service = app(SlaService::class);
    $start = CarbonImmutable::parse('2026-01-01 09:00:00', 'Asia/Dhaka');

    // config/support.php: default_sla_minutes = 1440, warning_percent = 80.
    expect(businessClock($service->calculateDue(Priority::Medium, $start)))->toBe('2026-01-05 15:00:00')
        ->and(businessClock($service->calculateWarning(Priority::Medium, $start)))->toBe('2026-01-05 10:12:00');
});

it('ignores inactive policies', function (): void {
    SlaPolicy::create(['priority' => Priority::Medium, 'resolution_minutes' => 1440, 'warning_minutes' => 1152, 'is_active' => false]);

    $service = app(SlaService::class);
    $start = CarbonImmutable::parse('2026-01-01 09:00:00', 'Asia/Dhaka');

    expect(businessClock($service->calculateDue(Priority::Medium, $start)))->toBe('2026-01-05 15:00:00');
});

it('recalculates sla from the original creation time when priority changes', function (): void {
    SlaPolicy::create(['priority' => Priority::Low, 'resolution_minutes' => 2880, 'warning_minutes' => 2304]);
    SlaPolicy::create(['priority' => Priority::Critical, 'resolution_minutes' => 120, 'warning_minutes' => 96]);

    $customer = User::factory()->create();
    $category = Category::factory()->create();
    $service = app(TicketService::class);

    $ticket = $service->create([
        'subject' => 'Printer down',
        'description' => 'The office printer is offline.',
        'priority' => Priority::Low,
        'category_id' => $category->id,
    ], $customer);

    $lowDueAt = $ticket->due_at;
    expect($lowDueAt)->not->toBeNull();

    $service->changePriority($ticket->fresh(), Priority::Critical, $customer);

    $ticket->refresh();

    expect($ticket->priority)->toBe(Priority::Critical)
        ->and($ticket->due_at->notEqualTo($lowDueAt))->toBeTrue()
        ->and($ticket->due_at->lessThan($lowDueAt))->toBeTrue()
        ->and($ticket->sla_warning_at->lessThan($ticket->due_at))->toBeTrue()
        ->and($ticket->activities()->where('action', 'sla_recalculated')->count())->toBe(1);
});

it('does not log an sla recalculation when the priority is unchanged', function (): void {
    SlaPolicy::create(['priority' => Priority::Low, 'resolution_minutes' => 2880, 'warning_minutes' => 2304]);

    $customer = User::factory()->create();
    $category = Category::factory()->create();
    $service = app(TicketService::class);

    $ticket = $service->create([
        'subject' => 'Printer down',
        'description' => 'The office printer is offline.',
        'priority' => Priority::Low,
        'category_id' => $category->id,
    ], $customer);

    $service->changePriority($ticket->fresh(), Priority::Low, $customer);

    expect($ticket->fresh()->activities()->where('action', 'sla_recalculated')->count())->toBe(0);
});

it('does not log an sla recalculation when the calculated deadlines are unchanged', function (): void {
    SlaPolicy::create(['priority' => Priority::Low, 'resolution_minutes' => 1440, 'warning_minutes' => 1152]);
    SlaPolicy::create(['priority' => Priority::Medium, 'resolution_minutes' => 1440, 'warning_minutes' => 1152]);

    $customer = User::factory()->create();
    $category = Category::factory()->create();
    $service = app(TicketService::class);

    $ticket = $service->create([
        'subject' => 'Shared SLA window',
        'description' => 'Both priorities resolve to the same deadlines.',
        'priority' => Priority::Low,
        'category_id' => $category->id,
    ], $customer);

    $dueBefore = $ticket->due_at->toDateTimeString();
    $warningBefore = $ticket->sla_warning_at->toDateTimeString();

    $service->changePriority($ticket->fresh(), Priority::Medium, $customer);

    $ticket->refresh();

    expect($ticket->due_at->toDateTimeString())->toBe($dueBefore)
        ->and($ticket->sla_warning_at->toDateTimeString())->toBe($warningBefore)
        ->and($ticket->activities()->where('action', 'sla_recalculated')->count())->toBe(0);
});

it('sets sla timestamps on ticket creation', function (): void {
    SlaPolicy::create(['priority' => Priority::High, 'resolution_minutes' => 480, 'warning_minutes' => 384]);

    $customer = User::factory()->create();
    $category = Category::factory()->create();

    $ticket = app(TicketService::class)->create([
        'subject' => 'VPN down',
        'description' => 'Cannot connect to the VPN.',
        'priority' => Priority::High,
        'category_id' => $category->id,
    ], $customer);

    expect($ticket->due_at)->not->toBeNull()
        ->and($ticket->sla_warning_at)->not->toBeNull()
        ->and($ticket->sla_warning_at->lessThan($ticket->due_at))->toBeTrue()
        ->and($ticket->due_at->timezoneName)->toBe('UTC');
});
