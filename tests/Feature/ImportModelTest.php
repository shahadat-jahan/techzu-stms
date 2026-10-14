<?php

use App\Models\Import;
use App\Models\ImportError;

it('links import errors to their import', function () {
    $import = Import::factory()->create();
    $error = ImportError::factory()->create(['import_id' => $import->id]);

    expect($import->errors->pluck('id'))->toContain($error->id)
        ->and($error->import->id)->toBe($import->id);
});

it('casts import counters and raw error data', function () {
    $import = Import::factory()->processing()->create([
        'total_rows' => 100,
        'processed_rows' => 25,
        'imported_rows' => 20,
        'failed_rows' => 5,
    ]);
    $error = ImportError::factory()->create(['import_id' => $import->id]);

    expect($import->total_rows)->toBe(100)
        ->and($import->processed_rows)->toBe(25)
        ->and($error->raw_data)->toBeArray();
});
