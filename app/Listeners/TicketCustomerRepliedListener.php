<?php

namespace App\Listeners;

use App\Events\TicketCustomerReplied;
use App\Notifications\TicketCustomerRepliedNotification as TicketCustomerRepliedNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TicketCustomerRepliedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TicketCustomerReplied $event): void
    {
        $event->reply->ticket->agent?->notify(new TicketCustomerRepliedNotificationMail($event->reply));
    }
}
