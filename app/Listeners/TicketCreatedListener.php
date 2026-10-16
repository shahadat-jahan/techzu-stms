<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Notifications\TicketCreatedNotification as TicketCreatedNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TicketCreatedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TicketCreated $event): void
    {
        $event->ticket->customer->notify(new TicketCreatedNotificationMail($event->ticket));
        $event->ticket->agent?->notify(new TicketCreatedNotificationMail($event->ticket));
    }
}
