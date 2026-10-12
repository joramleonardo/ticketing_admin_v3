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

    $service = app(StaffService::class);
    $staff = $service->getActiveStaff();
    $names = $service->getActiveStaffNames();

    expect($names)->toContain('Active Staff One')
        ->and($names)->not->toContain('Deactivated Staff')
        ->and($names)->not->toContain('Regular Client');
});

it('allows authenticated users to fetch staff members via endpoint', function (): void {
    $user = User::factory()->create(['role' => 'Staff']);

    $response = $this->actingAs($user)->getJson('/admin/staff-members');

    $response->assertSuccessful()
        ->assertJsonFragment(['name' => $user->name]);
});

it('redirects legacy all-tickets route to tickets-v2 index', function (): void {
    $user = User::factory()->create(['role' => 'Staff']);

    $response = $this->actingAs($user)->get('/all-tickets');

    $response->assertRedirect('/admin/tickets-v2');
});
