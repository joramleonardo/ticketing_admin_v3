<?php

use App\Mail\StaffMessageNotificationMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

it('sends email notification to client when staff posts a message', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'John Michael Cagadas',
        'role' => 'Technical Specialist',
    ]);

    $ref = '20261014-991';

    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'In Progress',
        'ticket_problem_that_needed_support' => 'Cannot connect to network printer in FAD',
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

    $messageText = 'Hello Maria, we have rebooted the printer. Could you please check if you can print now?';

    $response = $this->actingAs($user)->postJson("/admin/tickets/{$ref}/messages", [
        'message' => $messageText,
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'ok' => true,
        'email_notified' => true,
        'notified_email' => 'maria.santos@stii.dost.gov.ph',
    ]);

    // Verify stored in DB
    $this->assertDatabaseHas('v2_ticket_messages', [
        'client_referenceNo' => $ref,
        'sender_type' => 'staff',
        'sender_name' => 'John Michael Cagadas',
        'message' => $messageText,
    ]);

    // Verify activity log created
    $this->assertDatabaseHas('v2_ticket_activity_logs', [
        'client_referenceNo' => $ref,
        'activity_name' => 'Staff Message',
        'activity_user' => 'John Michael Cagadas',
    ]);

    // Verify Mail was queued to client email
    Mail::assertQueued(StaffMessageNotificationMail::class, function ($mail) use ($ref): bool {
        return $mail->hasTo('maria.santos@stii.dost.gov.ph')
            && $mail->refNo === $ref
            && $mail->clientName === 'Maria Santos'
            && $mail->staffName === 'John Michael Cagadas'
            && str_contains($mail->messageText, 'rebooted the printer')
            && str_contains($mail->trackUrl, "ticketing.stii.local/client?track={$ref}")
            && $mail->problem === 'Cannot connect to network printer in FAD';
    });
});

it('does not send email when client has no registered email', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'John Michael Cagadas',
        'role' => 'Technical Specialist',
    ]);

    $ref = '20261014-992';

    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'In Progress',
        'ticket_problem_that_needed_support' => 'Mouse not responding',
        'ticket_assigned_staff' => $user->name,
        'date_requested' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Pedro Penduko',
        'client_division_agency' => 'IRAD',
        'client_email' => null,
        'client_empID' => 'EMP-5678',
        'client_type' => 'Internal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson("/admin/tickets/{$ref}/messages", [
        'message' => 'We will replace your mouse this afternoon.',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'ok' => true,
        'email_notified' => false,
        'notified_email' => null,
    ]);

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

it('redirects browser requests from /client/track/{ref} to the interactive client tracker page', function (): void {
    $ref = '20261014-993';

    $response = $this->get("/client/track/{$ref}");

    $response->assertRedirect(route('client.main', ['track' => $ref, 'open' => '1']));
});

it('renders the client tracker page with prefilled track props', function (): void {
    $ref = '20261014-994';

    $response = $this->get("/client?track={$ref}&open=1");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('TicketsV2/ClientMain')
        ->where('initialView', 'track')
        ->where('initialCode', $ref)
        ->where('autoOpen', true)
    );
});
