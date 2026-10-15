<?php

namespace App\Mail;

use App\Mail\Concerns\ThreadsTicketEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class TicketCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, ThreadsTicketEmail;

    public function __construct(
        public string $refNo,
        public string $clientName,
        public string $staffName,
        public string $rateUrl,
        public ?string $trackUrl = null,
        public ?string $problem = null,
        public ?string $actionsTaken = null,
        public ?string $ticketType = null,
        public ?string $dateCompleted = null,
        public ?string $employeeCode = null,
    ) {}

    public function headers(): Headers
    {
        return $this->threadHeaders(isRoot: false);
    }

    public function build(): self
    {
        return $this->subject($this->buildThreadSubject($this->ticketType, $this->problem, isReply: true))
            ->markdown('email.tickets.completed');
    }
}
