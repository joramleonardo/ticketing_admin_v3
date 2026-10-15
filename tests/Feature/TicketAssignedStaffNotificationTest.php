<?php

use App\Mail\TicketAssignedClientNotificationMail;
use App\Mail\TicketAssignedStaffNotificationMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

it('sends email notification to IT staff when ticket is assigned via bulkAssign', function (): void {
    Mail::fake();

    $admin = User::factory()->create([
        'name' => 'Chester Francisco',
        'role' => 'Admin',
    ]);

    $staff = User::factory()->create([
        'name' => 'John Michael Cagadas',
        'email' => 'jmcagadas@stii.dost.gov.ph',
        'role' => 'Staff',
    ]);

    $ref = '20261014-STF1';

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'ticket_problem_that_needed_support' => 'Network printer offline in Admin office',
        'ticket_assigned_staff' => null,
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

    $response = $this->actingAs($admin)->postJson('/admin/tickets-v2/bulk-assign', [
        'ticket_ids' => [$ref],
        'assigned_staff' => $staff->name,
        'ticket_type' => 'Technical Support',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'email_notified' => true,
        'notified_email' => 'jmcagadas@stii.dost.gov.ph',
    ]);

    // Check DB status updated to Pending and assigned to staff
    $this->assertDatabaseHas('v2_ticket_details', [
        'client_referenceNo' => $ref,
        'ticket_assigned_staff' => 'John Michael Cagadas',
        'ticket_status' => 'Pending',
    ]);

    // Check activity log
    $this->assertDatabaseHas('v2_ticket_activity_logs', [
        'client_referenceNo' => $ref,
        'activity_name' => 'Assigned to John Michael Cagadas (Bulk)',
        'activity_user' => 'Chester Francisco',
    ]);

    // Check mail queued
    Mail::assertQueued(TicketAssignedStaffNotificationMail::class, function ($mail) use ($ref): bool {
        return $mail->hasTo('jmcagadas@stii.dost.gov.ph')
            && $mail->staffName === 'John Michael Cagadas'
            && $mail->assignedBy === 'Chester Francisco'
            && count($mail->tickets) === 1
            && $mail->tickets[0]['refNo'] === $ref
            && $mail->tickets[0]['clientName'] === 'Maria Santos'
            && str_contains($mail->viewUrl, '/admin/my-tickets?search='.$ref);
    });

    // Check client notification queued
    Mail::assertQueued(TicketAssignedClientNotificationMail::class, function ($mail) use ($ref): bool {
        return $mail->hasTo('maria.santos@stii.dost.gov.ph')
            && $mail->refNo === $ref
            && $mail->staffName === 'John Michael Cagadas'
            && $mail->clientName === 'Maria Santos'
            && str_contains($mail->trackUrl, '/client?track='.$ref);
    });
});

it('sends consolidated email notification when multiple tickets are assigned', function (): void {
    Mail::fake();

    $admin = User::factory()->create([
        'name' => 'Admin Boss',
        'role' => 'Super Admin',
    ]);

    $staff = User::factory()->create([
        'name' => 'Mark Cruz',
        'email' => 'mark.cruz@stii.dost.gov.ph',
        'role' => 'Staff',
    ]);

    $ref1 = '20261014-MLT1';
    $ref2 = '20261014-MLT2';

    DB::table('v2_ticket_details')->insert([
        [
            'client_referenceNo' => $ref1,
            'ticket_type' => 'Technical Support',
            'ticket_status' => 'New',
            'ticket_problem_that_needed_support' => 'Outlook not syncing',
            'ticket_assigned_staff' => null,
            'date_requested' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'client_referenceNo' => $ref2,
            'ticket_type' => 'Technical Support',
            'ticket_status' => 'New',
            'ticket_problem_that_needed_support' => 'LAN cable damaged',
            'ticket_assigned_staff' => null,
            'date_requested' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('v2_ticket_client_details')->insert([
        [
            'client_referenceNo' => $ref1,
            'client_name' => 'Pedro Penduko',
            'client_division_agency' => 'IRAD',
            'client_email' => 'pedro@stii.dost.gov.ph',
            'client_empID' => 'EMP-5678',
            'client_type' => 'Internal',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'client_referenceNo' => $ref2,
            'client_name' => 'Juan Dela Cruz',
            'client_division_agency' => 'CRPD',
            'client_email' => 'juan@stii.dost.gov.ph',
            'client_empID' => 'EMP-9012',
            'client_type' => 'Internal',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->actingAs($admin)->postJson('/admin/tickets-v2/bulk-assign', [
        'ticket_ids' => [$ref1, $ref2],
        'assigned_staff' => $staff->name,
        'ticket_type' => 'Technical Support',
    ]);

    $response->assertSuccessful();

    // Check mail queued with 2 tickets
    Mail::assertQueued(TicketAssignedStaffNotificationMail::class, function ($mail): bool {
        return $mail->hasTo('mark.cruz@stii.dost.gov.ph')
            && count($mail->tickets) === 2
            && str_contains($mail->viewUrl, '/admin/my-tickets');
    });

    // Check each client received individual notification
    Mail::assertQueued(TicketAssignedClientNotificationMail::class, function ($mail) use ($ref1): bool {
        return $mail->hasTo('pedro@stii.dost.gov.ph')
            && $mail->refNo === $ref1
            && $mail->staffName === 'Mark Cruz';
    });

    Mail::assertQueued(TicketAssignedClientNotificationMail::class, function ($mail) use ($ref2): bool {
        return $mail->hasTo('juan@stii.dost.gov.ph')
            && $mail->refNo === $ref2
            && $mail->staffName === 'Mark Cruz';
    });
});

it('sends email notification when staff is assigned via single ticket update endpoint', function (): void {
    Mail::fake();

    $admin = User::factory()->create([
        'name' => 'Lead Admin',
        'role' => 'Admin',
    ]);

    $staff = User::factory()->create([
        'name' => 'Joram Kate Leonardo',
        'email' => 'joram.leonardo@stii.dost.gov.ph',
        'role' => 'Staff',
    ]);

    $ref = '20261014-SGL1';

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'ticket_problem_that_needed_support' => 'Software installation requested',
        'ticket_assigned_staff' => null,
        'date_requested' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Ana Reyes',
        'client_division_agency' => 'OD',
        'client_email' => 'ana.reyes@stii.dost.gov.ph',
        'client_empID' => 'EMP-3344',
        'client_type' => 'Internal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->putJson("/tickets/{$ticketId}", [
        'ticket_assigned_staff' => $staff->name,
        'ticket_status' => 'Pending',
    ]);

    $response->assertSuccessful();

    Mail::assertQueued(TicketAssignedStaffNotificationMail::class, function ($mail) use ($ref): bool {
        return $mail->hasTo('joram.leonardo@stii.dost.gov.ph')
            && $mail->staffName === 'Joram Kate Leonardo'
            && count($mail->tickets) === 1
            && $mail->tickets[0]['refNo'] === $ref;
    });

    Mail::assertQueued(TicketAssignedClientNotificationMail::class, function ($mail) use ($ref): bool {
        return $mail->hasTo('ana.reyes@stii.dost.gov.ph')
            && $mail->refNo === $ref
            && $mail->staffName === 'Joram Kate Leonardo'
            && $mail->clientName === 'Ana Reyes';
    });
});

it('does not send email if staff user does not have a registered email', function (): void {
    Mail::fake();

    $admin = User::factory()->create([
        'name' => 'Lead Admin',
        'role' => 'Admin',
    ]);

    $ref = '20261014-NOEMAIL';

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'ticket_problem_that_needed_support' => 'Hardware issue',
        'ticket_assigned_staff' => null,
        'date_requested' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Client User',
        'client_division_agency' => 'FAD',
        'client_email' => 'client@stii.dost.gov.ph',
        'client_empID' => 'EMP-1111',
        'client_type' => 'Internal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->postJson('/admin/tickets-v2/bulk-assign', [
        'ticket_ids' => [$ref],
        'assigned_staff' => 'Ghost Staff Without Account',
        'ticket_type' => 'Technical Support',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'email_notified' => false,
    ]);

    Mail::assertNotQueued(TicketAssignedStaffNotificationMail::class);
    Mail::assertQueued(TicketAssignedClientNotificationMail::class, function ($mail) use ($ref): bool {
        return $mail->hasTo('client@stii.dost.gov.ph')
            && $mail->refNo === $ref
            && $mail->staffName === 'Ghost Staff Without Account';
    });
});

it('resolves staff email from ticket_employees table when user email is not set', function (): void {
    Mail::fake();

    $admin = User::factory()->create([
        'name' => 'Lead Admin',
        'role' => 'Admin',
    ]);

    // Create a staff user without email (simulating production MySQL users table)
    $staff = User::factory()->create([
        'name' => 'Reiner Zagada',
        'username' => 'reiner',
        'email' => null,
        'role' => 'Staff',
    ]);

    // Insert corresponding employee record in ticket_employees
    DB::table('ticket_employees')->insert([
        'fname' => 'Reiner',
        'lname' => 'Zagada',
        'employee_code' => '24-11',
        'email' => 'reiner.zagada@stii.dost.gov.ph',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ref = '20261014-EMP-RESOLVE';

    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'ticket_problem_that_needed_support' => 'Display monitor replacement',
        'ticket_assigned_staff' => null,
        'date_requested' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Client Employee',
        'client_division_agency' => 'CRPD',
        'client_email' => 'client.emp@stii.dost.gov.ph',
        'client_empID' => 'EMP-5555',
        'client_type' => 'Internal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->postJson('/admin/tickets-v2/bulk-assign', [
        'ticket_ids' => [$ref],
        'assigned_staff' => 'Reiner Zagada',
        'ticket_type' => 'Technical Support',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'email_notified' => true,
        'notified_email' => 'reiner.zagada@stii.dost.gov.ph',
    ]);

    Mail::assertQueued(TicketAssignedStaffNotificationMail::class, function ($mail): bool {
        return $mail->hasTo('reiner.zagada@stii.dost.gov.ph')
            && $mail->staffName === 'Reiner Zagada';
    });

    Mail::assertQueued(TicketAssignedClientNotificationMail::class, function ($mail) use ($ref): bool {
        return $mail->hasTo('client.emp@stii.dost.gov.ph')
            && $mail->refNo === $ref
            && $mail->staffName === 'Reiner Zagada';
    });
});

it('falls back to ticket_employees email when client_email is missing on assignment', function (): void {
    Mail::fake();

    $admin = User::factory()->create([
        'name' => 'Lead Admin',
        'role' => 'Admin',
    ]);

    $staff = User::factory()->create([
        'name' => 'Jane Tech',
        'email' => 'jane.tech@stii.dost.gov.ph',
        'role' => 'Staff',
    ]);

    $empCode = 'EMP-FALLBACK-ASSIGN';
    DB::table('ticket_employees')->insert([
        'employee_code' => $empCode,
        'fname' => 'Elena',
        'lname' => 'Ramos',
        'email' => 'elena.ramos@stii.dost.gov.ph',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ref = '20261015-FALLBACK-ASSIGN';

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'ticket_problem_that_needed_support' => 'Cannot connect to VPN',
        'ticket_assigned_staff' => null,
        'date_requested' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Elena Ramos',
        'client_division_agency' => 'OD-MISPS',
        'client_email' => null,
        'client_empID' => $empCode,
        'client_type' => 'Internal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->putJson("/tickets/{$ticketId}", [
        'ticket_assigned_staff' => $staff->name,
        'ticket_status' => 'Pending',
    ]);

    $response->assertSuccessful();

    Mail::assertQueued(TicketAssignedClientNotificationMail::class, function ($mail) use ($ref): bool {
        return $mail->hasTo('elena.ramos@stii.dost.gov.ph')
            && $mail->refNo === $ref
            && $mail->staffName === 'Jane Tech';
    });
});

it('does not send client email when client has no registered email anywhere on assignment', function (): void {
    Mail::fake();

    $admin = User::factory()->create([
        'name' => 'Lead Admin',
        'role' => 'Admin',
    ]);

    $staff = User::factory()->create([
        'name' => 'Jane Tech',
        'email' => 'jane.tech@stii.dost.gov.ph',
        'role' => 'Staff',
    ]);

    $ref = '20261015-NO-CLIENT-EMAIL';

    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'ticket_problem_that_needed_support' => 'Keyboard replacement',
        'ticket_assigned_staff' => null,
        'date_requested' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'External Guest Without Email',
        'client_division_agency' => null,
        'client_email' => null,
        'client_empID' => null,
        'client_type' => 'External',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->putJson("/tickets/{$ticketId}", [
        'ticket_assigned_staff' => $staff->name,
        'ticket_status' => 'Pending',
    ]);

    $response->assertSuccessful();

    // Staff receives email
    Mail::assertQueued(TicketAssignedStaffNotificationMail::class);
    // Client does NOT receive email
    Mail::assertNotQueued(TicketAssignedClientNotificationMail::class);
});

it('renders the client assignment email markdown template correctly with details', function (): void {
    $ref = '20261015-ASSIGN-RENDER';
    $mail = new TicketAssignedClientNotificationMail(
        refNo: $ref,
        clientName: 'Alexander Bell',
        staffName: 'Nikola Tesla',
        trackUrl: "http://ticketing.stii.local/client?track={$ref}&open=1",
        problem: 'Audio headset microphone produces excessive static',
        ticketType: 'Hardware',
        status: 'Pending',
    );

    $html = $mail->render();

    expect($html)
        ->toContain('Your Support Ticket Has Been Assigned')
        ->toContain('Alexander Bell')
        ->toContain('Nikola Tesla')
        ->toContain($ref)
        ->toContain('Hardware')
        ->toContain('Audio headset microphone produces excessive static')
        ->toContain("http://ticketing.stii.local/client?track={$ref}&amp;open=1");
});
