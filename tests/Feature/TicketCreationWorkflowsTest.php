<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('creates standard ticket with dynamic assigned_by user and in progress status', function (): void {
    $user = User::factory()->create(['name' => 'Maria Santos']);

    $response = $this->actingAs($user)->post('/tickets-v2', [
        'client_type' => 'Internal',
        'client_name' => 'Juan Dela Cruz',
        'client_empID' => 'EMP-1001',
        'client_sex' => 'Male',
        'client_division_agency' => 'FAD',
        'ticket_type' => 'Technical Support',
        'ticket_problem_that_needed_support' => 'Cannot connect to office Wi-Fi network.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $response->assertSessionHas('refNo');

    $refNo = session('refNo');
    expect($refNo)->toMatch('/^\d{8}-\d{3}$/');

    $client = DB::table('v2_ticket_client_details')->where('client_referenceNo', $refNo)->first();
    expect($client)->not->toBeNull()
        ->and($client->client_name)->toBe('Juan Dela Cruz')
        ->and($client->client_division_agency)->toBe('FAD');

    $detail = DB::table('v2_ticket_details')->where('client_referenceNo', $refNo)->first();
    expect($detail)->not->toBeNull()
        ->and($detail->ticket_status)->toBe('In Progress')
        ->and($detail->ticket_assigned_by)->toBe('Maria Santos')
        ->and($detail->ticket_assigned_staff)->toBe('Maria Santos')
        ->and($detail->ticket_attended_by)->toBe('Maria Santos');

    $logs = DB::table('v2_ticket_activity_logs')
        ->where('client_referenceNo', $refNo)
        ->orderBy('activity_id')
        ->get();

    expect($logs)->toHaveCount(3);
    expect($logs->pluck('activity_name')->all())->toEqual([
        'Ticket Created',
        'Ticket Assigned',
        'Ticket Attended',
    ]);
    expect($logs[0]->activity_user)->toBe('Juan Dela Cruz');
    expect($logs[1]->activity_user)->toBe('Maria Santos');
    expect($logs[2]->activity_user)->toBe('Maria Santos');
});

it('creates late ticket with status Completed and accurate historical dates', function (): void {
    $user = User::factory()->create(['name' => 'Alex Admin', 'role' => 'Admin']);

    $dateRequested = '2026-01-10 09:00:00';
    $dateCompleted = '2026-01-10 11:30:00';

    $response = $this->actingAs($user)->post('/admin/tickets-v2', [
        'client_type' => 'Internal',
        'client_name' => 'Clara Reyes',
        'client_division_agency' => 'IRAD',
        'client_empID' => 'EMP-2042',
        'client_sex' => 'Female',
        'ticket_type' => 'Events',
        'ticket_nature_of_support' => 'Events',
        'ticket_support_type' => 'Livestream Setup',
        'ticket_attended_by' => 'Mark Jayson Sison',
        'ticket_assisted_by' => ['Etienne Wayne Amparado', 'Chester Francisco'],
        'ticket_problem_that_needed_support' => 'Auditorium hybrid event livestream setup and testing.',
        'ticket_actions_taken' => 'Set up audio mixers, configured OBS broadcast and verified backup stream.',
        'ticket_remarks' => 'Event completed on schedule without technical hitches.',
        'date_requested' => $dateRequested,
        'date_completed' => $dateCompleted,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $response->assertSessionHas('refNo');

    $refNo = session('refNo');
    expect($refNo)->toStartWith('20260110-');

    $client = DB::table('v2_ticket_client_details')->where('client_referenceNo', $refNo)->first();
    expect($client)->not->toBeNull()
        ->and($client->client_name)->toBe('Clara Reyes')
        ->and($client->client_division_agency)->toBe('IRAD');

    $detail = DB::table('v2_ticket_details')->where('client_referenceNo', $refNo)->first();
    expect($detail)->not->toBeNull()
        ->and($detail->ticket_status)->toBe('Completed')
        ->and($detail->ticket_type)->toBe('Events')
        ->and($detail->ticket_nature_of_support)->toBe('Events')
        ->and($detail->ticket_support_type)->toBe('Livestream Setup')
        ->and($detail->ticket_assigned_by)->toBe('Alex Admin')
        ->and($detail->ticket_assigned_staff)->toBe('Mark Jayson Sison')
        ->and($detail->ticket_attended_by)->toBe('Mark Jayson Sison')
        ->and($detail->ticket_assisted_by)->toBe('Etienne Wayne Amparado, Chester Francisco')
        ->and($detail->ticket_actions_taken)->toBe('Set up audio mixers, configured OBS broadcast and verified backup stream.');

    $logs = DB::table('v2_ticket_activity_logs')
        ->where('client_referenceNo', $refNo)
        ->orderBy('activity_id')
        ->get();

    expect($logs)->toHaveCount(4);
    expect($logs->pluck('activity_name')->all())->toEqual([
        'Ticket Created',
        'Ticket Assigned',
        'Ticket Attended',
        'Ticket Completed',
    ]);
    expect($logs[0]->activity_user)->toBe('Clara Reyes');
    expect($logs[1]->activity_user)->toBe('Alex Admin');
    expect($logs[2]->activity_user)->toBe('Mark Jayson Sison');
    expect($logs[3]->activity_user)->toBe('Mark Jayson Sison');
});

it('validates that date_completed cannot be earlier than date_requested for late tickets', function (): void {
    $user = User::factory()->create(['name' => 'Staff Specialist']);

    $response = $this->actingAs($user)->from('/admin/tickets-v2')->post('/admin/tickets-v2', [
        'client_type' => 'External',
        'client_name' => 'Agency Visitor',
        'client_division_agency' => 'DOST-CO',
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => 'Hardware',
        'ticket_problem_that_needed_support' => 'Printer setup',
        'date_requested' => '2026-02-15 14:00:00',
        'date_completed' => '2026-02-15 10:00:00', // earlier than date_requested
    ]);

    $response->assertRedirect('/admin/tickets-v2');
    $response->assertSessionHasErrors(['date_completed']);
});

it('avoids reference sequence collision on late tickets even when an earlier ticket was deleted', function (): void {
    $user = User::factory()->create(['name' => 'Admin User']);

    // Seed ticket with sequence 002 for 20260301 (simulating 001 was deleted)
    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => '20260301-002',
        'client_name' => 'Previous Employee',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => '20260301-002',
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'Completed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->post('/admin/tickets-v2', [
        'client_type' => 'Internal',
        'client_name' => 'Second Employee',
        'client_division_agency' => 'CRPD',
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => 'Software',
        'ticket_problem_that_needed_support' => 'Antivirus update',
        'date_requested' => '2026-03-01 08:00:00',
        'date_completed' => '2026-03-01 09:00:00',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('refNo');
    expect(session('refNo'))->toBe('20260301-003');
});

it('returns json response when requested with wantsJson on late ticket creation', function (): void {
    $user = User::factory()->create(['name' => 'API Admin']);

    $response = $this->actingAs($user)->postJson('/admin/tickets-v2', [
        'client_type' => 'Internal',
        'client_name' => 'JSON Client',
        'client_division_agency' => 'OD-MISPS',
        'ticket_type' => 'Livestream',
        'ticket_nature_of_support' => 'Livestream',
        'ticket_problem_that_needed_support' => 'Zoom Webinar co-host setup',
        'date_requested' => '2026-04-05 10:00:00',
        'date_completed' => '2026-04-05 11:00:00',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'message' => 'Late ticket entry recorded successfully!',
    ]);
    expect($response->json('refNo'))->toStartWith('20260405-');
});
