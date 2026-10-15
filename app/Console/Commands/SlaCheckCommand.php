<?php

namespace App\Console\Commands;

use App\Events\TicketSlaApproaching;
use App\Events\TicketSlaBreached;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SlaCheckCommand extends Command
{
    protected $signature = 'sla:check';

    protected $description = 'Check SLA statuses and dispatch escalation events';

    public function handle(): int
    {
        $this->info('Starting SLA check...');

        try {
            $this->notifyApproachingTickets();
            $this->escalateBreachedTickets();

            $this->info('SLA check completed successfully.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("SLA check failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function notifyApproachingTickets(): void
    {
        Ticket::slaApproaching()
            ->whereNull('warning_notified_at')
            ->chunkById(100, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    $this->notifyApproaching($ticket);
                }
            });
    }

    private function escalateBreachedTickets(): void
    {
        Ticket::slaBreached()
            ->whereNull('escalated_at')
            ->chunkById(100, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    $this->escalate($ticket);
                }
            });
    }

    private function notifyApproaching(Ticket $ticket): void
    {
        try {
            DB::transaction(function () use ($ticket): void {
                $lockedTicket = Ticket::lockForUpdate()->find($ticket->id);

                if (! $lockedTicket instanceof Ticket || $lockedTicket->warning_notified_at !== null) {
                    return;
                }

                $lockedTicket->warning_notified_at = now();
                $lockedTicket->save();

                TicketSlaApproaching::dispatch($lockedTicket);
            });
        } catch (\Exception $e) {
            $this->error("Failed to notify approaching SLA for ticket {$ticket->id}: {$e->getMessage()}");
        }
    }

    private function escalate(Ticket $ticket): void
    {
        try {
            DB::transaction(function () use ($ticket): void {
                $lockedTicket = Ticket::lockForUpdate()->find($ticket->id);

                if (! $lockedTicket instanceof Ticket || $lockedTicket->escalated_at !== null) {
                    return;
                }

                $lockedTicket->escalated_at = now();
                $lockedTicket->save();

                $lockedTicket->activities()->create([
                    'actor_id' => null,
                    'action' => 'escalated',
                    'meta' => ['message' => 'SLA deadline breached; ticket escalated.'],
                ]);

                TicketSlaBreached::dispatch($lockedTicket);
            });
        } catch (\Exception $e) {
            $this->error("Failed to escalate ticket {$ticket->id}: {$e->getMessage()}");
        }
    }
}
