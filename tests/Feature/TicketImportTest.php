<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportError;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketImportService;
use App\Services\TicketNumberGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->customer()->create(['email' => 'customer@example.com']);
    $this->agent = User::factory()->availableAgent()->create(['email' => 'agent@example.com']);

    Category::factory()->create(['name' => 'Billing']);
    Category::factory()->create(['name' => 'Technical']);

    SlaPolicy::create(['priority' => Priority::Low, 'resolution_minutes' => 2880, 'warning_minutes' => 2304, 'is_active' => true]);
    SlaPolicy::create(['priority' => Priority::Medium, 'resolution_minutes' => 1440, 'warning_minutes' => 1152, 'is_active' => true]);
    SlaPolicy::create(['priority' => Priority::High, 'resolution_minutes' => 480, 'warning_minutes' => 384, 'is_active' => true]);
    SlaPolicy::create(['priority' => Priority::Critical, 'resolution_minutes' => 120, 'warning_minutes' => 96, 'is_active' => true]);

    $this->service = app(TicketImportService::class);
});

function makeCsv(array $rows): string
{
    $lines = [];
    $lines[] = implode(',', ['external_ref', 'subject', 'description', 'priority', 'category', 'status', 'customer_email', 'assigned_agent_email', 'created_at']);

    foreach ($rows as $row) {
        $lines[] = implode(',', array_map(fn ($v) => $v === null ? '' : $v, $row));
    }

    return implode("\n", $lines);
}

function writeCsv(string $path, string $csv): void
{
    $fullPath = storage_path("app/{$path}");
    File::ensureDirectoryExists(dirname($fullPath));
    file_put_contents($fullPath, $csv);
}

function countCsvDataRows(string $csv): int
{
    return max(0, count(explode("\n", trim($csv))) - 1); // subtract header
}

it('imports all valid rows from a CSV file', function () {
    $csv = makeCsv([
        ['EXT-001', 'Billing issue', 'Cannot pay invoice', 'medium', 'Billing', 'open', 'customer@example.com', 'agent@example.com', null],
        ['EXT-002', 'Server down', 'Production server is down', 'critical', 'Technical', 'open', 'customer@example.com', null, null],
    ]);

    $path = 'imports/test-valid.csv';
    writeCsv($path, $csv);

    $import = Import::factory()->create([
        'path' => $path,
        'user_id' => $this->admin->id,
        'total_rows' => countCsvDataRows($csv),
    ]);

    $this->service->process($import);

    $import->refresh();
    expect($import->status)->toBe('completed')
        ->and($import->total_rows)->toBe(2)
        ->and($import->processed_rows)->toBe(2)
        ->and($import->imported_rows)->toBe(2)
        ->and($import->failed_rows)->toBe(0);

    expect(Ticket::where('external_ref', 'EXT-001')->exists())->toBeTrue();
    expect(Ticket::where('external_ref', 'EXT-002')->exists())->toBeTrue();

    // Uploaded file should be deleted after processing
    expect(File::exists(storage_path("app/{$path}")))->toBeFalse();
});

it('reports invalid rows and continues importing valid ones', function () {
    $csv = makeCsv([
        ['EXT-001', 'Valid ticket', 'Description', 'medium', 'Billing', 'open', 'customer@example.com', 'agent@example.com', null],
        ['', 'Missing ref', 'Description', 'medium', 'Billing', 'open', 'customer@example.com', null, null],
        ['EXT-003', 'Bad priority', 'Description', 'urgent', 'Billing', 'open', 'customer@example.com', null, null],
        ['EXT-004', 'Unknown category', 'Description', 'medium', 'Nonexistent', 'open', 'customer@example.com', null, null],
        ['EXT-005', 'Unknown customer', 'Description', 'medium', 'Billing', 'open', 'unknown@example.com', null, null],
    ]);

    $path = 'imports/test-invalid.csv';
    writeCsv($path, $csv);

    $import = Import::factory()->create([
        'path' => $path,
        'user_id' => $this->admin->id,
        'total_rows' => countCsvDataRows($csv),
    ]);

    $this->service->process($import);

    $import->refresh();
    expect($import->status)->toBe('completed_with_errors')
        ->and($import->total_rows)->toBe(5)
        ->and($import->processed_rows)->toBe(5)
        ->and($import->imported_rows)->toBe(1)
        ->and($import->failed_rows)->toBe(4);

    expect(Ticket::where('external_ref', 'EXT-001')->exists())->toBeTrue();
    expect(ImportError::where('import_id', $import->id)->count())->toBe(4);

    // File should still be deleted
    expect(File::exists(storage_path("app/{$path}")))->toBeFalse();
});

it('detects duplicate external_ref within the same file', function () {
    $csv = makeCsv([
        ['EXT-001', 'First ticket', 'Description', 'medium', 'Billing', 'open', 'customer@example.com', null, null],
        ['EXT-001', 'Duplicate ticket', 'Description', 'medium', 'Billing', 'open', 'customer@example.com', null, null],
    ]);

    $path = 'imports/test-dup-file.csv';
    writeCsv($path, $csv);

    $import = Import::factory()->create([
        'path' => $path,
        'user_id' => $this->admin->id,
        'total_rows' => countCsvDataRows($csv),
    ]);

    $this->service->process($import);

    $import->refresh();
    expect($import->imported_rows)->toBe(1)
        ->and($import->failed_rows)->toBe(1);

    expect(Ticket::where('external_ref', 'EXT-001')->count())->toBe(1);
    expect(ImportError::where('import_id', $import->id)->where('row_number', 3)->exists())->toBeTrue();
});

it('detects duplicate external_ref already in the database', function () {
    Ticket::factory()->create(['external_ref' => 'EXT-EXISTING', 'created_by' => $this->customer->id]);

    $csv = makeCsv([
        ['EXT-EXISTING', 'Duplicate ticket', 'Description', 'medium', 'Billing', 'open', 'customer@example.com', null, null],
    ]);

    $path = 'imports/test-dup-db.csv';
    writeCsv($path, $csv);

    $import = Import::factory()->create([
        'path' => $path,
        'user_id' => $this->admin->id,
        'total_rows' => countCsvDataRows($csv),
    ]);

    $this->service->process($import);

    $import->refresh();
    expect($import->imported_rows)->toBe(0)
        ->and($import->failed_rows)->toBe(1);

    expect(Ticket::where('external_ref', 'EXT-EXISTING')->count())->toBe(1);
});

it('re-uploading the same file imports 0 new rows (idempotent)', function () {
    $csv = makeCsv([
        ['EXT-001', 'Ticket one', 'Description', 'medium', 'Billing', 'open', 'customer@example.com', null, null],
    ]);

    $path = 'imports/test-reupload.csv';
    writeCsv($path, $csv);

    $import = Import::factory()->create([
        'path' => $path,
        'user_id' => $this->admin->id,
        'total_rows' => countCsvDataRows($csv),
    ]);

    $this->service->process($import);

    $import->refresh();
    expect($import->imported_rows)->toBe(1);

    // Re-process the same import (simulating re-upload)
    writeCsv($path, $csv);
    $this->service->process($import);

    $import->refresh();
    expect($import->imported_rows)->toBe(1)
        ->and($import->failed_rows)->toBe(1); // duplicate in DB

    expect(Ticket::where('external_ref', 'EXT-001')->count())->toBe(1);
});

it('calculates due_at from import start time, not CSV created_at', function () {
    $csv = makeCsv([
        ['EXT-001', 'Ticket with past date', 'Description', 'critical', 'Billing', 'open', 'customer@example.com', null, '2020-01-01 00:00:00'],
    ]);

    $path = 'imports/test-sla.csv';
    writeCsv($path, $csv);

    $import = Import::factory()->create([
        'path' => $path,
        'user_id' => $this->admin->id,
        'total_rows' => countCsvDataRows($csv),
    ]);

    $this->service->process($import);

    $ticket = Ticket::where('external_ref', 'EXT-001')->first();
    expect($ticket)->not->toBeNull();
    // created_at should come from CSV (2020-01-01)
    expect($ticket->created_at->format('Y-m-d'))->toBe('2020-01-01');
    // due_at should be calculated from import start time (now), not CSV created_at
    expect($ticket->due_at)->not->toBeNull();
    expect($ticket->due_at->year)->toBe(now()->year);
});

it('reserves consecutive ticket numbers without gaps', function () {
    $generator = app(TicketNumberGeneratorService::class);

    DB::transaction(function () use ($generator) {
        $numbers = $generator->reserve(2026, 3);
        expect($numbers)->toEqual([
            'TKT-2026-000001',
            'TKT-2026-000002',
            'TKT-2026-000003',
        ]);
    });

    DB::transaction(function () use ($generator) {
        $numbers = $generator->reserve(2026, 2);
        expect($numbers)->toEqual([
            'TKT-2026-000004',
            'TKT-2026-000005',
        ]);
    });
});
