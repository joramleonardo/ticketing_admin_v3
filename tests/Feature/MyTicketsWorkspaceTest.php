<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', function (string ...$values): string {
        return implode('', $values);
    });

    DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', function (?string $date, string $format): ?string {
        if (! $date) {
            return null;
        }

        return date('Y-m-d', strtotime($date));
    });
});

it('renders the my tickets workspace with command header stats and triage queues', function (): void {
    $user = User::factory()->create([
        'name' => 'John Technician',
    ]);

    // Ticket 1: Pending (New assigned for John)
    createMyTicket('MY-REF-001', 'John Technician', 'Pending', 'Hardware', 'FAD');

    // Ticket 2: In Progress
    createMyTicket('MY-REF-002', 'John Technician', 'In Progress', 'Software', 'IRAD');

    // Ticket 3: Completed without rating
    createMyTicket('MY-REF-003', 'John Technician', 'Completed', 'Hardware', 'CRPD', dateCompleted: '2026-01-20 14:00:00');

    // Ticket 4: Assigned to someone else
    createMyTicket('MY-REF-004', 'Other Technician', 'Pending', 'Hardware', 'FAD');

    $this->actingAs($user)
        ->get(route('admin.my-tickets'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('TicketsV2/MyTicketsV2')
            ->has('ticketStats')
            ->where('ticketStats.total', 3)
            ->where('ticketStats.pending', 1)
            ->where('ticketStats.in_progress', 1)
            ->where('ticketStats.completed', 1)
            ->has('myNewTickets', 1)
            ->where('myNewTickets.0.client_referenceNo', 'MY-REF-001')
            ->has('myInProgTickets', 1)
            ->where('myInProgTickets.0.client_referenceNo', 'MY-REF-002')
            ->has('noRatingTickets', 1)
            ->where('noRatingTickets.0.client_referenceNo', 'MY-REF-003')
            ->has('tickets.data', 3)
            ->where('ticketStats.presets.awaiting_rating', 1)
        );
});

it('filters tickets by smart workflow presets', function (): void {
    $user = User::factory()->create(['name' => 'John Technician']);

    createMyTicket('PRESET-001', 'John Technician', 'Pending', 'Hardware', 'FAD');
    createMyTicket('PRESET-002', 'John Technician', 'Completed', 'Software', 'IRAD', dateCompleted: '2026-01-20 14:00:00');

    $this->actingAs($user)
        ->get(route('admin.my-tickets', ['preset' => 'awaiting_rating']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.client_referenceNo', 'PRESET-002')
        );
});

it('returns live pending updates in heartbeat check', function (): void {
    $user = User::factory()->create(['name' => 'John Technician']);

    createMyTicket('HB-001', 'John Technician', 'Pending', 'Hardware', 'FAD');
    createMyTicket('HB-002', 'John Technician', 'In Progress', 'Software', 'IRAD');

    $response = $this->actingAs($user)
        ->getJson(route('admin.my-tickets.check-updates'))
        ->assertOk()
        ->json();

    expect($response['pending_count'])->toBe(1)
        ->and($response['in_progress_count'])->toBe(1)
        ->and($response['pending_tickets'][0]['client_referenceNo'])->toBe('HB-001');
});

it('streams csv accomplishment report for the technician', function (): void {
    $user = User::factory()->create(['name' => 'John Technician']);

    createMyTicket('EXP-001', 'John Technician', 'Completed', 'Hardware', 'FAD', dateCompleted: '2026-01-16 10:00:00');

    $response = $this->actingAs($user)
        ->get(route('admin.my-tickets.export'))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');
    $content = $response->streamedContent();
    expect($content)->toContain('Reference Code')
        ->and($content)->toContain('EXP-001');
});

it('filters my tickets and csv export by selected date range', function (): void {
    $user = User::factory()->create(['name' => 'John Technician']);

    createMyTicket('DATE-JAN', 'John Technician', 'Completed', 'Hardware', 'FAD', dateCompleted: '2026-01-16 10:00:00', dateRequested: '2026-01-15');
    createMyTicket('DATE-MAR', 'John Technician', 'Completed', 'Software', 'IRAD', dateCompleted: '2026-03-11 10:00:00', dateRequested: '2026-03-10');

    $this->actingAs($user)
        ->get(route('admin.my-tickets', ['date_from' => '2026-03-01', 'date_to' => '2026-03-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.client_referenceNo', 'DATE-MAR')
            ->where('filters.date_from', '2026-03-01')
            ->where('filters.date_to', '2026-03-31')
        );

    $exportResponse = $this->actingAs($user)
        ->get(route('admin.my-tickets.export', ['date_from' => '2026-03-01', 'date_to' => '2026-03-31']))
        ->assertOk();

    $csvContent = $exportResponse->streamedContent();
    expect($csvContent)->toContain('DATE-MAR')
        ->and($csvContent)->not->toContain('DATE-JAN');
});

function createMyTicket(
    string $referenceNo,
    string $assignedStaff,
    string $status,
    string $nature,
    string $division,
    ?string $dateCompleted = null,
    string $dateRequested = '2026-01-15'
): void {
    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $referenceNo,
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => $nature,
        'ticket_support_type' => 'Troubleshooting',
        'ticket_assigned_staff' => $assignedStaff,
        'ticket_status' => $status,
        'date_requested' => $dateRequested,
        'date_completed' => $dateCompleted,
        'created_at' => $dateRequested.' 08:00:00',
        'updated_at' => $dateRequested.' 08:00:00',
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $referenceNo,
        'client_name' => fake()->name(),
        'client_division_agency' => $division,
        'created_at' => $dateRequested.' 08:00:00',
        'updated_at' => $dateRequested.' 08:00:00',
    ]);
}
