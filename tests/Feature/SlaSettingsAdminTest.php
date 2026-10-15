<?php

use App\Enums\Priority;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Services\BusinessHoursCalculatorService;
use App\Services\SlaService;
use App\Services\TicketService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

it('lets an admin view the sla policies', function (): void {
    SlaPolicy::create(['priority' => Priority::High, 'resolution_minutes' => 480, 'warning_minutes' => 384]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.sla-policies.index'))
        ->assertOk()
        ->assertSee('SLA Policies');
});

it('forbids non admins from viewing the sla policies', function (string $role): void {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('admin.sla-policies.index'))
        ->assertForbidden();
})->with(['agent', 'customer']);

it('lets an admin update an sla policy', function (): void {
    $policy = SlaPolicy::create(['priority' => Priority::High, 'resolution_minutes' => 480, 'warning_minutes' => 384]);

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.sla-policies.update', $policy), [
            'resolution_minutes' => 240,
            'warning_minutes' => 120,
            'is_active' => 1,
        ])
        ->assertRedirect();

    expect($policy->fresh())
        ->resolution_minutes->toBe(240)
        ->warning_minutes->toBe(120)
        ->is_active->toBeTrue();
});

it('rejects a warning window longer than the resolution window', function (): void {
    $policy = SlaPolicy::create(['priority' => Priority::High, 'resolution_minutes' => 480, 'warning_minutes' => 384]);

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.sla-policies.update', $policy), [
            'resolution_minutes' => 120,
            'warning_minutes' => 480,
            'is_active' => 1,
        ])
        ->assertSessionHasErrors('warning_minutes');

    expect($policy->fresh()->resolution_minutes)->toBe(480);
});

it('applies an updated policy to tickets created afterwards without code changes', function (): void {
    SlaPolicy::create(['priority' => Priority::High, 'resolution_minutes' => 480, 'warning_minutes' => 384]);

    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();
    $category = Category::factory()->create();

    $before = app(TicketService::class)->create([
        'subject' => 'Before policy change',
        'description' => 'Created under the original policy.',
        'priority' => Priority::High,
        'category_id' => $category->id,
    ], $customer);

    $this->actingAs($admin)->patch(route('admin.sla-policies.update', SlaPolicy::sole()), [
        'resolution_minutes' => 60,
        'warning_minutes' => 30,
        'is_active' => 1,
    ])->assertRedirect();

    $after = app(TicketService::class)->create([
        'subject' => 'After policy change',
        'description' => 'Created under the updated policy.',
        'priority' => Priority::High,
        'category_id' => $category->id,
    ], $customer);

    expect($after->due_at->lessThan($before->due_at))->toBeTrue()
        ->and($before->fresh()->due_at->equalTo($before->due_at))->toBeTrue();
});

it('lets an admin view the business hours', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.business-hours.index'))
        ->assertOk()
        ->assertSee('Business Hours')
        ->assertSee('Sunday');
});

it('forbids non admins from viewing the business hours', function (string $role): void {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('admin.business-hours.index'))
        ->assertForbidden();
})->with(['agent', 'customer']);

it('lets an admin update a business day and clears the calculator cache', function (): void {
    Cache::put(BusinessHoursCalculatorService::CACHE_KEY, ['stale' => true], 60);

    $sunday = BusinessHour::where('day_of_week', 0)->firstOrFail();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.business-hours.update', $sunday), [
            'is_open' => 1,
            'open_time' => '08:00',
            'close_time' => '20:00',
        ])
        ->assertRedirect();

    expect($sunday->fresh())
        ->open_time->toBe('08:00:00')
        ->close_time->toBe('20:00:00')
        ->and(Cache::has(BusinessHoursCalculatorService::CACHE_KEY))->toBeFalse();
});

it('rejects a closing time that is not after the opening time', function (): void {
    $monday = BusinessHour::where('day_of_week', 1)->firstOrFail();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.business-hours.update', $monday), [
            'is_open' => 1,
            'open_time' => '18:00',
            'close_time' => '09:00',
        ])
        ->assertSessionHasErrors('close_time');

    expect($monday->fresh()->open_time)->toBe('09:00:00');
});

it('lets an admin mark a day closed without supplying times', function (): void {
    $tuesday = BusinessHour::where('day_of_week', 2)->firstOrFail();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.business-hours.update', $tuesday), [
            'is_open' => 0,
        ])
        ->assertRedirect();

    expect($tuesday->fresh()->is_open)->toBeFalse();
});

it('uses the updated business hours for the next calculation', function (): void {
    SlaPolicy::create(['priority' => Priority::High, 'resolution_minutes' => 480, 'warning_minutes' => 384]);
    $sunday = BusinessHour::where('day_of_week', 0)->firstOrFail();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.business-hours.update', $sunday), [
            'is_open' => 1,
            'open_time' => '07:00',
            'close_time' => '21:00',
        ])
        ->assertRedirect();

    $service = app(SlaService::class);

    // Sunday 06:00 is before the new 07:00 opening, so counting starts at 07:00
    // and 480 business minutes end at 15:00 Dhaka, which is 09:00 UTC.
    $result = $service->calculateDue(Priority::High, CarbonImmutable::parse('2026-01-04 06:00:00', 'Asia/Dhaka'));

    expect($result->utc()->toDateTimeString())->toBe('2026-01-04 09:00:00');
});
