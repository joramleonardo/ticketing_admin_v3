<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketAttendedNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $refNo,
        public string $clientName,
        public string $staffName,
        public string $trackUrl,
        public ?string $problem = null,
        public ?string $ticketType = null,
    ) {}

    public function build(): self
    {
        return $this->subject("[Ticket #{$this->refNo}] Your Ticket is Now Being Attended")
            ->markdown('email.tickets.ticket_attended');
    }
}
