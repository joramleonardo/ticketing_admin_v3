<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StaffMessageNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $refNo,
        public string $clientName,
        public string $staffName,
        public string $messageText,
        public string $trackUrl,
        public ?string $problem = null,
    ) {}

    public function build(): self
    {
        return $this->subject("[Ticket #{$this->refNo}] New Message from IT Support")
            ->markdown('email.tickets.staff_message');
    }
}
