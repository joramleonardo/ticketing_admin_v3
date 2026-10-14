<?php

use App\Mail\TicketAttendedNotificationMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

it('sends email notification to client when staff attends a ticket', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'John Michael Cagadas',
        'role' => 'Technical Specialist',
    ]);

    $ref = '20261014-ATT1';

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'Pending',
        'ticket_problem_that_needed_support' => 'Monitor flickering on workstation 3',
        'ticket_assigned_staff' => $user->name,
        'date_requested' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Maria Santos',
        'client_division_agency' => 'FAD',
        'client_email' => 'maria.santos@stii.dost.gov.ph',
        'client_empID' => 'EMP-1234',
        'client_type' => 'Internal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->put("/tickets-attend/{$ticketId}", [
        'ticket_status' => 'In Progress',
    ]);

    $response->assertSessionHas('success', 'Ticket attended successfully and client notified via email!');

    // Check DB updates
    $this->assertDatabaseHas('v2_ticket_details', [
        'ticket_id' => $ticketId,
        'ticket_status' => 'In Progress',
        'ticket_attended_by' => 'John Michael Cagadas',
    ]);

    $this->assertDatabaseHas('v2_ticket_activity_logs', [
        'client_referenceNo' => $ref,
        'activity_name' => 'Ticket Attended',
        'activity_user' => 'John Michael Cagadas',
    ]);

    // Check email was queued
    Mail::assertQueued(TicketAttendedNotificationMail::class, function ($mail) use ($ref): bool {
        return $mail->hasTo('maria.santos@stii.dost.gov.ph')
            && $mail->refNo === $ref
            && $mail->clientName === 'Maria Santos'
            && $mail->staffName === 'John Michael Cagadas'
            && $mail->ticketType === 'Technical Support'
            && $mail->problem === 'Monitor flickering on workstation 3'
            && str_contains($mail->trackUrl, "ticketing.stii.local/client?track={$ref}");
    });
});

it('falls back to ticket_employees email when client_email is missing', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Mark Cruz',
        'role' => 'Technical Specialist',
    ]);

    $empCode = 'EMP-8888';
    DB::table('ticket_employees')->insert([
        'employee_code' => $empCode,
        'fname' => 'Juan',
        'lname' => 'Dela Cruz',
        'email' => 'juan.delacruz@stii.dost.gov.ph',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ref = '20261014-ATT2';

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'ticket_problem_that_needed_support' => 'Keyboard spacebar jammed',
        'ticket_assigned_staff' => $user->name,
        'date_requested' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Juan Dela Cruz',
        'client_division_agency' => 'CRPD',
        'client_email' => null, // empty email on client details
        'client_empID' => $empCode,
        'client_type' => 'Internal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->put("/tickets-attend/{$ticketId}", [
        'ticket_status' => 'In Progress',
    ]);

    $response->assertSessionHas('success', 'Ticket attended successfully and client notified via email!');

    Mail::assertQueued(TicketAttendedNotificationMail::class, function ($mail): bool {
        return $mail->hasTo('juan.delacruz@stii.dost.gov.ph');
    });
});

it('does not send email when client has no registered email anywhere', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Mark Cruz',
        'role' => 'Technical Specialist',
    ]);

    $ref = '20261014-ATT3';

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'ticket_problem_that_needed_support' => 'Printer setup',
        'ticket_assigned_staff' => $user->name,
        'date_requested' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Anonymous Walk-in',
        'client_division_agency' => null,
        'client_email' => null,
        'client_empID' => null,
        'client_type' => 'External',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->put("/tickets-attend/{$ticketId}", [
        'ticket_status' => 'In Progress',
    ]);

    $response->assertSessionHas('success', 'Ticket attended successfully and now in progress!');

    $this->assertDatabaseHas('v2_ticket_details', [
        'ticket_id' => $ticketId,
        'ticket_status' => 'In Progress',
        'ticket_attended_by' => 'Mark Cruz',
    ]);

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});
