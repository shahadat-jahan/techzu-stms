<?php

use App\Enums\Priority;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Import;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketImportService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\File;

it('sends ticket created and assigned notifications after CSV import', function () {
    $customer = User::factory()->create([
        'role' => Role::Customer,
        'email' => 'customer@example.com',
    ]);
    $agent = User::factory()->create([
        'role' => Role::Agent,
        'email' => 'agent@example.com',
    ]);
    $category = Category::factory()->create(['name' => 'Technical Support']);

    SlaPolicy::create([
        'priority' => Priority::High,
        'resolution_minutes' => 480,
        'warning_minutes' => 384,
        'is_active' => true,
    ]);

    $csvContent = "external_ref,subject,description,priority,category,status,customer_email,assigned_agent_email,created_at\n"
        ."EXT-001,Test Subject,Test Description,high,Technical Support,open,customer@example.com,agent@example.com,\n";

    $filePath = 'private/imports/test.csv';
    File::ensureDirectoryExists(storage_path('app/private/imports'), 0755, true);
    file_put_contents(storage_path("app/{$filePath}"), $csvContent);

    $import = Import::create([
        'user_id' => $customer->id,
        'original_filename' => 'test.csv',
        'path' => $filePath,
        'status' => 'pending',
        'total_rows' => 1,
    ]);

    app(TicketImportService::class)->process($import);

    // Tickets should be imported
    expect(Ticket::count())->toBe(1);

    // Database notifications should have been created for customer and agent
    expect(DatabaseNotification::count())->toBeGreaterThan(0);
});
