<?php

use App\Models\AppSetting;
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

it('returns notification sound settings and available sounds list for authenticated users', function (): void {
    $staff = User::factory()->create([
        'name' => 'Support Specialist',
        'role' => 'Staff',
    ]);

    $response = $this->actingAs($staff)->getJson(route('admin.settings.sound.show'));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'settings' => ['ticket_sound', 'message_sound', 'volume', 'enabled'],
            'sounds',
            'can_manage',
        ])
        ->assertJson([
            'can_manage' => false,
            'settings' => [
                'ticket_sound' => 'chime',
                'message_sound' => 'pop',
            ],
        ]);
});

it('allows Admin to update notification sound settings', function (): void {
    $admin = User::factory()->create([
        'name' => 'Dispatch Admin',
        'role' => 'Admin',
    ]);

    $payload = [
        'ticket_sound' => 'crystal_bell',
        'message_sound' => 'subtle_ping',
        'volume' => 0.65,
        'enabled' => true,
    ];

    $response = $this->actingAs($admin)->putJson(route('admin.settings.sound.update'), $payload);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Notification sound settings updated successfully.',
            'settings' => [
                'ticket_sound' => 'crystal_bell',
                'message_sound' => 'subtle_ping',
                'volume' => 0.65,
                'enabled' => true,
            ],
        ]);

    expect(AppSetting::get('notification_sound_settings'))->toBe($payload);
});

it('allows Super Admin to update notification sound settings', function (): void {
    $superAdmin = User::factory()->create([
        'name' => 'Chief Admin',
        'role' => 'Super Admin',
    ]);

    $payload = [
        'ticket_sound' => 'marimba',
        'message_sound' => 'radar',
        'volume' => 0.9,
        'enabled' => true,
    ];

    $response = $this->actingAs($superAdmin)->putJson(route('admin.settings.sound.update'), $payload);

    $response->assertSuccessful();
    expect(AppSetting::get('notification_sound_settings'))->toBe($payload);
});

it('forbids Staff from updating notification sound settings', function (): void {
    $staff = User::factory()->create([
        'name' => 'Technician Jane',
        'role' => 'Staff',
    ]);

    $payload = [
        'ticket_sound' => 'elevator',
        'message_sound' => 'pop',
        'volume' => 0.5,
        'enabled' => true,
    ];

    $response = $this->actingAs($staff)->putJson(route('admin.settings.sound.update'), $payload);

    $response->assertForbidden();
});

it('validates sound settings payload options', function (): void {
    $admin = User::factory()->create([
        'role' => 'Admin',
    ]);

    $invalidPayload = [
        'ticket_sound' => 'invalid_horn',
        'message_sound' => 'non_existent_siren',
        'volume' => 5.0, // max is 1.0
        'enabled' => 'not_a_boolean',
    ];

    $response = $this->actingAs($admin)->putJson(route('admin.settings.sound.update'), $invalidPayload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ticket_sound', 'message_sound', 'volume', 'enabled']);
});

it('shares notification sound settings via Inertia middleware', function (): void {
    AppSetting::set('notification_sound_settings', [
        'ticket_sound' => 'radar',
        'message_sound' => 'pop',
        'volume' => 0.75,
        'enabled' => true,
    ]);

    $admin = User::factory()->create([
        'role' => 'Admin',
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('notificationSound', fn (Assert $sound) => $sound
            ->where('ticket_sound', 'radar')
            ->where('message_sound', 'pop')
            ->where('volume', 0.75)
            ->where('enabled', true)
        )
    );
});

it('discovers custom audio files placed in public/sounds and allows selecting them', function (): void {
    $soundsDir = public_path('sounds');
    if (! is_dir($soundsDir)) {
        mkdir($soundsDir, 0755, true);
    }

    $testFile = $soundsDir.'/office_chime.mp3';
    file_put_contents($testFile, 'dummy mp3 content');

    try {
        $admin = User::factory()->create([
            'role' => 'Admin',
        ]);

        $showResponse = $this->actingAs($admin)->getJson(route('admin.settings.sound.show'));
        $showResponse->assertSuccessful();

        $sounds = collect($showResponse->json('sounds'));
        $custom = $sounds->firstWhere('id', 'office_chime.mp3');

        expect($custom)->not->toBeNull()
            ->and($custom['name'])->toBe('Office Chime')
            ->and($custom['is_custom'])->toBeTrue()
            ->and($custom['url'])->toBe('/sounds/office_chime.mp3');

        // Allow saving custom sound
        $payload = [
            'ticket_sound' => 'office_chime.mp3',
            'message_sound' => 'pop',
            'volume' => 0.85,
            'enabled' => true,
        ];

        $updateResponse = $this->actingAs($admin)->putJson(route('admin.settings.sound.update'), $payload);
        $updateResponse->assertSuccessful()
            ->assertJson([
                'settings' => [
                    'ticket_sound' => 'office_chime.mp3',
                ],
            ]);

        expect(AppSetting::get('notification_sound_settings')['ticket_sound'])->toBe('office_chime.mp3');
    } finally {
        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }
});
