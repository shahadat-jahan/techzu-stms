<?php

namespace App\Notifications;

use App\Models\TicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgentRepliedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TicketReply $reply,
    ) {}

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Agent Reply to Ticket: {$this->reply->ticket->ticket_number}")
            ->line('An agent has replied to your ticket.')
            ->line("Ticket: {$this->reply->ticket->ticket_number}")
            ->line("Subject: {$this->reply->ticket->subject}")
            ->line("Reply: {$this->reply->content}")
            ->action('View Ticket', url("/tickets/{$this->reply->ticket->id}"));
    }

    public function toArray($notifiable)
    {
        return [
            'ticket_id' => $this->reply->ticket->id,
            'ticket_number' => $this->reply->ticket->ticket_number,
            'subject' => $this->reply->ticket->subject,
            'reply_content' => $this->reply->content,
            'action_url' => url("/tickets/{$this->reply->ticket->id}"),
        ];
    }
}
