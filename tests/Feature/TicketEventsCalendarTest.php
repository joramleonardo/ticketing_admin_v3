<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

it('requires authentication to access the events calendar', function (): void {
    $response = $this->get('/admin/events');
    $response->assertRedirect('/login');
});

it('renders the events calendar monitoring workspace for admin users', function (): void {
    $admin = User::factory()->create([
        'name' => 'Admin Officer',
        'role' => 'Admin',
    ]);

    $ref1 = '20261015-EVT01';
    $ref2 = '20261015-EVT02';

    // Event 1: Single day event
    $client1Id = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $ref1,
        'client_name' => 'Alice Walker',
        'client_division_agency' => 'CRPD',
        'created_at' => now(),
    ]);

    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $client1Id,
        'client_referenceNo' => $ref1,
        'ticket_type' => 'Events',
        'ticket_nature_of_support' => 'Events',
        'ticket_status' => 'In Progress',
        'ticket_assigned_staff' => 'Lloyd Mandapat',
        'date_requested' => Carbon::parse('2026-10-15 08:30:00'),
        'created_at' => now(),
    ]);

    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $ref1,
        'event_start_date' => '2026-10-15',
        'event_end_date' => '2026-10-15',
        'event_venue' => 'Main Auditorium',
        'event_remarks' => 'Livestream setup with microphones',
        'other_request' => '2 wireless lapels',
    ]);

    // Event 2: Multi-day spanning event (3 days duration)
    $client2Id = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $ref2,
        'client_name' => 'Bob Builder',
        'client_division_agency' => 'FAD',
        'created_at' => now(),
    ]);

    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $client2Id,
        'client_referenceNo' => $ref2,
        'ticket_type' => 'Events',
        'ticket_nature_of_support' => 'Events',
        'ticket_status' => 'Completed',
        'ticket_assigned_staff' => 'John Doe',
        'date_requested' => Carbon::parse('2026-10-20 09:00:00'),
        'created_at' => now(),
    ]);

    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $ref2,
        'event_start_date' => '2026-10-20',
        'event_end_date' => '2026-10-22',
        'event_venue' => 'DOST Executive Lounge',
        'event_remarks' => 'Planning summit conference',
        'other_request' => 'Projector and PA system',
    ]);

    $response = $this->actingAs($admin)->get('/admin/events?month=2026-10');
    $response->assertSuccessful();

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('TicketsV2/EventsCalendar')
        ->has('events', 2)
        ->has('kpis', fn ($k) => $k
            ->where('total_events', 2)
            ->where('multi_day_count', 1)
            ->where('completed_count', 1)
            ->etc()
        )
        ->where('events.0.client_referenceNo', fn ($ref) => in_array($ref, [$ref1, $ref2], true))
        ->where('filters.month', '2026-10')
    );

    // Verify detailed event transformation fields
    $events = $response->viewData('page')['props']['events'];
    $multiDayEv = collect($events)->firstWhere('client_referenceNo', $ref2);

    expect($multiDayEv)->not->toBeNull()
        ->and($multiDayEv['duration_days'])->toBe(3)
        ->and($multiDayEv['is_multi_day'])->toBeTrue()
        ->and($multiDayEv['event_venue'])->toBe('DOST Executive Lounge')
        ->and($multiDayEv['other_request'])->toBe('Projector and PA system');

    $singleDayEv = collect($events)->firstWhere('client_referenceNo', $ref1);
    expect($singleDayEv)->not->toBeNull()
        ->and($singleDayEv['duration_days'])->toBe(1)
        ->and($singleDayEv['is_multi_day'])->toBeFalse()
        ->and($singleDayEv['event_venue'])->toBe('Main Auditorium')
        ->and($singleDayEv['other_request'])->toBe('2 wireless lapels');

    $activeStaff = $response->viewData('page')['props']['activeStaff'];
    expect($activeStaff)->toBeArray()->not->toBeEmpty()
        ->and($activeStaff[0])->toBeString();
});

it('filters events by status, division, and search keyword', function (): void {
    $admin = User::factory()->create(['role' => 'Admin']);

    $refA = '20261015-FAD1';
    $refB = '20261015-CRPD1';

    // FAD Event
    $idA = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $refA,
        'client_name' => 'FAD Coordinator',
        'client_division_agency' => '1', // FAD
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $idA,
        'client_referenceNo' => $refA,
        'ticket_type' => 'Events',
        'ticket_status' => 'New',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $refA,
        'event_start_date' => '2026-10-18',
        'event_venue' => 'Boardroom Alpha',
    ]);

    // CRPD Event
    $idB = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $refB,
        'client_name' => 'CRPD Chief',
        'client_division_agency' => '3', // CRPD
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $idB,
        'client_referenceNo' => $refB,
        'ticket_type' => 'Events',
        'ticket_status' => 'In Progress',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $refB,
        'event_start_date' => '2026-10-19',
        'event_venue' => 'Training Hall Beta',
    ]);

    // Filter by Status = 'New'
    $statusResp = $this->actingAs($admin)->get('/admin/events?status=New');
    $statusResp->assertSuccessful();
    $statusEvents = $statusResp->viewData('page')['props']['events'];
    expect($statusEvents)->toHaveCount(1)
        ->and($statusEvents[0]['client_referenceNo'])->toBe($refA);

    // Filter by Division = '3' (CRPD)
    $divResp = $this->actingAs($admin)->get('/admin/events?division=3');
    $divResp->assertSuccessful();
    $divEvents = $divResp->viewData('page')['props']['events'];
    expect($divEvents)->toHaveCount(1)
        ->and($divEvents[0]['client_referenceNo'])->toBe($refB);

    // Search by venue keyword 'Alpha'
    $searchResp = $this->actingAs($admin)->get('/admin/events?search=Alpha');
    $searchResp->assertSuccessful();
    $searchEvents = $searchResp->viewData('page')['props']['events'];
    expect($searchEvents)->toHaveCount(1)
        ->and($searchEvents[0]['client_referenceNo'])->toBe($refA);
});

it('returns asynchronous JSON data via the /admin/events/data endpoint', function (): void {
    $user = User::factory()->create(['role' => 'Staff']);

    $ref = '20261015-JSON1';
    $id = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $ref,
        'client_name' => 'Async Requester',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $id,
        'client_referenceNo' => $ref,
        'ticket_type' => 'Events',
        'ticket_status' => 'New',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $ref,
        'event_start_date' => '2026-10-25',
        'event_end_date' => '2026-10-26',
        'event_venue' => 'Broadcast Studio',
    ]);

    $response = $this->actingAs($user)->getJson('/admin/events/data?month=2026-10');
    $response->assertSuccessful()
        ->assertJsonStructure([
            'events',
            'kpis' => ['total_events', 'this_month_count', 'ongoing_today_count', 'upcoming_count', 'multi_day_count', 'completed_count'],
            'statusCounts',
        ]);

    $data = $response->json();
    expect($data['events'])->toHaveCount(1)
        ->and($data['events'][0]['client_referenceNo'])->toBe($ref)
        ->and($data['events'][0]['duration_days'])->toBe(2)
        ->and($data['events'][0]['is_multi_day'])->toBeTrue();
});

it('excludes soft-deleted tickets from the events calendar', function (): void {
    $admin = User::factory()->create(['role' => 'Admin']);

    $ref = '20261015-TRASHED_EVT';
    $id = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $ref,
        'client_name' => 'Trashed Event Organizer',
        'deleted_at' => now(),
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $id,
        'client_referenceNo' => $ref,
        'ticket_type' => 'Events',
        'ticket_status' => 'New',
        'deleted_at' => now(),
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $ref,
        'event_start_date' => '2026-10-15',
        'event_venue' => 'Discarded Venue',
    ]);

    $response = $this->actingAs($admin)->get('/admin/events');
    $response->assertSuccessful();

    $events = $response->viewData('page')['props']['events'];
    expect(collect($events)->firstWhere('client_referenceNo', $ref))->toBeNull();
});

it('flags urgent unassigned upcoming events and supports filtering by Unassigned staff', function (): void {
    $admin = User::factory()->create(['role' => 'Admin']);

    $tomorrow = Carbon::today()->addDay()->format('Y-m-d');
    $nextMonth = Carbon::today()->addDays(20)->format('Y-m-d');

    $refUrgent = '20261016-URGENT01';
    $refAssigned = '20261016-ASSIGNED01';
    $refLaterUnassigned = '20261016-LATER01';

    // 1. Urgent unassigned event starting tomorrow
    $id1 = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $refUrgent,
        'client_name' => 'Urgent Event Requester',
        'client_division_agency' => 'FAD',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $id1,
        'client_referenceNo' => $refUrgent,
        'ticket_type' => 'Events',
        'ticket_status' => 'New',
        'ticket_assigned_staff' => null,
        'ticket_attended_by' => null,
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $refUrgent,
        'event_start_date' => $tomorrow,
        'event_end_date' => $tomorrow,
        'event_venue' => 'Atrium Hall',
    ]);

    // 2. Assigned event starting tomorrow (should NOT be flagged unassigned)
    $id2 = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $refAssigned,
        'client_name' => 'Assigned Event Requester',
        'client_division_agency' => 'CRPD',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $id2,
        'client_referenceNo' => $refAssigned,
        'ticket_type' => 'Events',
        'ticket_status' => 'In Progress',
        'ticket_assigned_staff' => 'Lloyd Mandapat',
        'ticket_attended_by' => 'Lloyd Mandapat',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $refAssigned,
        'event_start_date' => $tomorrow,
        'event_end_date' => $tomorrow,
        'event_venue' => 'Cast Room',
    ]);

    // 3. Unassigned event 20 days out (unassigned = true, urgent = false)
    $id3 = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $refLaterUnassigned,
        'client_name' => 'Future Event Requester',
        'client_division_agency' => 'IRAD',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $id3,
        'client_referenceNo' => $refLaterUnassigned,
        'ticket_type' => 'Events',
        'ticket_status' => 'New',
        'ticket_assigned_staff' => null,
        'ticket_attended_by' => null,
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $refLaterUnassigned,
        'event_start_date' => $nextMonth,
        'event_end_date' => $nextMonth,
        'event_venue' => 'Conference Room B',
    ]);

    $resp = $this->actingAs($admin)->getJson('/admin/events/data');
    $resp->assertSuccessful();
    $payload = $resp->json();

    expect($payload['kpis']['unassigned_active_count'])->toBe(2)
        ->and($payload['kpis']['urgent_unassigned_count'])->toBe(1);

    $eventsByRef = collect($payload['events'])->keyBy('client_referenceNo');
    expect($eventsByRef[$refUrgent]['is_unassigned'])->toBeTrue()
        ->and($eventsByRef[$refUrgent]['is_urgent_unassigned'])->toBeTrue()
        ->and($eventsByRef[$refUrgent]['days_until_start'])->toBe(1)
        ->and($eventsByRef[$refAssigned]['is_unassigned'])->toBeFalse()
        ->and($eventsByRef[$refAssigned]['is_urgent_unassigned'])->toBeFalse()
        ->and($eventsByRef[$refLaterUnassigned]['is_unassigned'])->toBeTrue()
        ->and($eventsByRef[$refLaterUnassigned]['is_urgent_unassigned'])->toBeFalse();

    // Filter by staff=Unassigned
    $unassignedResp = $this->actingAs($admin)->getJson('/admin/events/data?staff=Unassigned');
    $unassignedResp->assertSuccessful();
    $unassignedRefs = collect($unassignedResp->json('events'))->pluck('client_referenceNo')->all();
    expect($unassignedRefs)->toContain($refUrgent, $refLaterUnassigned)
        ->and($unassignedRefs)->not->toContain($refAssigned);
});

it('supports inline quick-actions for assigning staff, editing schedule/venue, and completing events via JSON', function (): void {
    $admin = User::factory()->create([
        'name' => 'Admin Dispatcher',
        'role' => 'Admin',
    ]);

    $ref = '20261016-QUICK01';
    $clientId = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $ref,
        'client_name' => 'Quick Action Client',
        'client_division_agency' => 'OD-MISPS',
        'created_at' => now(),
    ]);
    $ticketId = DB::table('v2_ticket_details')->insertGetId([
        'ticket_id' => $clientId,
        'client_referenceNo' => $ref,
        'ticket_type' => 'Events',
        'ticket_nature_of_support' => 'Events',
        'ticket_status' => 'New',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $ref,
        'event_start_date' => '2026-10-18',
        'event_end_date' => '2026-10-18',
        'event_venue' => 'Old Venue',
        'event_remarks' => 'Initial remarks',
    ]);

    // 1. Inline Quick Assign Staff & transition to In Progress
    $assignResp = $this->actingAs($admin)->putJson("/tickets/{$ticketId}", [
        'ticket_assigned_staff' => 'Michael Binondo',
        'ticket_status' => 'In Progress',
    ]);
    $assignResp->assertSuccessful()
        ->assertJson(['success' => true]);

    $detailAfterAssign = DB::table('v2_ticket_details')->where('ticket_id', $ticketId)->first();
    expect($detailAfterAssign->ticket_assigned_staff)->toBe('Michael Binondo')
        ->and($detailAfterAssign->ticket_status)->toBe('In Progress');

    // 2. Inline Quick Schedule & Venue Update
    $scheduleResp = $this->actingAs($admin)->putJson("/tickets/{$ticketId}", [
        'ticket_type' => 'Events',
        'event_start_date' => '2026-10-20',
        'event_end_date' => '2026-10-22',
        'event_venue' => 'DOST-STII Main Auditorium',
        'event_remarks' => 'Updated 3-day hybrid summit',
        'other_request' => '4 HDMI cables and clicker',
    ]);
    $scheduleResp->assertSuccessful()
        ->assertJson(['success' => true]);

    $eventAfterUpdate = DB::table('v2_ticket_events')->where('client_referenceNo', $ref)->first();
    expect(Carbon::parse($eventAfterUpdate->event_start_date)->format('Y-m-d'))->toBe('2026-10-20')
        ->and(Carbon::parse($eventAfterUpdate->event_end_date)->format('Y-m-d'))->toBe('2026-10-22')
        ->and($eventAfterUpdate->event_venue)->toBe('DOST-STII Main Auditorium')
        ->and($eventAfterUpdate->event_remarks)->toBe('Updated 3-day hybrid summit')
        ->and($eventAfterUpdate->other_request)->toBe('4 HDMI cables and clicker');

    // 3. Inline Quick Complete Event via JSON
    $completeResp = $this->actingAs($admin)->putJson("/tickets-complete/{$ticketId}", [
        'json' => true,
        'ticket_status' => 'Completed',
        'ticket_type' => 'Events',
        'ticket_nature_of_support' => 'Events',
        'ticket_support_type' => 'Audio & Visual Setup',
        'ticket_actions_taken' => 'Completed full AV and livestream setup for 3-day summit.',
        'ticket_remarks' => 'All equipment returned in good condition.',
    ]);
    $completeResp->assertSuccessful()
        ->assertJson(['success' => true]);

    $detailAfterComplete = DB::table('v2_ticket_details')->where('ticket_id', $ticketId)->first();
    expect($detailAfterComplete->ticket_status)->toBe('Completed')
        ->and($detailAfterComplete->ticket_actions_taken)->toBe('Completed full AV and livestream setup for 3-day summit.');
});

it('detects venue and staff double-booking conflicts on overlapping dates while ignoring virtual venues', function (): void {
    $admin = User::factory()->create(['role' => 'Admin']);

    $refA = '20261019-CONF01';
    $refB = '20261019-CONF02';
    $refZoom1 = '20261019-ZOOM01';
    $refZoom2 = '20261019-ZOOM02';

    // Event A: Cast Room, Oct 20-21, Assigned to Lloyd Mandapat
    $idA = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $refA,
        'client_name' => 'Organizer Alpha',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $idA,
        'client_referenceNo' => $refA,
        'ticket_type' => 'Events',
        'ticket_status' => 'In Progress',
        'ticket_assigned_staff' => 'Lloyd Mandapat',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $refA,
        'event_start_date' => '2026-10-20',
        'event_end_date' => '2026-10-21',
        'event_venue' => 'Cast Room',
    ]);

    // Event B: Cast Room, Oct 21-22, Assigned to Lloyd Mandapat (Overlaps on Oct 21 in both Venue and Staff)
    $idB = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $refB,
        'client_name' => 'Organizer Beta',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $idB,
        'client_referenceNo' => $refB,
        'ticket_type' => 'Events',
        'ticket_status' => 'New',
        'ticket_assigned_staff' => 'Lloyd Mandapat',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $refB,
        'event_start_date' => '2026-10-21',
        'event_end_date' => '2026-10-22',
        'event_venue' => 'Cast Room',
    ]);

    // Events Zoom 1 & Zoom 2: Same date in 'Zoom / Online' with different staff -> should NOT trigger venue conflict
    $idZ1 = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $refZoom1,
        'client_name' => 'Zoom Host 1',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $idZ1,
        'client_referenceNo' => $refZoom1,
        'ticket_type' => 'Events',
        'ticket_status' => 'New',
        'ticket_assigned_staff' => 'Michael Binondo',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $refZoom1,
        'event_start_date' => '2026-10-25',
        'event_end_date' => '2026-10-25',
        'event_venue' => 'Zoom / Online',
    ]);

    $idZ2 = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $refZoom2,
        'client_name' => 'Zoom Host 2',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $idZ2,
        'client_referenceNo' => $refZoom2,
        'ticket_type' => 'Events',
        'ticket_status' => 'New',
        'ticket_assigned_staff' => 'Chester Francisco',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $refZoom2,
        'event_start_date' => '2026-10-25',
        'event_end_date' => '2026-10-25',
        'event_venue' => 'Zoom / Online',
    ]);

    $resp = $this->actingAs($admin)->getJson('/admin/events/data');
    $resp->assertSuccessful();
    $payload = $resp->json();

    expect($payload['kpis']['conflict_count'])->toBe(2)
        ->and($payload['kpis']['venue_conflict_count'])->toBe(2)
        ->and($payload['kpis']['staff_conflict_count'])->toBe(2);

    $byRef = collect($payload['events'])->keyBy('client_referenceNo');
    expect($byRef[$refA]['has_conflict'])->toBeTrue()
        ->and($byRef[$refA]['has_venue_conflict'])->toBeTrue()
        ->and($byRef[$refA]['has_staff_conflict'])->toBeTrue()
        ->and($byRef[$refA]['venue_conflicts'][0]['client_referenceNo'])->toBe($refB)
        ->and($byRef[$refA]['staff_conflicts'][0]['client_referenceNo'])->toBe($refB)
        ->and($byRef[$refZoom1]['has_venue_conflict'])->toBeFalse()
        ->and($byRef[$refZoom2]['has_venue_conflict'])->toBeFalse();
});

it('filters events by standard venue, Others category, and custom venue text', function (): void {
    $admin = User::factory()->create(['role' => 'Admin']);

    $refStd = '20261019-VENSTD';
    $refCustom1 = '20261019-VENCUST1';
    $refCustom2 = '20261019-VENCUST2';

    // Standard Venue: Training Room
    $id1 = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $refStd,
        'client_name' => 'Training Client',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $id1,
        'client_referenceNo' => $refStd,
        'ticket_type' => 'Events',
        'ticket_status' => 'New',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $refStd,
        'event_start_date' => '2026-10-15',
        'event_venue' => 'Training Room',
    ]);

    // Custom Venue 1: PICC Plenary Hall
    $id2 = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $refCustom1,
        'client_name' => 'External Summit Client',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $id2,
        'client_referenceNo' => $refCustom1,
        'ticket_type' => 'Events',
        'ticket_status' => 'New',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $refCustom1,
        'event_start_date' => '2026-10-16',
        'event_venue' => 'PICC Plenary Hall',
    ]);

    // Custom Venue 2: Acacia Hotel Ballroom
    $id3 = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $refCustom2,
        'client_name' => 'Hotel Event Client',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $id3,
        'client_referenceNo' => $refCustom2,
        'ticket_type' => 'Events',
        'ticket_status' => 'New',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $refCustom2,
        'event_start_date' => '2026-10-17',
        'event_venue' => 'Acacia Hotel Ballroom',
    ]);

    // 1. Filter by standard venue 'Training Room'
    $stdResp = $this->actingAs($admin)->getJson('/admin/events/data?venue=Training+Room');
    $stdResp->assertSuccessful();
    $stdRefs = collect($stdResp->json('events'))->pluck('client_referenceNo')->all();
    expect($stdRefs)->toBe([$refStd]);

    // 2. Filter by 'Others' (without custom_venue text -> returns both custom venues)
    $othersResp = $this->actingAs($admin)->getJson('/admin/events/data?venue=Others');
    $othersResp->assertSuccessful();
    $othersRefs = collect($othersResp->json('events'))->pluck('client_referenceNo')->all();
    expect($othersRefs)->toContain($refCustom1, $refCustom2)
        ->and($othersRefs)->not->toContain($refStd);

    // 3. Filter by 'Others' + custom_venue='PICC'
    $customResp = $this->actingAs($admin)->getJson('/admin/events/data?venue=Others&custom_venue=PICC');
    $customResp->assertSuccessful();
    $customRefs = collect($customResp->json('events'))->pluck('client_referenceNo')->all();
    expect($customRefs)->toBe([$refCustom1]);
});
