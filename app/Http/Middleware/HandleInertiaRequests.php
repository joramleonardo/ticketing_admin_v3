<?php

namespace App\Http\Middleware;

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
            'auth' => ['user' => $request->user()],
            'staffMembers' => fn () => $request->user() ? app(\App\Services\StaffService::class)->getActiveStaffNames() : [],
            'classifications' => fn () => \App\Support\TicketClassification::all(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'message' => fn () => $request->session()->get('message'),
                'refNo' => fn () => $request->session()->get('refNo'),
            ],
        ]);
    }
}
