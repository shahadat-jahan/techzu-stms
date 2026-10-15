<?php

use App\Services\TicketNumberGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates sequential ticket numbers within a year', function () {
    $generator = app(TicketNumberGeneratorService::class);

    expect(DB::transaction(fn () => $generator->generate(2026)))->toBe('TKT-2026-000001')
        ->and(DB::transaction(fn () => $generator->generate(2026)))->toBe('TKT-2026-000002');
});

it('starts a fresh sequence on year rollover', function () {
    $generator = app(TicketNumberGeneratorService::class);

    DB::transaction(fn () => $generator->generate(2026));

    expect(DB::transaction(fn () => $generator->generate(2027)))->toBe('TKT-2027-000001')
        ->and(DB::transaction(fn () => $generator->generate(2026)))->toBe('TKT-2026-000002');
});

it('defaults to the current year', function () {
    $generator = app(TicketNumberGeneratorService::class);

    expect(DB::transaction(fn () => $generator->generate(now()->year)))->toBe(sprintf('TKT-%d-000001', now()->year));
});

it('produces unique numbers when generating in bulk', function () {
    $generator = app(TicketNumberGeneratorService::class);

    $numbers = collect(range(1, 25))->map(fn () => DB::transaction(fn () => $generator->generate(2026)));

    expect($numbers->unique())->toHaveCount(25)
        ->and($numbers->last())->toBe('TKT-2026-000025');
});

it('grows beyond six digits without losing the sequence', function () {
    DB::table('ticket_sequences')->insert(['year' => 2026, 'last_number' => 999999]);

    $generator = app(TicketNumberGeneratorService::class);

    expect(DB::transaction(fn () => $generator->generate(2026)))->toBe('TKT-2026-1000000');
});
