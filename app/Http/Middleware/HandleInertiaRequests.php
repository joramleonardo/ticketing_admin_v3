<?php

namespace App\Http\Middleware;

use App\Models\TicketDetail;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user(),
                'badges' => fn () => $this->getBadgeCounts($request->user()),
            ],
            'staffMembers' => fn () => $request->user() ? app(\App\Services\StaffService::class)->getActiveStaffNames() : [],
            'classifications' => fn () => \App\Support\TicketClassification::all(),
            'notificationSound' => fn () => $request->user() ? \App\Models\AppSetting::get('notification_sound_settings', \App\Http\Controllers\Admin\NotificationSoundController::DEFAULT_SETTINGS) : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'message' => fn () => $request->session()->get('message'),
                'refNo' => fn () => $request->session()->get('refNo'),
            ],
        ]);
    }

    /**
     * Compute live badge counts based on authenticated user's role.
     *
     * @return array{unassigned: int, my_active: int}
     */
    protected function getBadgeCounts(?User $user): array
    {
        if (! $user) {
            return [
                'unassigned' => 0,
                'my_active' => 0,
            ];
        }

        try {
            $myActive = 0;
            if ($user->role !== 'Super Admin' && ! empty($user->name)) {
                $myActive = TicketDetail::query()
                    ->where('ticket_assigned_staff', $user->name)
                    ->whereIn('ticket_status', ['New', 'Pending', 'In Progress'])
                    ->count();
            }

            $unassigned = 0;
            if ($user->role === 'Admin' || $user->role === 'Super Admin') {
                $unassigned = TicketDetail::query()
                    ->whereNotIn('ticket_status', ['Closed', 'Cancelled', 'Completed'])
                    ->where(function ($q) {
                        $q->where('ticket_status', 'New')
                            ->orWhereNull('ticket_assigned_staff')
                            ->orWhereRaw("TRIM(ticket_assigned_staff) = ''");
                    })
                    ->count();
            }

            return [
                'unassigned' => $unassigned,
                'my_active' => $myActive,
            ];
        } catch (\Throwable) {
            return [
                'unassigned' => 0,
                'my_active' => 0,
            ];
        }
    }
}
