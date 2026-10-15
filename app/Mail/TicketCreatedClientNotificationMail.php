<?php

namespace App\Mail;

use App\Mail\Concerns\ThreadsTicketEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class TicketCreatedClientNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, ThreadsTicketEmail;

    public function __construct(
        public string $refNo,
        public string $clientName,
        public string $trackUrl,
        public ?string $problem = null,
        public ?string $ticketType = null,
        public ?string $dateRequested = null,
    ) {}

    public function headers(): Headers
    {
        return $this->threadHeaders(isRoot: true);
    }

    public function build(): self
    {
        return $this->subject($this->buildThreadSubject($this->ticketType, $this->problem, isReply: false))
            ->markdown('email.tickets.ticket_created');
    }
}
