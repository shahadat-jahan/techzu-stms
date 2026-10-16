<?php

namespace App\Listeners;

use App\Events\TicketSlaApproaching;
use App\Notifications\SlaApproachingNotification as SlaApproachingNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SlaApproachingListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TicketSlaApproaching $event): void
    {
        $event->ticket->agent?->notify(new SlaApproachingNotificationMail($event->ticket));
    }
}
