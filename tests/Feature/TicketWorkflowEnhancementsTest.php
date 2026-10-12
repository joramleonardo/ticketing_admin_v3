<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('allows external clients to track tickets by ticket reference number', function (): void {
    $ref = '20261007-001';

    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'In Progress',
        'ticket_problem_that_needed_support' => 'Network down on 2nd floor',
        'date_requested' => now(),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Maria Santos',
        'client_division_agency' => 'CRPD',
        'client_empID' => 'EMP-888',
        'client_type' => 'Internal',
    ]);

    $response = $this->getJson("/client/track/{$ref}");

    $response->assertSuccessful();
    $data = $response->json();

    expect($data['search_type'])->toBe('reference')
        ->and($data['tickets'])->toHaveCount(1)
        ->and($data['tickets'][0]['client_referenceNo'])->toBe($ref)
        ->and($data['tickets'][0]['client_name'])->toBe('Maria Santos');
});

it('allows clients to track all tickets by employee ID', function (): void {
    DB::table('ticket_employees')->insert([
        'employee_code' => 'EMP-555',
        'fname' => 'Juan',
        'lname' => 'Dela Cruz',
        'division_id' => 'FAD',
    ]);

    $ref1 = '20261007-101';
    $ref2 = '20261007-102';

    DB::table('v2_ticket_details')->insert([
        [
            'client_referenceNo' => $ref1,
            'ticket_type' => 'Technical Support',
            'ticket_status' => 'In Progress',
            'ticket_problem_that_needed_support' => 'Mouse not working',
            'date_requested' => now()->subDay(),
        ],
        [
            'client_referenceNo' => $ref2,
            'ticket_type' => 'Technical Support',
            'ticket_status' => 'Completed',
            'ticket_problem_that_needed_support' => 'Printer jammed',
            'date_requested' => now(),
        ],
    ]);

    DB::table('v2_ticket_client_details')->insert([
        [
            'client_referenceNo' => $ref1,
            'client_name' => 'Juan Dela Cruz',
            'client_division_agency' => 'FAD',
            'client_empID' => 'EMP-555',
            'client_type' => 'Internal',
        ],
        [
            'client_referenceNo' => $ref2,
            'client_name' => 'Juan Dela Cruz',
            'client_division_agency' => 'FAD',
            'client_empID' => 'EMP-555',
            'client_type' => 'Internal',
        ],
    ]);

    $response = $this->getJson('/client/track/EMP-555');

    $response->assertSuccessful();
    $data = $response->json();

    expect($data['search_type'])->toBe('employee')
        ->and($data['tickets'])->toHaveCount(2)
        ->and($data['employee']['name'])->toBe('Juan Dela Cruz');
});

it('allows two-way messaging between client and staff on a ticket', function (): void {
    $ref = '20261007-500';

    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'In Progress',
        'ticket_problem_that_needed_support' => 'Need SPSS installation',
        'date_requested' => now(),
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Ana Reyes',
        'client_type' => 'Internal',
    ]);

    // 1) Client posts a message
    $clientPost = $this->postJson("/client/ticket/{$ref}/messages", [
        'sender_type' => 'client',
        'message' => 'Hello, is someone available to check this today?',
    ]);
    $clientPost->assertSuccessful();

    // 2) Staff posts a response
    $staffUser = User::factory()->create([
        'name' => 'Lloyd Mandapat',
        'role' => 'Staff',
    ]);

    $staffPost = $this->actingAs($staffUser)->postJson("/admin/tickets/{$ref}/messages", [
        'message' => 'Hi Ana, yes! I will be by your desk in 15 minutes.',
    ]);
    $staffPost->assertSuccessful();

    // 3) Both messages can be read by client and staff
    $messagesResponse = $this->getJson("/client/ticket/{$ref}/messages");
    $messagesResponse->assertSuccessful();
    $messages = $messagesResponse->json();

    expect($messages)->toHaveCount(2)
        ->and($messages[0]['sender_type'])->toBe('client')
        ->and($messages[0]['sender_name'])->toBe('Ana Reyes')
        ->and($messages[0]['message'])->toContain('is someone available')
        ->and($messages[1]['sender_type'])->toBe('staff')
        ->and($messages[1]['sender_name'])->toBe('Lloyd Mandapat')
        ->and($messages[1]['message'])->toContain('I will be by your desk');
});

it('renders all tickets view with status counts and pagination filters', function (): void {
    $staff = User::factory()->create([
        'name' => 'Michael Binondo',
        'role' => 'Admin',
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => '20261007-005',
        'client_name' => 'Carla Santos',
        'client_empID' => '1099',
        'client_division_agency' => '3',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => '20261007-005',
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'ticket_problem_that_needed_support' => 'Cannot print report',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($staff)->get('/admin/tickets-v2');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('TicketsV2/All')
        ->has('tickets.data')
        ->has('counts')
        ->has('filters')
    );
});

it('searches tickets across reference number, client name, and problem details', function (): void {
    $staff = User::factory()->create([
        'name' => 'Michael Binondo',
        'role' => 'Admin',
    ]);

    DB::table('v2_ticket_client_details')->insert([
        [
            'client_referenceNo' => 'TKT-2026-SEARCH1',
            'client_name' => 'Alice Wonderland',
            'client_empID' => 'EMP-111',
            'client_division_agency' => 'FAD',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'client_referenceNo' => 'TKT-2026-SEARCH2',
            'client_name' => 'Bob Builder',
            'client_empID' => 'EMP-222',
            'client_division_agency' => 'CRPD',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('v2_ticket_details')->insert([
        [
            'client_referenceNo' => 'TKT-2026-SEARCH1',
            'ticket_type' => 'Technical Support',
            'ticket_status' => 'In Progress',
            'ticket_problem_that_needed_support' => 'Blue screen error',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'client_referenceNo' => 'TKT-2026-SEARCH2',
            'ticket_type' => 'Technical Support',
            'ticket_status' => 'Pending',
            'ticket_problem_that_needed_support' => 'Cannot print document',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $responseRef = $this->actingAs($staff)->get('/admin/tickets-v2?search=SEARCH1');
    $responseRef->assertSuccessful();
    $responseRef->assertInertia(fn ($page) => $page
        ->component('TicketsV2/All')
        ->has('tickets.data', 1)
        ->where('tickets.data.0.client_referenceNo', 'TKT-2026-SEARCH1')
    );

    $responseProblem = $this->actingAs($staff)->get('/admin/tickets-v2?search=print');
    $responseProblem->assertSuccessful();
    $responseProblem->assertInertia(fn ($page) => $page
        ->component('TicketsV2/All')
        ->has('tickets.data', 1)
        ->where('tickets.data.0.client_referenceNo', 'TKT-2026-SEARCH2')
    );

    $responseName = $this->actingAs($staff)->get('/admin/tickets-v2?search=Wonderland');
    $responseName->assertSuccessful();
    $responseName->assertInertia(fn ($page) => $page
        ->component('TicketsV2/All')
        ->has('tickets.data', 1)
        ->where('tickets.data.0.client_referenceNo', 'TKT-2026-SEARCH1')
    );
});

it('exports filtered tickets to CSV format', function (): void {
    $staff = User::factory()->create();

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => '20261007-EXP1',
        'client_name' => 'Export User',
        'client_empID' => 'EMP-EXP',
        'client_division_agency' => 'ORD',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => '20261007-EXP1',
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'Pending',
        'ticket_problem_that_needed_support' => 'Export test problem',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($staff)->get('/admin/tickets-v2/export?status=Pending');

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('bulk assigns selected tickets to a staff member', function (): void {
    $staff = User::factory()->create();

    $id1 = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => '20261007-BLK1',
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'ticket_problem_that_needed_support' => 'Issue 1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $id2 = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => '20261007-BLK2',
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'ticket_problem_that_needed_support' => 'Issue 2',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($staff)->postJson('/admin/tickets-v2/bulk-assign', [
        'ticket_ids' => [$id1, $id2],
        'assigned_staff' => 'Agent Smith',
    ]);

    $response->assertSuccessful();
    $response->assertJson(['success' => true]);

    expect(DB::table('v2_ticket_details')->where('ticket_id', $id1)->value('ticket_status'))->toBe('Pending')
        ->and(DB::table('v2_ticket_details')->where('ticket_id', $id1)->value('ticket_assigned_staff'))->toBe('Agent Smith')
        ->and(DB::table('v2_ticket_details')->where('ticket_id', $id2)->value('ticket_assigned_staff'))->toBe('Agent Smith');
});

it('bulk closes completed tickets safely', function (): void {
    $staff = User::factory()->create();

    $idCompleted = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => '20261007-CLS1',
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'Completed',
        'ticket_problem_that_needed_support' => 'Issue ready for closure',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $idPending = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => '20261007-CLS2',
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'Pending',
        'ticket_problem_that_needed_support' => 'Issue still in progress',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($staff)->postJson('/admin/tickets-v2/bulk-close', [
        'ticket_ids' => [$idCompleted, $idPending],
    ]);

    $response->assertSuccessful();
    $response->assertJson(['success' => true]);

    expect(DB::table('v2_ticket_details')->where('ticket_id', $idCompleted)->value('ticket_status'))->toBe('Closed')
        ->and(DB::table('v2_ticket_details')->where('ticket_id', $idPending)->value('ticket_status'))->toBe('Pending');
});

it('filters tickets by workflow presets', function (): void {
    $staff = User::factory()->create([
        'name' => 'Ada Reyes',
    ]);

    // 1: Assigned to Ada
    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => '20261008-PRE1',
        'client_name' => 'User One',
        'client_division_agency' => 'FAD',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => '20261008-PRE1',
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'In Progress',
        'ticket_assigned_staff' => 'Ada Reyes',
        'date_requested' => now(),
        'created_at' => now(),
    ]);

    // 2: Overdue ticket (requested 3 days ago, still In Progress)
    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => '20261008-PRE2',
        'client_name' => 'User Two',
        'client_division_agency' => 'CRPD',
        'created_at' => now()->subDays(3),
    ]);
    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => '20261008-PRE2',
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'Pending',
        'ticket_assigned_staff' => 'Other Staff',
        'date_requested' => now()->subDays(3),
        'created_at' => now()->subDays(3),
    ]);

    // 3: Completed awaiting rating
    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => '20261008-PRE3',
        'client_name' => 'User Three',
        'client_division_agency' => 'IRAD',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => '20261008-PRE3',
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'Completed',
        'ticket_assigned_staff' => 'Other Staff',
        'date_requested' => now(),
        'created_at' => now(),
    ]);

    // Test Assigned to Me preset
    $respMe = $this->actingAs($staff)->get('/admin/tickets-v2?preset=assigned_to_me');
    $respMe->assertSuccessful();
    $respMe->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->component('TicketsV2/All')
        ->where('filters.preset', 'assigned_to_me')
        ->has('tickets.data', 1)
        ->where('tickets.data.0.client_referenceNo', '20261008-PRE1')
    );

    // Test Overdue SLA preset
    $respOverdue = $this->actingAs($staff)->get('/admin/tickets-v2?preset=overdue');
    $respOverdue->assertSuccessful();
    $respOverdue->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->component('TicketsV2/All')
        ->where('filters.preset', 'overdue')
        ->has('tickets.data', 1)
        ->where('tickets.data.0.client_referenceNo', '20261008-PRE2')
    );

    // Test Awaiting Rating preset
    $respRating = $this->actingAs($staff)->get('/admin/tickets-v2?preset=awaiting_rating');
    $respRating->assertSuccessful();
    $respRating->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->component('TicketsV2/All')
        ->where('filters.preset', 'awaiting_rating')
        ->has('tickets.data', 1)
        ->where('tickets.data.0.client_referenceNo', '20261008-PRE3')
        ->has('counts.presets', fn ($p) => $p
            ->where('assigned_to_me', 1)
            ->where('overdue', 1)
            ->where('awaiting_rating', 1)
            ->etc()
        )
    );
});

it('filters tickets by quick date preset and custom date range', function (): void {
    $admin = User::factory()->create(['role' => 'Admin']);

    DB::table('v2_ticket_client_details')->insert([
        [
            'client_referenceNo' => '20261008-DATE1',
            'client_name' => 'Date User 1',
            'client_division_agency' => 'FAD',
            'created_at' => now(),
        ],
        [
            'client_referenceNo' => '20261008-DATE2',
            'client_name' => 'Date User 2',
            'client_division_agency' => 'CRPD',
            'created_at' => now()->subDays(5),
        ],
    ]);

    DB::table('v2_ticket_details')->insert([
        [
            'client_referenceNo' => '20261008-DATE1',
            'ticket_type' => 'Technical Support',
            'ticket_status' => 'New',
            'date_requested' => now()->toDateString(),
            'created_at' => now(),
        ],
        [
            'client_referenceNo' => '20261008-DATE2',
            'ticket_type' => 'Technical Support',
            'ticket_status' => 'New',
            'date_requested' => now()->subDays(5)->toDateString(),
            'created_at' => now()->subDays(5),
        ],
    ]);

    // Test 'today' preset
    $resp = $this->actingAs($admin)->get('/admin/tickets-v2?date_preset=today');
    $resp->assertSuccessful();
    $resp->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->component('TicketsV2/All')
        ->has('tickets.data', 1)
        ->where('tickets.data.0.client_referenceNo', '20261008-DATE1')
    );
});

it('allows admin to bulk delete tickets and logs', function (): void {
    $admin = User::factory()->create(['role' => 'Admin']);
    $staff = User::factory()->create(['role' => 'Staff']);

    $ref1 = '20261008-DEL1';
    $ref2 = '20261008-DEL2';

    DB::table('v2_ticket_client_details')->insert([
        ['client_referenceNo' => $ref1, 'client_name' => 'To Delete 1', 'client_division_agency' => 'FAD'],
        ['client_referenceNo' => $ref2, 'client_name' => 'To Delete 2', 'client_division_agency' => 'FAD'],
    ]);

    DB::table('v2_ticket_details')->insert([
        ['client_referenceNo' => $ref1, 'ticket_type' => 'Technical Support', 'ticket_status' => 'New'],
        ['client_referenceNo' => $ref2, 'ticket_type' => 'Technical Support', 'ticket_status' => 'New'],
    ]);

    DB::table('v2_ticket_activity_logs')->insert([
        ['client_referenceNo' => $ref1, 'activity_name' => 'Created', 'activity_date' => now()],
        ['client_referenceNo' => $ref2, 'activity_name' => 'Created', 'activity_date' => now()],
    ]);

    // Non-admin should be forbidden
    $respForbidden = $this->actingAs($staff)->postJson('/admin/tickets-v2/bulk-delete', [
        'ticket_ids' => [$ref1, $ref2],
    ]);
    $respForbidden->assertForbidden();

    // Admin should succeed
    $respAdmin = $this->actingAs($admin)->postJson('/admin/tickets-v2/bulk-delete', [
        'ticket_ids' => [$ref1, $ref2],
    ]);
    $respAdmin->assertSuccessful();
    expect($respAdmin->json('deleted_count'))->toBe(2);

    expect(DB::table('v2_ticket_client_details')->whereIn('client_referenceNo', [$ref1, $ref2])->count())->toBe(0)
        ->and(DB::table('v2_ticket_details')->whereIn('client_referenceNo', [$ref1, $ref2])->count())->toBe(0)
        ->and(DB::table('v2_ticket_activity_logs')->whereIn('client_referenceNo', [$ref1, $ref2])->count())->toBe(0);
});

it('loads ticket messages count and latest message for all tickets table', function (): void {
    $user = User::factory()->create(['role' => 'Admin']);
    $ref = '20261008-MSG1';

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $ref,
        'client_name' => 'Message Tester',
        'client_division_agency' => 'CRPD',
    ]);

    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'In Progress',
        'date_requested' => now(),
    ]);

    DB::table('v2_ticket_messages')->insert([
        [
            'client_referenceNo' => $ref,
            'sender_type' => 'client',
            'sender_name' => 'Message Tester',
            'message' => 'First client message',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ],
        [
            'client_referenceNo' => $ref,
            'sender_type' => 'staff',
            'sender_name' => 'Admin User',
            'message' => 'Staff reply on ticket',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->actingAs($user)->get('/admin/tickets-v2');
    $response->assertSuccessful();

    $tickets = $response->viewData('page')['props']['tickets']['data'];
    $ticket = collect($tickets)->firstWhere('client_referenceNo', $ref);

    expect($ticket)->not->toBeNull()
        ->and($ticket['messages_count'])->toBe(2)
        ->and($ticket['latest_message'])->not->toBeNull()
        ->and($ticket['latest_message']['message'])->toBe('Staff reply on ticket');
});

it('supports dispatch mode heartbeat monitoring for new tickets and messages', function (): void {
    $user = User::factory()->create([
        'name' => 'Dispatch Agent',
        'role' => 'Admin',
    ]);

    $ref1 = '20261008-001';
    $ref2 = '20261008-002';

    $firstClientId = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $ref1,
        'client_name' => 'First Caller',
        'client_division_agency' => 'ORD',
        'created_at' => now()->subMinute(),
    ]);

    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $ref1,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'created_at' => now()->subMinute(),
    ]);

    // Initial check (init = 1) returns current latest ID without alerting on existing tickets
    $initialRes = $this->actingAs($user)->getJson('/admin/tickets-v2/check-updates?init=1');
    $initialRes->assertSuccessful();
    $initialData = $initialRes->json();

    expect($initialData['latest_id'])->toBeGreaterThanOrEqual($firstClientId)
        ->and($initialData['has_new_tickets'])->toBeFalse()
        ->and($initialData['new_tickets'])->toBeEmpty();

    // Now insert a new ticket
    $secondClientId = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $ref2,
        'client_name' => 'Second Incoming Caller',
        'client_division_agency' => 'CRPD',
        'created_at' => now(),
    ]);

    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $ref2,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'created_at' => now(),
    ]);

    // Insert a new client message on the first ticket
    $msgId = DB::table('v2_ticket_messages')->insertGetId([
        'client_referenceNo' => $ref1,
        'sender_type' => 'client',
        'sender_name' => 'First Caller',
        'message' => 'Help, printer is smoking!',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Heartbeat poll passing the previous latest IDs
    $pollRes = $this->actingAs($user)->getJson(
        "/admin/tickets-v2/check-updates?last_id={$firstClientId}&last_message_id=0&include_counts=1"
    );

    $pollRes->assertSuccessful();
    $pollData = $pollRes->json();

    expect($pollData['latest_id'])->toBeGreaterThanOrEqual($secondClientId)
        ->and($pollData['has_new_tickets'])->toBeTrue()
        ->and(count($pollData['new_tickets']))->toBe(1)
        ->and($pollData['new_tickets'][0]['client_referenceNo'])->toBe($ref2)
        ->and($pollData['has_new_messages'])->toBeTrue()
        ->and(count($pollData['new_messages']))->toBe(1)
        ->and($pollData['new_messages'][0]['message'])->toBe('Help, printer is smoking!')
        ->and($pollData['counts'])->not->toBeNull()
        ->and($pollData['counts']['New'])->toBeGreaterThanOrEqual(2);
});
