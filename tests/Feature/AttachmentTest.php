<?php

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads valid attachments and logs them on the ticket', function () {
    Storage::fake('local');

    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

    $this->actingAs($customer)
        ->post(route('tickets.attachments.store', $ticket), [
            'files' => [
                UploadedFile::fake()->create('x', 1, 'application/pdf'),
                UploadedFile::fake()->create('x', 2, 'image/png'),
            ],
        ])
        ->assertRedirect(route('tickets.show', $ticket));

    $attachment = Ticket::sole()->attachments->first();
    Storage::disk('local')->assertExists($attachment->path);

    expect(Ticket::sole()->attachments->count())->toBe(2)
        ->and($ticket->activities()->where('action', 'attachment_added')->count())->toBe(2);
});

it('validates file size, mime type, and count', function () {
    Storage::fake('local');

    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

    $response = $this->actingAs($customer)
        ->post(route('tickets.attachments.store', $ticket), [
            'files' => [
                UploadedFile::fake()->create('x', 6, 'application/pdf'),
            ],
        ]);

    $response->assertRedirect()->assertSessionHasErrors(['files.*']);

    $response = $this->actingAs($customer)
        ->post(route('tickets.attachments.store', $ticket), [
            'files' => [UploadedFile::fake()->create('x', 100, 'text/x-php')],
        ]);

    $response->assertRedirect()->assertSessionHasErrors(['files.*']);

    $response = $this->actingAs($customer)
        ->post(route('tickets.attachments.store', $ticket), [
            'files' => collect(range(1, 6))
                ->map(fn (int $index): UploadedFile => UploadedFile::fake()->create("file{$index}.pdf", 1, 'application/pdf'))
                ->toArray(),
        ]);

    $response->assertRedirect()->assertSessionHasErrors(['files']);
});

it('lets a customer download their own ticket attachment', function () {
    Storage::fake('local');

    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

    $attachment = $ticket->attachments()->create([
        'user_id' => $customer->id,
        'original_name' => 'Q1-report.pdf',
        'path' => 'attachments/'.uniqid('x_', true).'.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1234,
    ]);

    Storage::disk('local')->put($attachment->path, str_repeat('x', 1234));

    $response = $this->actingAs($customer)
        ->get(route('attachments.download', $attachment));

    $response->assertOk();
    $response->assertHeader('Content-Disposition');

    $this->assertTrue($response->headers->has('Content-Disposition'));
    $this->assertStringContainsString('Q1-report.pdf', $response->headers->get('Content-Disposition'));
});

it('blocks customers from downloading attachments attached to internal replies', function () {
    Storage::fake('local');

    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);
    $agent = User::factory()->agent()->create();

    $internalReply = TicketReply::factory()->internal()->create(['ticket_id' => $ticket->id, 'user_id' => $agent->id]);

    $attachment = $internalReply->attachments()->create([
        'user_id' => $agent->id,
        'original_name' => 'secret.pdf',
        'path' => 'attachments/'.uniqid('x_', true).'.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);

    Storage::disk('local')->put($attachment->path, str_repeat('x', 1024));

    $this->actingAs($customer)
        ->get(route('attachments.download', $attachment))
        ->assertForbidden();
});

it('blocks a customer from downloading attachments on another customer ticket', function () {
    Storage::fake('local');

    $customer = User::factory()->create();
    $other = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $other->id]);

    $attachment = $ticket->attachments()->create([
        'user_id' => $other->id,
        'original_name' => 'their.pdf',
        'path' => 'attachments/'.uniqid('x_', true).'.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);

    Storage::disk('local')->put($attachment->path, str_repeat('x', 1024));

    $this->actingAs($customer)
        ->get(route('attachments.download', $attachment))
        ->assertForbidden();
});

it('lets the owner delete an attachment and removes the file', function () {
    Storage::fake('local');

    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

    $attachment = $ticket->attachments()->create([
        'user_id' => $customer->id,
        'original_name' => 'delete-me.pdf',
        'path' => 'attachments/'.uniqid('x_', true).'.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);

    Storage::disk('local')->put($attachment->path, str_repeat('x', 1024));

    $this->actingAs($customer)
        ->delete(route('attachments.destroy', $attachment))
        ->assertRedirect(route('tickets.show', $ticket));

    Storage::disk('local')->assertMissing($attachment->path);
    expect($ticket->fresh()->attachments->count())->toBe(0);
});
