<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Redirect;

class AttachmentController extends Controller
{
    public function __construct(private readonly TicketService $ticketService)
    {
    }

    public function store(StoreAttachmentRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('reply', $ticket);

        $this->ticketService->addAttachments($ticket, $request->file('files'), $request->user());

        return Redirect::route('tickets.show', $ticket)
            ->with('success', 'Attachments uploaded.');
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $this->authorize('delete', $attachment);

        $ticket = $attachment->ticket();

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return Redirect::route('tickets.show', $ticket)
            ->with('success', 'Attachment removed.');
    }
}
