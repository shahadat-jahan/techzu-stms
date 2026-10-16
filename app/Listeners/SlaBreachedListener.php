<?php

namespace App\Listeners;

use App\Events\TicketSlaBreached;
use App\Models\User;
use App\Notifications\SlaBreachedNotification as SlaBreachedNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SlaBreachedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TicketSlaBreached $event): void
    {
        $event->ticket->agent?->notify(new SlaBreachedNotificationMail($event->ticket));
        // Notify admins as well
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new SlaBreachedNotificationMail($event->ticket));
        }
    }
}
