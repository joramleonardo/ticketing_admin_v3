<?php

use App\Models\TicketDetail;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('creates and associates a TicketEvent record on v2_ticket_events with other_request', function (): void {
    $refNo = '20261015-999';

    $ticket = TicketDetail::create([
        'client_referenceNo' => $refNo,
        'ticket_status' => 'New',
        'ticket_type' => 'Events',
        'ticket_nature_of_support' => 'Events',
        'ticket_support_type' => 'Others',
        'ticket_problem_that_needed_support' => 'Event Support: STII Cast Room (2026-10-20)',
        'ticket_actions_taken' => '',
        'ticket_remarks' => '',
        'date_requested' => now(),
    ]);

    $event = TicketEvent::create([
        'client_referenceNo' => $refNo,
        'event_start_date' => '2026-10-20',
        'event_end_date' => '2026-10-22',
        'event_venue' => 'STII Cast Room',
        'event_remarks' => 'National Science & Technology Week Seminar',
        'other_request' => 'Need 4 wireless microphones, HDMI splitter, and projector setup.',
    ]);

    expect($event->event_id)->not->toBeNull()
        ->and($event->client_referenceNo)->toBe($refNo)
        ->and($event->event_venue)->toBe('STII Cast Room')
        ->and($event->event_remarks)->toBe('National Science & Technology Week Seminar')
        ->and($event->other_request)->toBe('Need 4 wireless microphones, HDMI splitter, and projector setup.');

    // Verify Eloquent relationship
    $loadedTicket = TicketDetail::with('event')->where('client_referenceNo', $refNo)->first();
    expect($loadedTicket)->not->toBeNull()
        ->and($loadedTicket->event)->not->toBeNull()
        ->and($loadedTicket->event->event_venue)->toBe('STII Cast Room')
        ->and($loadedTicket->event->other_request)->toBe('Need 4 wireless microphones, HDMI splitter, and projector setup.');

    // Verify DB table v2_ticket_events directly
    $dbRow = DB::table('v2_ticket_events')->where('client_referenceNo', $refNo)->first();
    expect($dbRow)->not->toBeNull()
        ->and($dbRow->event_venue)->toBe('STII Cast Room')
        ->and($dbRow->event_remarks)->toBe('National Science & Technology Week Seminar')
        ->and($dbRow->other_request)->toBe('Need 4 wireless microphones, HDMI splitter, and projector setup.');
});

it('updates event details and other_request via status update endpoint', function (): void {
    $user = User::factory()->create(['name' => 'IT Specialist', 'role' => 'Staff']);
    $refNo = '20261015-998';

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $refNo,
        'client_name' => 'Event Organizer',
        'client_type' => 'Internal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ticket = TicketDetail::create([
        'client_referenceNo' => $refNo,
        'ticket_status' => 'In Progress',
        'ticket_type' => 'Events',
        'ticket_nature_of_support' => 'Events',
        'ticket_support_type' => 'Others',
        'ticket_problem_that_needed_support' => 'Original request',
        'ticket_actions_taken' => '',
        'ticket_remarks' => '',
        'date_requested' => now(),
    ]);

    $response = $this->actingAs($user)->putJson("/tickets/{$ticket->ticket_id}", [
        'status' => 'In Progress',
        'ticket_type' => 'Events',
        'event_start_date' => '2026-11-05',
        'event_end_date' => '2026-11-06',
        'event_venue' => 'Executive Conference Room',
        'event_remarks' => 'High-level executive briefing',
        'other_request' => 'PA system, 2 wireless mics, and laser pointer',
    ]);

    $response->assertSuccessful();

    $event = TicketEvent::where('client_referenceNo', $refNo)->first();
    expect($event)->not->toBeNull()
        ->and($event->event_venue)->toBe('Executive Conference Room')
        ->and($event->event_remarks)->toBe('High-level executive briefing')
        ->and($event->other_request)->toBe('PA system, 2 wireless mics, and laser pointer');
});

it('creates a standard event ticket via TicketStatusController storeNew', function (): void {
    Illuminate\Support\Facades\Mail::fake();
    $admin = User::factory()->create(['name' => 'Admin User', 'role' => 'Admin']);

    $response = $this->actingAs($admin)->post('/tickets-v2', [
        'client_type' => 'Internal',
        'client_name' => 'Dr. Maria Santos',
        'client_empID' => 'STII-101',
        'client_sex' => 'Female',
        'client_division_agency' => 'CRPD',
        'ticket_type' => 'Events',
        'ticket_assigned_staff' => 'John Doe',
        'event_start_date' => '2026-11-10',
        'event_end_date' => '2026-11-12',
        'event_venue' => 'STII Auditorium',
        'event_remarks' => 'National Science Press Conference',
        'other_request' => 'Livestream setup and 3 wireless lapels',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $refNo = session('refNo');
    expect($refNo)->not->toBeNull();

    $event = TicketEvent::where('client_referenceNo', $refNo)->first();
    expect($event)->not->toBeNull()
        ->and($event->event_venue)->toBe('STII Auditorium')
        ->and($event->event_remarks)->toBe('National Science Press Conference')
        ->and($event->other_request)->toBe('Livestream setup and 3 wireless lapels');

    $ticket = TicketDetail::where('client_referenceNo', $refNo)->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->ticket_type)->toBe('Events')
        ->and($ticket->ticket_nature_of_support)->toBe('Events')
        ->and($ticket->ticket_problem_that_needed_support)->toBe('Livestream setup and 3 wireless lapels');
});

it('creates a late event ticket via TicketV2Controller store', function (): void {
    Illuminate\Support\Facades\Mail::fake();
    $admin = User::factory()->create(['name' => 'Super Admin', 'role' => 'Admin']);

    $response = $this->actingAs($admin)->post('/admin/tickets-v2', [
        'client_name' => 'Gov Specialist',
        'client_type' => 'Internal',
        'client_division_agency' => 'IRAD',
        'client_empID' => 'STII-202',
        'client_sex' => 'Male',
        'ticket_type' => 'Events',
        'ticket_nature_of_support' => 'Events',
        'ticket_support_type' => 'Others',
        'ticket_attended_by' => 'John Doe',
        'ticket_actions_taken' => 'Managed event AV requirements',
        'date_requested' => '2026-10-01 09:00:00',
        'date_completed' => '2026-10-01 17:00:00',
        'event_start_date' => '2026-10-01',
        'event_end_date' => '2026-10-01',
        'event_venue' => 'DOST Executive Lounge',
        'event_remarks' => 'Regional Assembly',
        'other_request' => 'Full sound system and projector screen',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $event = TicketEvent::where('event_venue', 'DOST Executive Lounge')->first();
    expect($event)->not->toBeNull()
        ->and($event->event_remarks)->toBe('Regional Assembly')
        ->and($event->other_request)->toBe('Full sound system and projector screen');
});

it('completes an event ticket via TicketStatusController complete', function (): void {
    Illuminate\Support\Facades\Mail::fake();
    $admin = User::factory()->create(['name' => 'Admin Tech', 'role' => 'Admin']);
    $refNo = '20261015-777';

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $refNo,
        'client_name' => 'Project Lead',
        'client_type' => 'Internal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ticket = TicketDetail::create([
        'client_referenceNo' => $refNo,
        'ticket_status' => 'In Progress',
        'ticket_type' => 'Events',
        'ticket_nature_of_support' => 'Events',
        'ticket_support_type' => 'Others',
        'ticket_problem_that_needed_support' => 'Event Support Needed',
        'ticket_actions_taken' => '',
        'ticket_remarks' => '',
        'date_requested' => now(),
    ]);

    $response = $this->actingAs($admin)->putJson("/tickets-complete/{$ticket->ticket_id}", [
        'ticket_status' => 'Completed',
        'ticket_type' => 'Events',
        'ticket_nature_of_support' => 'Events',
        'ticket_actions_taken' => 'Provided complete sound and stage assistance.',
        'ticket_remarks' => 'Completed on time',
        'event_start_date' => '2026-10-18',
        'event_end_date' => '2026-10-19',
        'event_venue' => 'Main Auditorium',
        'event_remarks' => 'Closing Ceremony',
        'other_request' => '4 microphones, HDMI cable, and clicker',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $event = TicketEvent::where('client_referenceNo', $refNo)->first();
    expect($event)->not->toBeNull()
        ->and($event->event_venue)->toBe('Main Auditorium')
        ->and($event->event_remarks)->toBe('Closing Ceremony')
        ->and($event->other_request)->toBe('4 microphones, HDMI cable, and clicker');

    $fresh = $ticket->fresh();
    expect($fresh->ticket_status)->toBe('Completed')
        ->and($fresh->ticket_actions_taken)->toBe('Provided complete sound and stage assistance.');
});
