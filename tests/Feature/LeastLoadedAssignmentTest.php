<?php

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\LeastLoadedAssignmentService;

it('assignTicket correctly handles string timestamp from withMax without errors', function () {
    $category = Category::factory()->create();
    $agent = User::factory()->agent()->create(['is_available' => true, 'max_active_tickets' => 5]);
    $agent->categories()->attach($category);

    Ticket::factory()->create([
        'assigned_to' => $agent->id,
        'category_id' => $category->id,
        'status' => TicketStatus::Resolved,
        'last_assigned_at' => now()->subDay(),
    ]);

    $newTicket = Ticket::factory()->create([
        'assigned_to' => null,
        'category_id' => $category->id,
        'status' => TicketStatus::Open,
    ]);

    $service = app(LeastLoadedAssignmentService::class);
    $assignedAgent = $service->assignTicket($newTicket);

    expect($assignedAgent)->not->toBeNull();
    expect($assignedAgent->id)->toBe($agent->id);
});
