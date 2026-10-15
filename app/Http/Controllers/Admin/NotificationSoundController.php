<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationSoundController extends Controller
{
    public const DEFAULT_SETTINGS = [
        'ticket_sound' => 'chime',
        'message_sound' => 'pop',
        'volume' => 0.8,
        'enabled' => true,
    ];

    public const AVAILABLE_SOUNDS = [
        [
            'id' => 'chime',
            'name' => 'Classic Chime',
            'description' => 'Bright 3-tone ascending chime (Slack / Messenger style)',
            'recommended_for' => 'ticket',
        ],
        [
            'id' => 'crystal_bell',
            'name' => 'Crystal Bell',
            'description' => 'Two-tone acoustic bell chime with rich harmonic overtone decay',
            'recommended_for' => 'ticket',
        ],
        [
            'id' => 'radar',
            'name' => 'Radar Pulse',
            'description' => 'Crisp double sonar chirp (modern & high-tech)',
            'recommended_for' => 'both',
        ],
        [
            'id' => 'marimba',
            'name' => 'Marimba Melody',
            'description' => 'Warm 4-note ascending wooden acoustic arpeggio',
            'recommended_for' => 'ticket',
        ],
        [
            'id' => 'digital_beep',
            'name' => 'Digital Pager',
            'description' => 'Crisp double electronic alert beep',
            'recommended_for' => 'ticket',
        ],
        [
            'id' => 'subtle_ping',
            'name' => 'Subtle Glass Ping',
            'description' => 'Soft, unobtrusive high-frequency glass ping',
            'recommended_for' => 'both',
        ],
        [
            'id' => 'elevator',
            'name' => 'Elevator Ding',
            'description' => 'Warm, resonant classic brass arrival bell',
            'recommended_for' => 'ticket',
        ],
        [
            'id' => 'gentle_harp',
            'name' => 'Gentle Harp',
            'description' => 'Soothing 3-note descending melodic glissando',
            'recommended_for' => 'ticket',
        ],
        [
            'id' => 'pop',
            'name' => 'Bubble Pop',
            'description' => 'Playful water droplet pop (ideal for chat messages)',
            'recommended_for' => 'message',
        ],
    ];

    /**
     * Scan public/sounds for custom audio files (.mp3, .wav, .ogg, .m4a).
     *
     * @return array<int, array{id: string, name: string, description: string, recommended_for: string, category: string, url: string, is_custom: bool}>
     */
    public function getCustomAudioFiles(): array
    {
        $soundsPath = public_path('sounds');
        if (! is_dir($soundsPath)) {
            return [];
        }

        $extensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];
        $files = scandir($soundsPath) ?: [];
        $customSounds = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || is_dir($soundsPath.DIRECTORY_SEPARATOR.$file)) {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (! in_array($ext, $extensions, true)) {
                continue;
            }

            $baseName = pathinfo($file, PATHINFO_FILENAME);
            $formattedName = ucwords(str_replace(['_', '-'], ' ', $baseName));

            $customSounds[] = [
                'id' => $file,
                'name' => $formattedName,
                'description' => "Custom {$ext} audio ({$file})",
                'recommended_for' => 'both',
                'category' => 'custom',
                'url' => '/sounds/'.$file,
                'is_custom' => true,
            ];
        }

        return $customSounds;
    }

    /**
     * Get current notification sound configuration and available sounds list.
     */
    public function show(): JsonResponse
    {
        $saved = AppSetting::get('notification_sound_settings', self::DEFAULT_SETTINGS);
        $settings = array_merge(self::DEFAULT_SETTINGS, is_array($saved) ? $saved : []);
        $canManage = in_array(auth()->user()?->role, ['Admin', 'Super Admin'], true);

        $customSounds = $this->getCustomAudioFiles();
        $allSounds = array_merge(self::AVAILABLE_SOUNDS, $customSounds);

        return response()->json([
            'settings' => $settings,
            'sounds' => $allSounds,
            'can_manage' => $canManage,
        ]);
    }

    /**
     * Update notification sound configuration (Restricted to Admin & Super Admin).
     */
    public function update(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! in_array($user?->role, ['Admin', 'Super Admin'], true)) {
            return response()->json([
                'message' => 'Unauthorized action. Only administrators can update notification sound settings.',
            ], 403);
        }

        $validIds = array_merge(
            array_column(self::AVAILABLE_SOUNDS, 'id'),
            array_column($this->getCustomAudioFiles(), 'id')
        );

        $validated = $request->validate([
            'ticket_sound' => ['required', 'string', 'max:255', Rule::in($validIds)],
            'message_sound' => ['required', 'string', 'max:255', Rule::in($validIds)],
            'volume' => ['required', 'numeric', 'min:0.05', 'max:1.0'],
            'enabled' => ['required', 'boolean'],
        ]);

        AppSetting::set('notification_sound_settings', $validated);

        return response()->json([
            'message' => 'Notification sound settings updated successfully.',
            'settings' => $validated,
        ]);
    }
}
