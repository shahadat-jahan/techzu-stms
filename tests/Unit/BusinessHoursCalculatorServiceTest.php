<?php

namespace Tests\Unit;

use App\Models\BusinessHour;
use App\Services\BusinessHoursCalculatorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
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

/**
 * Express the expectation in business wall-clock time, since that is what the
 * SLA policy minutes actually mean.
 */
it('consumes the rest of the current business day then spills into the next one', function (): void {
    $calculator = app(BusinessHoursCalculatorService::class);

    // Thursday 2026-01-01 17:00 + 120min: 60min left on Thursday, 60min on Sunday.
    $result = $calculator->addBusinessMinutes(
        CarbonImmutable::parse('2026-01-01 17:00:00', 'Asia/Dhaka'),
        120,
    );

    expect(businessClock($result))->toBe('2026-01-04 10:00:00');
});

it('skips the friday and saturday weekend', function (): void {
    $calculator = app(BusinessHoursCalculatorService::class);

    // Thursday 16:00 + 180min: 120min on Thursday, 60min on Sunday.
    $result = $calculator->addBusinessMinutes(
        CarbonImmutable::parse('2026-01-01 16:00:00', 'Asia/Dhaka'),
        180,
    );

    expect(businessClock($result))->toBe('2026-01-04 10:00:00');
});

it('handles an exact boundary at opening time', function (): void {
    $calculator = app(BusinessHoursCalculatorService::class);

    $result = $calculator->addBusinessMinutes(
        CarbonImmutable::parse('2026-01-01 09:00:00', 'Asia/Dhaka'),
        60,
    );

    expect(businessClock($result))->toBe('2026-01-01 10:00:00');
});

it('handles an exact boundary at closing time', function (): void {
    $calculator = app(BusinessHoursCalculatorService::class);

    $result = $calculator->addBusinessMinutes(
        CarbonImmutable::parse('2026-01-01 17:00:00', 'Asia/Dhaka'),
        60,
    );

    expect(businessClock($result))->toBe('2026-01-01 18:00:00');
});

it('clamps a start before opening time to the opening time', function (): void {
    $calculator = app(BusinessHoursCalculatorService::class);

    $result = $calculator->addBusinessMinutes(
        CarbonImmutable::parse('2026-01-01 08:00:00', 'Asia/Dhaka'),
        60,
    );

    expect(businessClock($result))->toBe('2026-01-01 10:00:00');
});

it('rolls a start after closing time to the next business day', function (): void {
    $calculator = app(BusinessHoursCalculatorService::class);

    $result = $calculator->addBusinessMinutes(
        CarbonImmutable::parse('2026-01-01 18:30:00', 'Asia/Dhaka'),
        60,
    );

    expect(businessClock($result))->toBe('2026-01-04 10:00:00');
});

it('spans multiple business days', function (): void {
    $calculator = app(BusinessHoursCalculatorService::class);

    // Wednesday 2026-01-07 16:00 + 480min: 120min on Wednesday, 360min on Thursday.
    $result = $calculator->addBusinessMinutes(
        CarbonImmutable::parse('2026-01-07 16:00:00', 'Asia/Dhaka'),
        480,
    );

    expect(businessClock($result))->toBe('2026-01-08 15:00:00');
});

it('rolls a start on a closed day to the next business day', function (): void {
    $calculator = app(BusinessHoursCalculatorService::class);

    // Friday 2026-01-02 is closed, so counting starts Sunday 09:00.
    $result = $calculator->addBusinessMinutes(
        CarbonImmutable::parse('2026-01-02 10:00:00', 'Asia/Dhaka'),
        60,
    );

    expect(businessClock($result))->toBe('2026-01-04 10:00:00');
});

it('returns the deadline in utc for storage', function (): void {
    $calculator = app(BusinessHoursCalculatorService::class);

    $result = $calculator->addBusinessMinutes(
        CarbonImmutable::parse('2026-01-01 09:00:00', 'Asia/Dhaka'),
        60,
    );

    expect($result->timezoneName)->toBe('UTC')
        ->and($result->utc()->toDateTimeString())->toBe('2026-01-01 04:00:00');
});

it('throws when no business hours are configured', function (): void {
    BusinessHour::query()->delete();

    $calculator = app(BusinessHoursCalculatorService::class);

    expect(fn (): CarbonImmutable => $calculator->addBusinessMinutes(
        CarbonImmutable::parse('2026-01-01 09:00:00', 'Asia/Dhaka'),
        60,
    ))->toThrow(RuntimeException::class);
});

it('throws when every configured business day is closed', function (): void {
    BusinessHour::query()->update(['is_open' => false]);

    $calculator = app(BusinessHoursCalculatorService::class);

    expect(fn (): CarbonImmutable => $calculator->addBusinessMinutes(
        CarbonImmutable::parse('2026-01-01 09:00:00', 'Asia/Dhaka'),
        60,
    ))->toThrow(RuntimeException::class, 'No business days are configured as open.');
});
