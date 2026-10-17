<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreachedNotification extends Notification
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
            ->subject("SLA Breached: {$this->ticket->ticket_number}")
            ->line('A ticket has breached its SLA deadline.')
            ->line("Ticket: {$this->ticket->ticket_number}")
            ->line("Subject: {$this->ticket->subject}")
            ->line("Due at: {$this->ticket->due_at}")
            ->action('View Ticket', url("/tickets/{$this->ticket->id}"));
    }

    public function toArray($notifiable)
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'subject' => $this->ticket->subject,
            'due_at' => $this->ticket->due_at,
            'action_url' => url("/tickets/{$this->ticket->id}"),
        ];
    }
}
