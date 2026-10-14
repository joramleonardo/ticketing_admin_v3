<?php

namespace App\Services;

use App\Models\TicketEmployee;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
        $hasEmail = Schema::hasColumn('users', 'email');
        $columns = ['id', 'name', 'username', 'role'];
        if ($hasEmail) {
            $columns[] = 'email';
        }

        $staff = User::query()
            ->whereIn('role', ['Staff', 'Admin'])
            ->where('isActivated', '!=', '0')
            ->orderBy('name')
            ->get($columns);

        if (! $hasEmail) {
            foreach ($staff as $user) {
                $user->email = $this->resolveStaffEmail($user->name) ?? $this->resolveStaffEmail($user->username);
            }
        }

        return $staff;
    }

    /**
     * Get unique active staff names, falling back to catalog defaults if empty.
     *
     * @return array<int, string>
     */
    public function getActiveStaffNames(): array
    {
        $names = User::query()
            ->whereIn('role', ['Staff', 'Admin'])
            ->where('isActivated', '!=', '0')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        return ! empty($names) ? $names : self::DEFAULT_STAFF;
    }

    /**
     * Resolve the email address for an IT staff member.
     * Checks User table first (if email column exists), then resolves against ticket_employees.
     */
    public function resolveStaffEmail(?string $staffName): ?string
    {
        if (! $staffName || trim($staffName) === '') {
            return null;
        }

        $trimmed = trim($staffName);

        // 1. If User table has email column and matches
        if (Schema::hasColumn('users', 'email')) {
            $userEmail = User::query()
                ->where('name', $trimmed)
                ->orWhere('username', $trimmed)
                ->value('email');

            if ($userEmail && filter_var(trim($userEmail), FILTER_VALIDATE_EMAIL)) {
                return trim($userEmail);
            }
        }

        // 2. Check if staffName matches a User record to get full name and username candidates
        $user = User::query()
            ->where('name', $trimmed)
            ->orWhere('username', $trimmed)
            ->first();

        $candidates = array_unique(array_filter([
            $trimmed,
            $user?->name,
            $user?->username,
        ]));

        foreach ($candidates as $candidate) {
            // Direct employee_code match
            $employee = TicketEmployee::query()
                ->where('employee_code', $candidate)
                ->first();

            if ($employee && $employee->email && filter_var(trim($employee->email), FILTER_VALIDATE_EMAIL)) {
                return trim($employee->email);
            }

            // Name matching: split by spaces and match first/last name
            $parts = preg_split('/\s+/', $candidate);
            $first = $parts[0] ?? '';
            $last = count($parts) > 1 ? end($parts) : '';

            $employee = TicketEmployee::query()
                ->where(function ($q) use ($first, $last, $candidate) {
                    if ($last !== '') {
                        $q->where('lname', $last)
                            ->where('fname', 'like', "%{$first}%");
                    } else {
                        $q->where('lname', $candidate)
                            ->orWhere('fname', $candidate);
                    }
                })
                ->first();

            if ($employee && $employee->email && filter_var(trim($employee->email), FILTER_VALIDATE_EMAIL)) {
                return trim($employee->email);
            }
        }

        return null;
    }
}
