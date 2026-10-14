<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('staff users are redirected to my-tickets after login', function () {
    $user = User::factory()->create(['role' => 'Staff']);

    $response = $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.my-tickets', absolute: false));
});

test('admin users are redirected to all tickets after login', function () {
    $admin = User::factory()->create(['role' => 'Admin']);

    $response = $this->post('/login', [
        'username' => $admin->username,
        'password' => 'password',
    ]);
    $this->assertAuthenticatedAs($admin);
    $response->assertRedirect(route('tickets-v2.index', absolute: false));
});

test('super admin users are redirected to dashboard after login', function () {
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);

    $response = $this->post('/login', [
        'username' => $superAdmin->username,
        'password' => 'password',
    ]);
    $this->assertAuthenticatedAs($superAdmin);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('deactivated users cannot authenticate and receive error message', function () {
    $user = User::factory()->create([
        'username' => 'inactive_user',
        'isActivated' => '0',
    ]);

    $response = $this->post('/login', [
        'username' => 'inactive_user',
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors([
        'username' => 'Your account has been deactivated. Please contact an administrator.',
    ]);
});

test('active session is invalidated when user is deactivated by middleware', function () {
    $user = User::factory()->create([
        'role' => 'Staff',
        'isActivated' => '0',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors([
        'username' => 'Your account has been deactivated. Please contact an administrator.',
    ]);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'username' => $user->username,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('authenticated root visitors are redirected based on role', function () {
    $staff = User::factory()->create(['role' => 'Staff']);
    $admin = User::factory()->create(['role' => 'Admin']);
    $superAdmin = User::factory()->create(['role' => 'Super Admin']);

    $this->actingAs($staff)->get('/')
        ->assertRedirect(route('admin.my-tickets'));

    $this->actingAs($admin)->get('/')
        ->assertRedirect(route('tickets-v2.index'));

    $this->actingAs($superAdmin)->get('/')
        ->assertRedirect(route('dashboard'));
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
