<?php

namespace App\Events;

use App\Models\TicketReply;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCustomerReplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TicketReply $reply,
    ) {}
}
