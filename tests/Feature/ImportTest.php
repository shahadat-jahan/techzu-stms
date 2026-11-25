<?php

use App\Models\Import;
use App\Models\ImportError;
use App\Models\User;

it('failed csv download escapes formula prefixes and handles special characters in description', function () {
    $admin = User::factory()->admin()->create();
    $import = Import::factory()->create(['user_id' => $admin->id, 'failed_rows' => 1]);
    ImportError::create([
        'import_id' => $import->id,
        'row_number' => 2,
        'raw_data' => [
            'external_ref' => '=CMD|calc.exe',
            'subject' => 'Issue, with comma',
            'description' => "Line 1\nLine 2 with \"quotes\"",
        ],
        'error_message' => 'Invalid data',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.imports.failed-csv', $import));

    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain("'=CMD|calc.exe");
});
