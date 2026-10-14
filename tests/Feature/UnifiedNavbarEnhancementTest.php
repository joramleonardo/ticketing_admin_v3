<?php

use App\Models\TicketDetail;
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

it('shares live unassigned badge count to Admin and Super Admin via Inertia middleware', function (): void {
    $admin = User::factory()->create([
        'name' => 'Dispatch Admin',
        'role' => 'Admin',
    ]);

    // Unassigned tickets
    TicketDetail::create([
        'client_referenceNo' => 'REF-UNASSIGNED-1',
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => 'Hardware',
        'ticket_support_type' => 'Troubleshooting',
        'ticket_assigned_staff' => null,
        'ticket_status' => 'New',
        'date_requested' => now()->toDateString(),
    ]);

    TicketDetail::create([
        'client_referenceNo' => 'REF-UNASSIGNED-2',
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => 'Hardware',
        'ticket_support_type' => 'Troubleshooting',
        'ticket_assigned_staff' => '',
        'ticket_status' => 'Pending',
        'date_requested' => now()->toDateString(),
    ]);

    // Assigned and completed tickets (should not count as unassigned)
    TicketDetail::create([
        'client_referenceNo' => 'REF-ASSIGNED-1',
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => 'Software',
        'ticket_support_type' => 'Troubleshooting',
        'ticket_assigned_staff' => 'John Tech',
        'ticket_status' => 'In Progress',
        'date_requested' => now()->toDateString(),
    ]);

    TicketDetail::create([
        'client_referenceNo' => 'REF-COMPLETED-1',
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => 'Software',
        'ticket_support_type' => 'Troubleshooting',
        'ticket_assigned_staff' => null,
        'ticket_status' => 'Completed',
        'date_requested' => now()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', 'Admin')
            ->where('auth.badges.unassigned', 2)
            ->where('auth.badges.my_active', 0)
        );
});

it('shares live active tickets count to Staff specialist via Inertia middleware', function (): void {
    $staff = User::factory()->create([
        'name' => 'Jane Specialist',
        'role' => 'Staff',
    ]);

    // Active tickets for Jane
    TicketDetail::create([
        'client_referenceNo' => 'REF-STAFF-1',
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => 'Hardware',
        'ticket_support_type' => 'Troubleshooting',
        'ticket_assigned_staff' => 'Jane Specialist',
        'ticket_status' => 'Pending',
        'date_requested' => now()->toDateString(),
    ]);

    TicketDetail::create([
        'client_referenceNo' => 'REF-STAFF-2',
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => 'Software',
        'ticket_support_type' => 'Troubleshooting',
        'ticket_assigned_staff' => 'Jane Specialist',
        'ticket_status' => 'In Progress',
        'date_requested' => now()->toDateString(),
    ]);

    // Completed ticket for Jane (not active)
    TicketDetail::create([
        'client_referenceNo' => 'REF-STAFF-3',
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => 'Hardware',
        'ticket_support_type' => 'Troubleshooting',
        'ticket_assigned_staff' => 'Jane Specialist',
        'ticket_status' => 'Completed',
        'date_requested' => now()->toDateString(),
    ]);

    // Ticket for someone else
    TicketDetail::create([
        'client_referenceNo' => 'REF-OTHER-1',
        'ticket_type' => 'Technical Support',
        'ticket_nature_of_support' => 'Hardware',
        'ticket_support_type' => 'Troubleshooting',
        'ticket_assigned_staff' => 'Other Staff',
        'ticket_status' => 'Pending',
        'date_requested' => now()->toDateString(),
    ]);

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', 'Staff')
            ->where('auth.badges.my_active', 2)
            ->where('auth.badges.unassigned', 0)
        );
});
