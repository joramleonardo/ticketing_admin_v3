<?php

use App\Mail\StaffMessageNotificationMail;
use App\Mail\TicketAssignedClientNotificationMail;
use App\Mail\TicketAttendedNotificationMail;
use App\Mail\TicketCompletedMail;
use App\Mail\TicketCreatedClientNotificationMail;
use App\Mail\TicketRatingReminderMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

it('generates matching threading headers and unified base subjects across all ticket emails', function (): void {
    $ref = '20261015-THRD1';
    $client = 'Juan Dela Cruz';
    $staff = 'Chester Francisco';
    $problem = 'Workstation network cable damaged';
    $trackUrl = "http://ticketing.stii.local/client?track={$ref}&open=1";
    $rateUrl = "http://ticketing.stii.local/client/rate/{$ref}";

    // 1. Root / Creation Mail
    $createdMail = new TicketCreatedClientNotificationMail(
        refNo: $ref,
        clientName: $client,
        trackUrl: $trackUrl,
        problem: $problem,
        ticketType: 'Technical Support',
        dateRequested: 'Oct 15, 2026 09:00 AM',
    );
    $createdMail->build();
    $createdHeaders = $createdMail->headers();

    // 2. Assigned Mail (Client)
    $assignedMail = new TicketAssignedClientNotificationMail(
        refNo: $ref,
        clientName: $client,
        staffName: $staff,
        trackUrl: $trackUrl,
        problem: $problem,
        ticketType: 'Technical Support',
    );
    $assignedMail->build();
    $assignedHeaders = $assignedMail->headers();

    // 3. Attended Mail
    $attendedMail = new TicketAttendedNotificationMail(
        refNo: $ref,
        clientName: $client,
        staffName: $staff,
        trackUrl: $trackUrl,
        problem: $problem,
        ticketType: 'Technical Support',
    );
    $attendedMail->build();
    $attendedHeaders = $attendedMail->headers();

    // 3. Staff Message Mail
    $messageMail = new StaffMessageNotificationMail(
        refNo: $ref,
        clientName: $client,
        staffName: $staff,
        messageText: 'I will be coming over in 10 minutes.',
        trackUrl: $trackUrl,
        problem: $problem,
    );
    $messageMail->build();
    $messageHeaders = $messageMail->headers();

    // 4. Completed Mail
    $completedMail = new TicketCompletedMail(
        refNo: $ref,
        clientName: $client,
        staffName: $staff,
        rateUrl: $rateUrl,
        trackUrl: $trackUrl,
        problem: $problem,
        actionsTaken: 'Replaced CAT6 patch cable and verified Gigabit connection.',
        ticketType: 'Technical Support',
        dateCompleted: 'Oct 15, 2026 09:30 AM',
    );
    $completedMail->build();
    $completedHeaders = $completedMail->headers();

    // 5. Rating Reminder Mail
    $reminderMail = new TicketRatingReminderMail(
        refNo: $ref,
        clientName: $client,
        rateUrl: $rateUrl,
        staffName: $staff,
        problem: $problem,
        ticketType: 'Technical Support',
    );
    $reminderMail->build();
    $reminderHeaders = $reminderMail->headers();

    // Verify Root Message-ID
    $expectedRootId = "ticket-{$ref}@ticketing.stii.local";
    expect($createdHeaders->messageId)->toBe($expectedRootId);

    // Verify Subject Coherence
    $expectedBaseSubject = "[Ticket #{$ref}] IT Support: Workstation network cable damaged";
    expect($createdMail->subject)->toBe($expectedBaseSubject);
    expect($assignedMail->subject)->toBe("Re: {$expectedBaseSubject}");
    expect($attendedMail->subject)->toBe("Re: {$expectedBaseSubject}");
    expect($messageMail->subject)->toBe("Re: {$expectedBaseSubject}");
    expect($completedMail->subject)->toBe("Re: {$expectedBaseSubject}");
    expect($reminderMail->subject)->toBe("Re: {$expectedBaseSubject}");

    // Verify In-Reply-To and References Headers on all follow-ups
    foreach ([$assignedHeaders, $attendedHeaders, $messageHeaders, $completedHeaders, $reminderHeaders] as $headers) {
        expect($headers->references)->toContain($expectedRootId);
        expect($headers->text['In-Reply-To'])->toBe("<{$expectedRootId}>");
    }
});

it('sends root ticket created acknowledgment email on ticket submission from client controller', function (): void {
    Mail::fake();

    $empCode = 'EMP-THREAD-1';
    DB::table('ticket_employees')->insert([
        'employee_code' => $empCode,
        'fname' => 'Clara',
        'lname' => 'Reyes',
        'email' => 'clara.reyes@stii.dost.gov.ph',
    ]);

    $response = $this->post('/client/tickets', [
        'employee_code' => $empCode,
        'ticket_type' => 'Technical Support',
        'ticket_problem_that_needed_support' => 'Cannot print document on shared HP printer',
    ]);

    $response->assertSessionHas('success', 'Ticket created');
    $refNo = session('refNo');
    expect($refNo)->not->toBeEmpty();

    Mail::assertQueued(TicketCreatedClientNotificationMail::class, function ($mail) use ($refNo): bool {
        return $mail->hasTo('clara.reyes@stii.dost.gov.ph')
            && $mail->refNo === $refNo
            && $mail->headers()->messageId === "ticket-{$refNo}@ticketing.stii.local";
    });
});

it('sends root ticket created acknowledgment email when staff creates ticket via storeNew', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Michael Binondo',
        'role' => 'Technical Specialist',
    ]);

    $empCode = 'EMP-THREAD-2';
    DB::table('ticket_employees')->insert([
        'employee_code' => $empCode,
        'fname' => 'Pedro',
        'lname' => 'Penduko',
        'email' => 'pedro.penduko@stii.dost.gov.ph',
    ]);

    $response = $this->actingAs($user)->post('/tickets-v2', [
        'client_type' => 'Internal',
        'client_name' => 'Pedro Penduko',
        'client_empID' => $empCode,
        'ticket_type' => 'Technical Support',
        'ticket_problem_that_needed_support' => 'Operating system blue screen error',
    ]);

    $response->assertSessionHas('success');
    $refNo = session('refNo');
    expect($refNo)->not->toBeEmpty();

    Mail::assertQueued(TicketCreatedClientNotificationMail::class, function ($mail) use ($refNo): bool {
        return $mail->hasTo('pedro.penduko@stii.dost.gov.ph')
            && $mail->refNo === $refNo;
    });
});
