<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('restricts user management index to Super Admin only', function (): void {
    $staff = User::factory()->create(['role' => 'Staff']);
    $admin = User::factory()->create(['role' => 'Admin']);
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);

    // Unauthenticated
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('login'));

    // Staff forbidden
    $this->actingAs($staff)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    // Regular Admin forbidden
    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    // Super Admin authorized
    $this->actingAs($superAdmin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Users/Index')
            ->has('users.data')
            ->has('summary.total')
            ->has('summary.super_admins')
            ->has('summary.admins')
            ->has('summary.staff')
            ->where('currentUserId', $superAdmin->id)
        );
});

it('allows Super Admin to create a new user with role and status', function (): void {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);

    $payload = [
        'name' => 'Alice Technician',
        'username' => 'atech',
        'email' => 'alice@stii.dost.gov.ph',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'role' => 'Staff',
        'isActivated' => '1',
    ];

    $response = $this->actingAs($superAdmin)
        ->post(route('admin.users.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $created = User::where('username', 'atech')->first();
    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('Alice Technician')
        ->and($created->email)->toBe('alice@stii.dost.gov.ph')
        ->and($created->role)->toBe('Staff')
        ->and($created->isActivated)->toBe('1')
        ->and(Hash::check('SecurePass123!', $created->password))->toBeTrue();
});

it('allows Super Admin to update user role and active status', function (): void {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $user = User::factory()->create([
        'name' => 'Bob Smith',
        'username' => 'bsmith',
        'role' => 'Staff',
        'isActivated' => '1',
    ]);

    $response = $this->actingAs($superAdmin)
        ->put(route('admin.users.update', $user->id), [
            'name' => 'Bob Smith Updated',
            'username' => 'bsmith',
            'email' => 'bsmith@stii.dost.gov.ph',
            'role' => 'Admin', // Promoted to Admin
            'isActivated' => '0', // Deactivated
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $user->refresh();
    expect($user->name)->toBe('Bob Smith Updated')
        ->and($user->role)->toBe('Admin')
        ->and($user->isActivated)->toBe('0');
});

it('prevents Super Admin from demoting or deactivating their own account', function (): void {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);

    // Attempting to change own role
    $response = $this->actingAs($superAdmin)
        ->put(route('admin.users.update', $superAdmin->id), [
            'name' => $superAdmin->name,
            'username' => $superAdmin->username,
            'email' => $superAdmin->email,
            'role' => 'Staff',
            'isActivated' => '1',
        ]);

    $response->assertSessionHasErrors('role');

    // Attempting to deactivate self
    $response2 = $this->actingAs($superAdmin)
        ->put(route('admin.users.update', $superAdmin->id), [
            'name' => $superAdmin->name,
            'username' => $superAdmin->username,
            'email' => $superAdmin->email,
            'role' => 'Super Admin',
            'isActivated' => '0',
        ]);

    $response2->assertSessionHasErrors('isActivated');

    // Attempting to toggle status on self
    $response3 = $this->actingAs($superAdmin)
        ->patch(route('admin.users.toggle-status', $superAdmin->id));

    $response3->assertSessionHasErrors('error');
});

it('allows Super Admin to toggle user status between active and deactivated', function (): void {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $user = User::factory()->create([
        'role' => 'Staff',
        'isActivated' => '1',
    ]);

    // Deactivate
    $this->actingAs($superAdmin)
        ->patch(route('admin.users.toggle-status', $user->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $user->refresh();
    expect($user->isActivated)->toBe('0');

    // Re-activate
    $this->actingAs($superAdmin)
        ->patch(route('admin.users.toggle-status', $user->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $user->refresh();
    expect($user->isActivated)->toBe('1');
});

it('allows Super Admin to delete other users but prevents deleting self', function (): void {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);
    $user = User::factory()->create(['role' => 'Staff']);

    // Attempting to delete self
    $this->actingAs($superAdmin)
        ->delete(route('admin.users.destroy', $superAdmin->id))
        ->assertSessionHasErrors('error');

    expect(User::find($superAdmin->id))->not->toBeNull();

    // Deleting other user
    $this->actingAs($superAdmin)
        ->delete(route('admin.users.destroy', $user->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(User::find($user->id))->toBeNull();
});
