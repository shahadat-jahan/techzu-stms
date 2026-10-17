<?php

namespace App\Listeners;

use App\Events\AgentReplied;
use App\Notifications\AgentRepliedNotification as AgentRepliedNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AgentRepliedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(AgentReplied $event): void
    {
        $event->reply->ticket->customer->notify(new AgentRepliedNotificationMail($event->reply));
    }
}
