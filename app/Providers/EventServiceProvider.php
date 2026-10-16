<?php

namespace App\Providers;

use App\Events\AgentReplied;
use App\Events\TicketAssigned;
use App\Events\TicketClosed;
use App\Events\TicketCreated;
use App\Events\TicketCustomerReplied;
use App\Events\TicketResolved;
use App\Events\TicketSlaApproaching;
use App\Events\TicketSlaBreached;
use App\Listeners\AgentRepliedListener;
use App\Listeners\SlaApproachingListener;
use App\Listeners\SlaBreachedListener;
use App\Listeners\TicketAssignedListener;
use App\Listeners\TicketClosedListener;
use App\Listeners\TicketCreatedListener;
use App\Listeners\TicketCustomerRepliedListener;
use App\Listeners\TicketResolvedListener;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        TicketCreated::class => [
            TicketCreatedListener::class,
        ],
        TicketAssigned::class => [
            TicketAssignedListener::class,
        ],
        TicketCustomerReplied::class => [
            TicketCustomerRepliedListener::class,
        ],
        AgentReplied::class => [
            AgentRepliedListener::class,
        ],
        TicketResolved::class => [
            TicketResolvedListener::class,
        ],
        TicketClosed::class => [
            TicketClosedListener::class,
        ],
        TicketSlaApproaching::class => [
            SlaApproachingListener::class,
        ],
        TicketSlaBreached::class => [
            SlaBreachedListener::class,
        ],
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
