<?php

namespace App\Listeners;

use App\Events\TicketResolved;
use App\Notifications\TicketResolvedNotification as TicketResolvedNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TicketResolvedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TicketResolved $event): void
    {
        $event->ticket->customer->notify(new TicketResolvedNotificationMail($event->ticket));
    }
}
