<?php

namespace App\Listeners;

use App\Events\TicketClosed;
use App\Notifications\TicketClosedNotification as TicketClosedNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TicketClosedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TicketClosed $event): void
    {
        $event->ticket->customer->notify(new TicketClosedNotificationMail($event->ticket));
    }
}
