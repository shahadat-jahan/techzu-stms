<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\TicketImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTicketImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800; // 30 minutes (retry_after must be higher)

    public function __construct(
        private Import $import
    ) {}

    public function handle(TicketImportService $ticketImporter): void
    {
        $this->import->update(['status' => 'processing', 'started_at' => now()]);

        // Service sets the final status (completed / completed_with_errors / failed)
        $ticketImporter->process($this->import);
    }

    public function failed(\Throwable $exception): void
    {
        $this->import->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
