<?php

use App\Models\User;
use App\Services\StaffService;
use App\Support\TicketClassification;

it('provides centralized ticket classifications', function (): void {
    $classifications = TicketClassification::all();

    expect($classifications)->toHaveKeys([
        'ticket_types',
        'nature_of_support',
        'subcategories',
        'related_types',
        'remarks_options',
        'divisions',
    ]);

    expect($classifications['ticket_types'])->toContain('Technical Support', 'Livestream', 'Events', 'Others');
    expect(TicketClassification::mapDivision('1'))->toBe('FAD');
});

it('exposes ticket classifications via json endpoint', function (): void {
    $response = $this->getJson('/ticket-classifications');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'ticket_types',
            'nature_of_support',
            'subcategories',
            'related_types',
            'remarks_options',
            'divisions',
        ]);
});

it('dynamically queries active staff members via StaffService', function (): void {
    User::factory()->create([
        'name' => 'Active Staff One',
        'role' => 'Staff',
        'isActivated' => '1',
    ]);

    User::factory()->create([
        'name' => 'Deactivated Staff',
        'role' => 'Staff',
        'isActivated' => '0',
    ]);

    User::factory()->create([
        'name' => 'Regular Client',
        'role' => 'Client',
        'isActivated' => '1',
    ]);

    User::factory()->create([
        'name' => 'Executive Director',
        'role' => 'Super Admin',
        'isActivated' => '1',
    ]);

    $service = app(StaffService::class);
    $staff = $service->getActiveStaff();
    $names = $service->getActiveStaffNames();

    expect($names)->toContain('Active Staff One')
        ->and($names)->not->toContain('Deactivated Staff')
        ->and($names)->not->toContain('Regular Client')
        ->and($names)->not->toContain('Executive Director');
});

it('allows authenticated users to fetch staff members via endpoint', function (): void {
    $user = User::factory()->create(['role' => 'Staff']);

    $response = $this->actingAs($user)->getJson('/admin/staff-members');

    $response->assertSuccessful()
        ->assertJsonFragment(['name' => $user->name]);
});

it('allows only admin to assign or reassign tickets via update endpoint', function (): void {
    $staff = User::factory()->create(['role' => 'Staff']);
    $admin = User::factory()->create(['role' => 'Admin']);

    $detailId = DB::table('v2_ticket_details')->insertGetId([
        'client_referenceNo' => '20261013-ASGN1',
        'ticket_type' => 'Technical Support',
        'ticket_status' => 'New',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Staff attempt to assign staff should be forbidden (403)
    $forbiddenResponse = $this->actingAs($staff)->putJson("/tickets/{$detailId}", [
        'ticket_assigned_staff' => 'Chester Francisco',
    ]);
    $forbiddenResponse->assertForbidden();

    // Admin attempt to assign staff should succeed
    $response = $this->actingAs($admin)->putJson("/tickets/{$detailId}", [
        'ticket_assigned_staff' => 'Chester Francisco',
    ]);
    $response->assertSuccessful();

    expect(DB::table('v2_ticket_details')->where('ticket_id', $detailId)->value('ticket_assigned_staff'))
        ->toBe('Chester Francisco');
});

it('redirects legacy all-tickets route to tickets-v2 index', function (): void {
    $user = User::factory()->create(['role' => 'Staff']);

    $response = $this->actingAs($user)->get('/all-tickets');

    $response->assertRedirect('/admin/tickets-v2');
});
