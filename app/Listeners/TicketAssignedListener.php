<?php

namespace App\Listeners;

use App\Events\TicketAssigned;
use App\Notifications\TicketAssignedNotification as TicketAssignedNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TicketAssignedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TicketAssigned $event): void
    {
        $event->agent->notify(new TicketAssignedNotificationMail($event->ticket));
    }
}
