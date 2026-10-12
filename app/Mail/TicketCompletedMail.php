<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $refNo;

    public string $clientName;

    public ?string $problem;

    public ?string $employeeCode;

    public ?string $rateUrl;

    public function __construct(
        string $refNo,
        string $clientName,
        ?string $problem = null,
        ?string $employeeCode = null,
        ?string $rateUrl = null,
    ) {
        $this->refNo = $refNo;
        $this->clientName = $clientName;
        $this->problem = $problem;
        $this->employeeCode = $employeeCode;
        $this->rateUrl = $rateUrl;
    }

    public function build()
    {
        return $this->subject('Ticket Completed')
            ->markdown('email.tickets.completed');
    }
}
