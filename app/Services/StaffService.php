<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class StaffService
{
    /**
     * Default fallback staff names list.
     *
     * @var array<int, string>
     */
    public const DEFAULT_STAFF = [
        'Aerone Dominhick Sebastian',
        'Etienne Wayne Amparado',
        'Mc Gyver Basaya',
        'Michael Binondo',
        'John Michael Cagadas',
        'Chester Francisco',
        'Joram Kate Leonardo',
        'Lloyd Mandapat',
        'Jomar Rabanera',
        'Mark Jayson Sison',
    ];

    /**
     * Get active staff user records.
     *
     * @return Collection<int, User>
     */
    public function getActiveStaff(): Collection
    {
        return User::query()
            ->whereIn('role', ['Staff', 'Admin', 'Super Admin'])
            ->where('isActivated', '!=', '0')
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'role', 'email']);
    }

    /**
     * Get unique active staff names, falling back to catalog defaults if empty.
     *
     * @return array<int, string>
     */
    public function getActiveStaffNames(): array
    {
        $names = User::query()
            ->whereIn('role', ['Staff', 'Admin', 'Super Admin'])
            ->where('isActivated', '!=', '0')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        return ! empty($names) ? $names : self::DEFAULT_STAFF;
    }
}
