<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', function (string ...$values): string {
        return implode('', $values);
    });

    DB::connection()->getPdo()->sqliteCreateFunction('MONTH', function (string $date): int {
        return (int) date('n', strtotime($date));
    });
});

it('filters dashboard counts by assigned staff', function (): void {
    $user = User::factory()->create();

    createTicket('REF-001', 'Ada Reyes', 'Completed', 'Hardware', 'FAD', 'Excellent');
    createTicket('REF-002', 'Ada Reyes', 'Pending', 'Hardware', 'IRAD');
    createTicket('REF-003', 'Ben Santos', 'Completed', 'Hardware', 'CRPD', 'Good');
    createTicket('REF-004', 'Cara Dela Cruz', 'Pending', 'Hardware', 'FAD', assistedBy: 'Ada Reyes, Ben Santos');
    createTicket('REF-005', 'Ada Reyes', 'Closed', 'Hardware', 'FAD');

    $this->actingAs($user)
        ->get(route('dashboard', [
            'year' => 2026,
            'months' => '1',
            'assigned_staff' => 'Ada Reyes',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('ticketStats.total', 4)
            ->where('ticketStats.pending', 2)
            ->where('ticketStats.completed', 1)
            ->where('ticketStats.closed', 1)
            ->where('filters.assigned_staff', 'Ada Reyes')
            ->where('assignedStaffOptions.0', 'Ada Reyes')
            ->where('assignedStaffOptions.1', 'Ben Santos')
            ->where('assignedStaffOptions.2', 'Cara Dela Cruz')
            ->has('divisionCounts', 2)
            ->has('ratingCounts', 1)
            ->where('ratingCounts.0.rating', 'Excellent')
            ->where('natureCounts.0.nature', 'Hardware')
            ->where('natureCounts.0.total', 4)
            ->has('slaStats')
            ->has('slaStats.compliance_rate')
            ->has('slaStats.avg_tat_formatted')
            ->has('slaStats.active_total')
            ->has('trends')
            ->has('trends.period_label')
            ->has('trends.total_diff_pct')
            ->has('staffWorkload')
            ->where('staffWorkload.0.name', 'Ada Reyes')
            ->has('recentFeedback')
        );
});

it('includes closed tickets as a supported lifecycle status in admin ticket list and counts', function (): void {
    $user = User::factory()->create();

    createTicket('REF-LIST-001', 'Ada Reyes', 'New', 'Hardware', 'FAD');
    createTicket('REF-LIST-002', 'Ada Reyes', 'Completed', 'Hardware', 'FAD');
    createTicket('REF-LIST-003', 'Ada Reyes', 'Closed', 'Hardware', 'FAD');

    $this->actingAs($user)
        ->get(route('tickets-v2.index', ['status' => 'Closed']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('TicketsV2/All')
            ->where('filters.status', 'Closed')
            ->where('counts.All', 3)
            ->where('counts.New', 1)
            ->where('counts.Completed', 1)
            ->where('counts.Closed', 1)
            ->has('tickets.data', 1)
        );
});

it('downloads a dashboard report csv for the selected filters', function (): void {
    $user = User::factory()->create();

    createTicket('REF-CSV-001', 'Ada Reyes', 'Completed', 'Hardware', 'FAD', 'Excellent');
    createTicket('REF-CSV-002', 'Ben Santos', 'Pending', 'Software', 'IRAD');

    $response = $this->actingAs($user)
        ->get(route('dashboard.export', [
            'year' => 2026,
            'months' => '1',
            'assigned_staff' => 'Ada Reyes',
        ]));

    $response->assertOk();
    $response->assertDownload('ticket_report_2026-01.csv');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Reference Code')
        ->toContain('REF-CSV-001')
        ->toContain('2026-01-15')
        ->toContain('Ada Reyes')
        ->toContain('Excellent')
        ->not->toContain('REF-CSV-002');
});

it('delivers role-specific props for Super Admin, Admin, and Staff users', function (): void {
    $superAdmin = User::factory()->create(['name' => 'Super Boss', 'role' => 'Super Admin']);
    $admin = User::factory()->create(['name' => 'Dispatch Admin', 'role' => 'Admin']);
    $staff = User::factory()->create(['name' => 'Ada Reyes', 'role' => 'Staff']);

    createTicket('REF-UNASSIGNED', '', 'New', 'Hardware', 'FAD');
    createTicket('REF-STAFF-01', 'Ada Reyes', 'In Progress', 'Software', 'IRAD');
    createTicket('REF-STAFF-02', 'Ada Reyes', 'Completed', 'Software', 'CRPD', 'Excellent');

    // Super Admin View
    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('userRole', 'Super Admin')
            ->has('ticketStats')
            ->has('adminQueue')
            ->has('staffPersonal')
        );

    // Admin View with Queue Data
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('userRole', 'Admin')
            ->has('adminQueue.unassigned_tickets', 1)
            ->where('adminQueue.unassigned_tickets.0.client_referenceNo', 'REF-UNASSIGNED')
        );

    // Staff View with Personal Stats
    $this->actingAs($staff)
        ->get(route('dashboard', ['year' => 2026, 'months' => '1']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('userRole', 'Staff')
            ->where('staffPersonal.stats.in_progress', 1)
            ->where('staffPersonal.stats.completed', 1)
            ->where('staffPersonal.stats.total', 2)
            ->where('staffPersonal.avg_rating', 5)
            ->where('staffPersonal.urgent_tickets.0.client_referenceNo', 'REF-STAFF-01')
        );
});

function createTicket(
    string $referenceNo,
    string $assignedStaff,
    string $status,
    string $nature,
    string $division,
    ?string $rating = null,
    ?string $assistedBy = null,
): void {
    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $referenceNo,
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => $nature,
        'ticket_support_type' => 'Troubleshooting',
        'ticket_assigned_staff' => $assignedStaff,
        'ticket_assisted_by' => $assistedBy,
        'ticket_status' => $status,
        'date_requested' => '2026-01-15',
        'created_at' => '2026-01-15 08:00:00',
        'updated_at' => '2026-01-15 08:00:00',
    ]);

    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $referenceNo,
        'client_name' => fake()->name(),
        'client_division_agency' => $division,
        'created_at' => '2026-01-15 08:00:00',
        'updated_at' => '2026-01-15 08:00:00',
    ]);

    DB::table('v2_ticket_activity_logs')->insert([
        [
            'client_referenceNo' => $referenceNo,
            'activity_name' => 'Ticket Created',
            'activity_date' => '2026-01-15 08:00:00',
            'activity_user' => $assignedStaff,
        ],
        [
            'client_referenceNo' => $referenceNo,
            'activity_name' => 'Ticket Assigned',
            'activity_date' => '2026-01-15 09:00:00',
            'activity_user' => $assignedStaff,
        ],
        [
            'client_referenceNo' => $referenceNo,
            'activity_name' => 'Ticket Completed',
            'activity_date' => '2026-01-16 10:00:00',
            'activity_user' => $assignedStaff,
        ],
    ]);

    if ($rating === null) {
        return;
    }

    DB::table('v2_ticket_rating_details')->insert([
        'client_referenceNo' => $referenceNo,
        'rating_result' => $rating,
        'created_at' => '2026-01-16 08:00:00',
        'updated_at' => '2026-01-16 08:00:00',
    ]);
}
