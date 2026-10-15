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
