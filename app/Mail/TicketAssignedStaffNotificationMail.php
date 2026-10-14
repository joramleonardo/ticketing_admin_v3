<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketAssignedStaffNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{refNo: string, clientName: string, division: ?string, problem: ?string, ticketType: ?string, dateRequested: ?string}>  $tickets
     */
    public function __construct(
        public string $staffName,
        public string $assignedBy,
        public array $tickets,
        public string $viewUrl,
    ) {}

    public function build(): self
    {
        $count = count($this->tickets);
        if ($count === 1) {
            $ref = $this->tickets[0]['refNo'] ?? '';
            $subject = "[Ticket #{$ref}] Assigned to You by {$this->assignedBy}";
        } else {
            $subject = "[Tickets Assigned] {$count} Tickets Assigned to You by {$this->assignedBy}";
        }

        return $this->subject($subject)
            ->markdown('email.tickets.ticket_assigned_staff');
    }
}
