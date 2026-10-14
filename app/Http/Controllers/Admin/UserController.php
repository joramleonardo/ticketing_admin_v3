<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\TicketActivityLog;
use App\Models\TicketDetail;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private function authorizeSuperAdmin(): void
    {
        abort_unless(
            Auth::check() && Auth::user()->role === 'Super Admin',
            403,
            'Unauthorized. Super Administrator privileges required.'
        );
    }

    /**
     * Display a listing of the users with their roles and assignment workloads.
     */
    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin();

        $search = trim((string) $request->input('search', ''));
        $role = trim((string) $request->input('role', ''));
        $status = trim((string) $request->input('status', ''));

        // Query Users
        $usersQuery = User::query()
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== '' && in_array($role, ['Super Admin', 'Admin', 'Staff'], true), function ($q) use ($role): void {
                $q->where('role', $role);
            })
            ->when($status === 'active', function ($q): void {
                $q->where('isActivated', '!=', '0');
            })
            ->when($status === 'inactive', function ($q): void {
                $q->where('isActivated', '0');
            })
            ->orderBy('name', 'asc');

        $users = $usersQuery->paginate(15)->withQueryString();

        // Query active ticket workloads grouped by staff name
        $activeWorkloads = DB::table('v2_ticket_details')
            ->whereIn('ticket_status', ['New', 'Pending', 'In Progress'])
            ->whereNotNull('ticket_assigned_staff')
            ->whereRaw("TRIM(ticket_assigned_staff) != ''")
            ->selectRaw('TRIM(ticket_assigned_staff) as staff_name, COUNT(*) as open_count')
            ->groupBy(DB::raw('TRIM(ticket_assigned_staff)'))
            ->pluck('open_count', 'staff_name')
            ->all();

        // Attach workload to paginated users
        $users->getCollection()->transform(function (User $user) use ($activeWorkloads): array {
            $staffName = trim((string) $user->name);
            $openTickets = $activeWorkloads[$staffName] ?? 0;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role ?? 'Staff',
                'isActivated' => $user->isActivated !== '0',
                'open_tickets_count' => $openTickets,
                'created_at' => $user->created_at?->toISOString(),
            ];
        });

        // Summary counts
        $summary = [
            'total' => User::count(),
            'super_admins' => User::where('role', 'Super Admin')->count(),
            'admins' => User::where('role', 'Admin')->count(),
            'staff' => User::where('role', 'Staff')->count(),
            'active' => User::where('isActivated', '!=', '0')->count(),
            'inactive' => User::where('isActivated', '0')->count(),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'role' => $role,
                'status' => $status,
            ],
            'currentUserId' => Auth::id(),
            'staffOptions' => app(StaffService::class)->getActiveStaffNames(),
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validated();

        $isActivated = in_array((string) $validated['isActivated'], ['1', 'true'], true) ? '1' : '0';

        $user = User::create([
            'name' => trim($validated['name']),
            'username' => trim($validated['username']),
            'email' => ! empty($validated['email']) ? trim($validated['email']) : null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'isActivated' => $isActivated,
        ]);

        return back()->with('success', "User '{$user->name}' was created successfully.");
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validated();

        // Safety guard: Super Admin cannot demote self or deactivate self
        if (Auth::id() === $user->id) {
            if ($validated['role'] !== 'Super Admin') {
                return back()->withErrors(['role' => 'You cannot change your own Super Admin role.']);
            }
            if (in_array((string) $validated['isActivated'], ['0', 'false'], true)) {
                return back()->withErrors(['isActivated' => 'You cannot deactivate your own account.']);
            }
        }

        $isActivated = in_array((string) $validated['isActivated'], ['1', 'true'], true) ? '1' : '0';

        $updates = [
            'name' => trim($validated['name']),
            'username' => trim($validated['username']),
            'email' => ! empty($validated['email']) ? trim($validated['email']) : null,
            'role' => $validated['role'],
            'isActivated' => $isActivated,
        ];

        if (! empty($validated['password'])) {
            $updates['password'] = Hash::make($validated['password']);
        }

        DB::transaction(function () use ($user, $updates, $isActivated, $validated): void {
            if ($isActivated === '0' && ! empty($validated['reassign_action'])) {
                $this->reassignStaffWorkload(
                    $user->name,
                    $validated['reassign_action'],
                    $validated['reassign_to'] ?? null,
                    Auth::user()?->name ?? 'Super Admin'
                );
            }

            // Keep assigned tickets in sync if staff name changed
            if ($user->name !== $updates['name']) {
                TicketDetail::where('ticket_assigned_staff', $user->name)
                    ->whereIn('ticket_status', ['New', 'Pending', 'In Progress'])
                    ->update(['ticket_assigned_staff' => $updates['name']]);
            }

            $user->update($updates);
        });

        return back()->with('success', "User '{$user->name}' was updated successfully.");
    }

    /**
     * Toggle the active status of the specified user.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        if (Auth::id() === $user->id) {
            return back()->withErrors(['error' => 'You cannot deactivate your own account.']);
        }

        $validated = $request->validate([
            'reassign_action' => ['nullable', 'string', 'in:reassign,unassign,keep'],
            'reassign_to' => ['nullable', 'required_if:reassign_action,reassign', 'string', 'max:255'],
        ]);

        $newStatus = ($user->isActivated === '0') ? '1' : '0';

        DB::transaction(function () use ($user, $newStatus, $validated): void {
            $user->update(['isActivated' => $newStatus]);

            if ($newStatus === '0' && ! empty($validated['reassign_action'])) {
                $this->reassignStaffWorkload(
                    $user->name,
                    $validated['reassign_action'],
                    $validated['reassign_to'] ?? null,
                    Auth::user()?->name ?? 'Super Admin'
                );
            }
        });

        $statusLabel = $newStatus === '1' ? 'activated' : 'deactivated';

        return back()->with('success', "User '{$user->name}' has been {$statusLabel}.");
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        if (Auth::id() === $user->id) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $validated = $request->validate([
            'reassign_action' => ['nullable', 'string', 'in:reassign,unassign,keep'],
            'reassign_to' => ['nullable', 'required_if:reassign_action,reassign', 'string', 'max:255'],
        ]);

        $name = $user->name;

        DB::transaction(function () use ($user, $validated): void {
            if (! empty($validated['reassign_action'])) {
                $this->reassignStaffWorkload(
                    $user->name,
                    $validated['reassign_action'],
                    $validated['reassign_to'] ?? null,
                    Auth::user()?->name ?? 'Super Admin'
                );
            }

            $user->delete();
        });

        return back()->with('success', "User '{$name}' was deleted successfully.");
    }

    /**
     * Reassign or unassign active tickets belonging to a staff member upon deactivation or deletion.
     */
    private function reassignStaffWorkload(string $fromStaffName, ?string $action, ?string $toStaffName, string $performedBy): int
    {
        $cleanFrom = trim($fromStaffName);
        if ($cleanFrom === '' || empty($action) || $action === 'keep') {
            return 0;
        }

        $openTickets = TicketDetail::query()
            ->whereRaw('TRIM(ticket_assigned_staff) = ?', [$cleanFrom])
            ->whereIn('ticket_status', ['New', 'Pending', 'In Progress'])
            ->get();

        if ($openTickets->isEmpty()) {
            return 0;
        }

        $count = 0;

        if ($action === 'reassign' && ! empty($toStaffName)) {
            $cleanTo = trim($toStaffName);
            foreach ($openTickets as $detail) {
                $detail->update([
                    'ticket_assigned_staff' => $cleanTo,
                    'ticket_assigned_by' => $performedBy,
                ]);

                TicketActivityLog::create([
                    'client_referenceNo' => $detail->client_referenceNo,
                    'activity_name' => "Workload reassigned from {$cleanFrom} to {$cleanTo}",
                    'activity_date' => now(),
                    'activity_user' => $performedBy,
                ]);
                $count++;
            }
        } elseif ($action === 'unassign') {
            foreach ($openTickets as $detail) {
                $detail->update([
                    'ticket_assigned_staff' => null,
                    'ticket_status' => 'New',
                    'ticket_assigned_by' => $performedBy,
                ]);

                TicketActivityLog::create([
                    'client_referenceNo' => $detail->client_referenceNo,
                    'activity_name' => "Workload unassigned to Triage Queue upon departure/deactivation of {$cleanFrom}",
                    'activity_date' => now(),
                    'activity_user' => $performedBy,
                ]);
                $count++;
            }
        }

        return $count;
    }
}
