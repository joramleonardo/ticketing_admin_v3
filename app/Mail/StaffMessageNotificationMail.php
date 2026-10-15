<?php

namespace App\Mail;

use App\Mail\Concerns\ThreadsTicketEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class StaffMessageNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, ThreadsTicketEmail;

    public function __construct(
        public string $refNo,
        public string $clientName,
        public string $staffName,
        public string $messageText,
        public string $trackUrl,
        public ?string $problem = null,
    ) {}

    public function headers(): Headers
    {
        return $this->threadHeaders(isRoot: false);
    }

    public function build(): self
    {
        return $this->subject($this->buildThreadSubject(null, $this->problem, isReply: true))
            ->markdown('email.tickets.staff_message');
    }
}
