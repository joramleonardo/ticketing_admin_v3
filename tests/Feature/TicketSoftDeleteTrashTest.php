<?php

use App\Models\TicketClientDetail;
use App\Models\TicketDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

it('moves an active ticket to trash and logs activity without destroying child relations', function (): void {
    $admin = User::factory()->create([
        'name' => 'Admin Jane',
        'role' => 'Admin',
    ]);

    $ref = '20261015-TRASH1';

    $clientId = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $ref,
        'client_name' => 'Active Client',
        'client_division_agency' => 'CRPD',
        'created_at' => now(),
    ]);

    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $clientId,
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'In Progress',
        'date_requested' => now(),
        'created_at' => now(),
    ]);

    DB::table('v2_ticket_messages')->insert([
        'client_referenceNo' => $ref,
        'sender_type' => 'client',
        'sender_name' => 'Active Client',
        'message' => 'Need help with system',
    ]);

    DB::table('v2_ticket_events')->insert([
        'client_referenceNo' => $ref,
        'event_start_date' => now()->toDateString(),
        'event_venue' => 'Conference Room A',
    ]);

    // Send delete request
    $response = $this->actingAs($admin)->deleteJson("/admin/tickets-v2/{$clientId}");

    $response->assertSuccessful()
        ->assertJsonPath('success', true);
    expect($response->json('message'))->toContain('moved to trash');

    // Assert soft-deleted
    expect(TicketClientDetail::find($clientId))->toBeNull()
        ->and(TicketClientDetail::onlyTrashed()->where('client_id', $clientId)->exists())->toBeTrue()
        ->and(TicketDetail::where('client_referenceNo', $ref)->exists())->toBeFalse()
        ->and(TicketDetail::onlyTrashed()->where('client_referenceNo', $ref)->exists())->toBeTrue()
        // Child relations remain intact
        ->and(DB::table('v2_ticket_messages')->where('client_referenceNo', $ref)->exists())->toBeTrue()
        ->and(DB::table('v2_ticket_events')->where('client_referenceNo', $ref)->exists())->toBeTrue();

    // Verify activity log
    $log = DB::table('v2_ticket_activity_logs')
        ->where('client_referenceNo', $ref)
        ->where('activity_name', 'Ticket Moved to Trash')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->activity_user)->toBe('Admin Jane');
});

it('hides trashed tickets from active views and counts, but shows them in the Trash tab', function (): void {
    $admin = User::factory()->create(['role' => 'Admin']);

    $activeRef = '20261015-ACT1';
    $trashedRef = '20261015-TRASH2';

    // Active ticket
    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $activeRef,
        'client_name' => 'Active User',
        'client_division_agency' => 'FAD',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $activeRef,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'date_requested' => now(),
        'created_at' => now(),
    ]);

    // Trashed ticket
    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $trashedRef,
        'client_name' => 'Trashed User',
        'client_division_agency' => 'CRPD',
        'created_at' => now(),
        'deleted_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $trashedRef,
        'ticket_type' => 'Livestream',
        'ticket_status' => 'In Progress',
        'date_requested' => now(),
        'created_at' => now(),
        'deleted_at' => now(),
    ]);

    // Active listing check
    $respActive = $this->actingAs($admin)->get('/admin/tickets-v2');
    $respActive->assertSuccessful();
    $respActive->assertInertia(fn (AssertableInertia $page) => $page
        ->component('TicketsV2/All')
        ->has('tickets.data', 1)
        ->where('tickets.data.0.client_referenceNo', $activeRef)
        ->where('counts.Trash', 1)
        ->where('counts.New', 1)
    );

    // Trash listing check
    $respTrash = $this->actingAs($admin)->get('/admin/tickets-v2?status=Trash');
    $respTrash->assertSuccessful();
    $respTrash->assertInertia(fn (AssertableInertia $page) => $page
        ->component('TicketsV2/All')
        ->where('filters.status', 'Trash')
        ->has('tickets.data', 1)
        ->where('tickets.data.0.client_referenceNo', $trashedRef)
        ->where('counts.Trash', 1)
    );
});

it('can restore a soft-deleted ticket back to the active queue', function (): void {
    $admin = User::factory()->create([
        'name' => 'Admin Restorer',
        'role' => 'Admin',
    ]);

    $ref = '20261015-RESTORE1';

    $clientId = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $ref,
        'client_name' => 'Restorable Client',
        'client_division_agency' => 'IRAD',
        'created_at' => now(),
        'deleted_at' => now(),
    ]);

    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $clientId,
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'Pending',
        'date_requested' => now(),
        'created_at' => now(),
        'deleted_at' => now(),
    ]);

    $response = $this->actingAs($admin)->postJson("/admin/tickets-v2/{$clientId}/restore");

    $response->assertSuccessful()
        ->assertJsonPath('success', true);
    expect($response->json('message'))->toContain('restored from trash');

    // Assert restored
    expect(TicketClientDetail::find($clientId))->not->toBeNull()
        ->and(TicketClientDetail::find($clientId)->deleted_at)->toBeNull()
        ->and(TicketDetail::where('client_referenceNo', $ref)->exists())->toBeTrue()
        ->and(TicketDetail::where('client_referenceNo', $ref)->first()->deleted_at)->toBeNull();

    // Verify activity log
    $log = DB::table('v2_ticket_activity_logs')
        ->where('client_referenceNo', $ref)
        ->where('activity_name', 'Ticket Restored from Trash')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->activity_user)->toBe('Admin Restorer');
});

it('can bulk restore trashed tickets', function (): void {
    $admin = User::factory()->create(['role' => 'Admin']);

    $ref1 = '20261015-BREST1';
    $ref2 = '20261015-BREST2';

    DB::table('v2_ticket_client_details')->insert([
        ['client_referenceNo' => $ref1, 'client_name' => 'User One', 'deleted_at' => now()],
        ['client_referenceNo' => $ref2, 'client_name' => 'User Two', 'deleted_at' => now()],
    ]);

    DB::table('v2_ticket_details')->insert([
        ['client_referenceNo' => $ref1, 'ticket_type' => 'Technical Support', 'ticket_status' => 'New', 'deleted_at' => now()],
        ['client_referenceNo' => $ref2, 'ticket_type' => 'Livestream', 'ticket_status' => 'In Progress', 'deleted_at' => now()],
    ]);

    $response = $this->actingAs($admin)->postJson('/admin/tickets-v2/bulk-restore', [
        'ticket_ids' => [$ref1, $ref2],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('restored_count', 2);

    expect(TicketClientDetail::whereIn('client_referenceNo', [$ref1, $ref2])->count())->toBe(2)
        ->and(TicketDetail::whereIn('client_referenceNo', [$ref1, $ref2])->count())->toBe(2);
});

it('can permanently force delete a single trashed ticket and all child relations', function (): void {
    $admin = User::factory()->create(['role' => 'Admin']);

    $ref = '20261015-FORCE1';

    $clientId = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $ref,
        'client_name' => 'To Purge',
        'deleted_at' => now(),
    ]);

    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $clientId,
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'Completed',
        'deleted_at' => now(),
    ]);

    DB::table('v2_ticket_messages')->insert([
        'client_referenceNo' => $ref,
        'sender_type' => 'client',
        'sender_name' => 'To Purge',
        'message' => 'Purge me',
    ]);

    $response = $this->actingAs($admin)->deleteJson("/admin/tickets-v2/{$clientId}/force");

    $response->assertSuccessful()
        ->assertJsonPath('success', true);
    expect($response->json('deleted_count'))->toBeGreaterThanOrEqual(1);

    expect(DB::table('v2_ticket_client_details')->where('client_id', $clientId)->exists())->toBeFalse()
        ->and(DB::table('v2_ticket_details')->where('client_referenceNo', $ref)->exists())->toBeFalse()
        ->and(DB::table('v2_ticket_messages')->where('client_referenceNo', $ref)->exists())->toBeFalse();
});

it('can bulk permanently force delete trashed tickets', function (): void {
    $admin = User::factory()->create(['role' => 'Admin']);

    $ref1 = '20261015-BFORCE1';
    $ref2 = '20261015-BFORCE2';

    DB::table('v2_ticket_client_details')->insert([
        ['client_referenceNo' => $ref1, 'client_name' => 'Purge 1', 'deleted_at' => now()],
        ['client_referenceNo' => $ref2, 'client_name' => 'Purge 2', 'deleted_at' => now()],
    ]);

    DB::table('v2_ticket_details')->insert([
        ['client_referenceNo' => $ref1, 'ticket_type' => 'Technical Support', 'ticket_status' => 'New', 'deleted_at' => now()],
        ['client_referenceNo' => $ref2, 'ticket_type' => 'Livestream', 'ticket_status' => 'New', 'deleted_at' => now()],
    ]);

    $response = $this->actingAs($admin)->postJson('/admin/tickets-v2/bulk-force-delete', [
        'ticket_ids' => [$ref1, $ref2],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('deleted_count', 2);

    expect(DB::table('v2_ticket_client_details')->whereIn('client_referenceNo', [$ref1, $ref2])->exists())->toBeFalse()
        ->and(DB::table('v2_ticket_details')->whereIn('client_referenceNo', [$ref1, $ref2])->exists())->toBeFalse();
});

it('can empty the entire trash bin without affecting active tickets', function (): void {
    $admin = User::factory()->create(['role' => 'Admin']);

    $activeRef = '20261015-STAY_ACTIVE';
    $trash1 = '20261015-PURGE_T1';
    $trash2 = '20261015-PURGE_T2';

    // Active
    DB::table('v2_ticket_client_details')->insert([
        'client_referenceNo' => $activeRef,
        'client_name' => 'Keep Me Safe',
        'created_at' => now(),
    ]);
    DB::table('v2_ticket_details')->insert([
        'client_referenceNo' => $activeRef,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'In Progress',
        'created_at' => now(),
    ]);

    // Trashed
    DB::table('v2_ticket_client_details')->insert([
        ['client_referenceNo' => $trash1, 'client_name' => 'Trash 1', 'deleted_at' => now()],
        ['client_referenceNo' => $trash2, 'client_name' => 'Trash 2', 'deleted_at' => now()],
    ]);
    DB::table('v2_ticket_details')->insert([
        ['client_referenceNo' => $trash1, 'ticket_type' => 'Technical Support', 'ticket_status' => 'Closed', 'deleted_at' => now()],
        ['client_referenceNo' => $trash2, 'ticket_type' => 'Technical Support', 'ticket_status' => 'Cancelled', 'deleted_at' => now()],
    ]);

    $response = $this->actingAs($admin)->postJson('/admin/tickets-v2/empty-trash');

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('deleted_count', 2);

    // Active ticket is completely intact
    expect(TicketClientDetail::where('client_referenceNo', $activeRef)->exists())->toBeTrue()
        ->and(TicketDetail::where('client_referenceNo', $activeRef)->exists())->toBeTrue()
        // Trashed tickets are permanently purged
        ->and(DB::table('v2_ticket_client_details')->whereIn('client_referenceNo', [$trash1, $trash2])->exists())->toBeFalse()
        ->and(DB::table('v2_ticket_details')->whereIn('client_referenceNo', [$trash1, $trash2])->exists())->toBeFalse()
        ->and(TicketClientDetail::onlyTrashed()->count())->toBe(0);
});

it('forbids staff from restoring or force deleting tickets', function (): void {
    $staff = User::factory()->create([
        'role' => 'Staff',
    ]);

    $ref = '20261015-STAFF_FORBIDDEN';

    $clientId = DB::table('v2_ticket_client_details')->insertGetId([
        'client_referenceNo' => $ref,
        'client_name' => 'Protected Trashed Ticket',
        'deleted_at' => now(),
    ]);

    DB::table('v2_ticket_details')->insert([
        'ticket_id' => $clientId,
        'client_referenceNo' => $ref,
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'deleted_at' => now(),
    ]);

    // Staff cannot restore
    $this->actingAs($staff)->postJson("/admin/tickets-v2/{$clientId}/restore")
        ->assertForbidden();

    // Staff cannot force delete
    $this->actingAs($staff)->deleteJson("/admin/tickets-v2/{$clientId}/force")
        ->assertForbidden();

    // Staff cannot empty trash
    $this->actingAs($staff)->postJson('/admin/tickets-v2/empty-trash')
        ->assertForbidden();

    // Staff cannot bulk restore
    $this->actingAs($staff)->postJson('/admin/tickets-v2/bulk-restore', ['ticket_ids' => [$ref]])
        ->assertForbidden();

    // Staff cannot bulk force delete
    $this->actingAs($staff)->postJson('/admin/tickets-v2/bulk-force-delete', ['ticket_ids' => [$ref]])
        ->assertForbidden();
});
