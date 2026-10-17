<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
    ) {}

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Ticket Assigned: {$this->ticket->ticket_number}")
            ->line('You have been assigned to a ticket.')
            ->line("Ticket: {$this->ticket->ticket_number}")
            ->line("Subject: {$this->ticket->subject}")
            ->action('View Ticket', url("/tickets/{$this->ticket->id}"));
    }

    public function toArray($notifiable)
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'subject' => $this->ticket->subject,
            'action_url' => url("/tickets/{$this->ticket->id}"),
        ];
    }
}
