<?php

use App\Mail\TicketCompletedMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

it('sends email notification to client when staff completes an in-progress ticket', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Michael Binondo',
        'role' => 'Technical Specialist',
    ]);

    $ref = '20261015-CMP1';

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'In Progress',
        'ticket_attended_by' => $user->name,
        'ticket_problem_that_needed_support' => 'Cannot connect to local network printer',
        'date_requested' => now()->subHours(2),
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(1),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Maria Clara',
        'client_division_agency' => 'FAD',
        'client_email' => 'maria.clara@stii.dost.gov.ph',
        'client_empID' => 'EMP-5566',
        'client_type' => 'Internal',
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($user)->put("/tickets-complete/{$ticketId}", [
        'ticket_status' => 'Completed',
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => 'Hardware',
        'ticket_support_type' => 'Printer',
        'ticket_actions_taken' => 'Re-installed print drivers and tested network test page successfully.',
        'ticket_remarks' => 'Printer operational and verified by user.',
    ]);

    $response->assertSessionHas('success', 'Ticket completed successfully and client notified via email!');

    $this->assertDatabaseHas('v2_ticket_details', [
        'ticket_id' => $ticketId,
        'ticket_status' => 'Completed',
        'ticket_attended_by' => 'Michael Binondo',
        'ticket_actions_taken' => 'Re-installed print drivers and tested network test page successfully.',
    ]);

    $this->assertDatabaseHas('v2_ticket_activity_logs', [
        'client_referenceNo' => $ref,
        'activity_name' => 'Ticket Completed',
        'activity_user' => 'Michael Binondo',
    ]);

    Mail::assertQueued(TicketCompletedMail::class, function ($mail) use ($ref): bool {
        return $mail->hasTo('maria.clara@stii.dost.gov.ph')
            && $mail->refNo === $ref
            && $mail->clientName === 'Maria Clara'
            && $mail->staffName === 'Michael Binondo'
            && $mail->problem === 'Cannot connect to local network printer'
            && $mail->actionsTaken === 'Re-installed print drivers and tested network test page successfully.'
            && $mail->ticketType === 'Technical Support'
            && str_contains($mail->rateUrl, "ticketing.stii.local/client/rate/{$ref}")
            && str_contains($mail->trackUrl, "ticketing.stii.local/client?track={$ref}");
    });
});

it('falls back to ticket_employees email when client_email is missing upon ticket completion', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Chester Francisco',
        'role' => 'Technical Specialist',
    ]);

    $empCode = 'EMP-9999';
    DB::table('ticket_employees')->insert([
        'employee_code' => $empCode,
        'fname' => 'Crisostomo',
        'lname' => 'Ibarra',
        'email' => 'crisostomo.ibarra@stii.dost.gov.ph',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ref = '20261015-CMP2';

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'In Progress',
        'ticket_attended_by' => $user->name,
        'ticket_problem_that_needed_support' => 'Software update installation error',
        'date_requested' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subHours(2),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Crisostomo Ibarra',
        'client_division_agency' => 'IRAD',
        'client_email' => null,
        'client_empID' => $empCode,
        'client_type' => 'Internal',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)->put("/tickets-complete/{$ticketId}", [
        'ticket_status' => 'Completed',
        'ticket_actions_taken' => 'Updated operating system patches and reinstalled software application.',
    ]);

    $response->assertSessionHas('success', 'Ticket completed successfully and client notified via email!');

    Mail::assertQueued(TicketCompletedMail::class, function ($mail): bool {
        return $mail->hasTo('crisostomo.ibarra@stii.dost.gov.ph');
    });
});

it('completes ticket without sending email when client has no registered email anywhere', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Jomar Rabanera',
        'role' => 'Technical Specialist',
    ]);

    $ref = '20261015-CMP3';

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Livestream',
        'ticket_status' => 'In Progress',
        'ticket_attended_by' => $user->name,
        'ticket_problem_that_needed_support' => 'Livestream audio setup',
        'date_requested' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Walk-in Guest',
        'client_division_agency' => null,
        'client_email' => null,
        'client_empID' => null,
        'client_type' => 'External',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->put("/tickets-complete/{$ticketId}", [
        'ticket_status' => 'Completed',
        'ticket_actions_taken' => 'Audio console configured and tested on stream.',
    ]);

    $response->assertSessionHas('success', 'Ticket completed successfully!');

    $this->assertDatabaseHas('v2_ticket_details', [
        'ticket_id' => $ticketId,
        'ticket_status' => 'Completed',
    ]);

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

it('renders the completed email markdown template correctly with rating and tracking URLs', function (): void {
    $mailable = new TicketCompletedMail(
        refNo: '20261015-TEST',
        clientName: 'Juan Dela Cruz',
        staffName: 'Lloyd Mandapat',
        rateUrl: 'http://ticketing.stii.local/client/rate/20261015-TEST',
        trackUrl: 'http://ticketing.stii.local/client?track=20261015-TEST&open=1',
        problem: 'System login issues on staff portal',
        actionsTaken: 'Reset user credentials and cleared authentication cache.',
        ticketType: 'Technical Support',
        dateCompleted: 'Oct 15, 2026 10:00 AM',
    );

    $html = $mailable->render();

    expect($html)->toContain('20261015-TEST')
        ->toContain('Juan Dela Cruz')
        ->toContain('Lloyd Mandapat')
        ->toContain('System login issues on staff portal')
        ->toContain('Reset user credentials and cleared authentication cache.')
        ->toContain('http://ticketing.stii.local/client/rate/20261015-TEST')
        ->toContain('Rate This Ticket');
});
