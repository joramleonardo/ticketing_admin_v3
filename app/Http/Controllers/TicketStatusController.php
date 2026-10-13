<?php

namespace App\Http\Controllers;

use App\Enums\SupportNature;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketActivityLog;
use App\Models\TicketClientDetail;
use App\Models\TicketDetail;
use App\Models\TicketEvent;
use App\Models\TicketStatus as TicketStatusModel;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TicketStatusController extends Controller
{
    /** Complete (In Progress -> Completed) */
    public function complete(Request $request, int|string $id): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_status' => ['required', Rule::in([TicketStatus::Completed->value])],
            'ticket_type' => 'nullable|string|max:255',
            'ticket_nature_of_support' => ['nullable', Rule::in(['Events', 'Hardware', 'Software', 'Hardware & Software', 'System Modification', 'Livestream', 'Others'])],
            'ticket_support_type' => 'nullable|string|max:255',
            'ticket_related_type' => ['nullable', Rule::in([
                'Installation related', 'Livestream related', 'Network related', 'PC Setup & Troubleshooting', 'Printer related', 'STARBOOKS related', 'Website and System related', 'Events related', 'Others',
            ])],
            'ticket_assisted_by' => 'nullable|string',
            'ticket_actions_taken' => 'nullable|string',
            'ticket_remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($id, $validated): void {
            $detail = TicketDetail::lockForUpdate()->findOrFail($id);

            if ($detail->ticket_status !== TicketStatus::InProgress->value) {
                abort(422, 'Only tickets In Progress can be marked as completed.');
            }

            $updateData = array_filter([
                'ticket_status' => TicketStatus::Completed->value,
                'ticket_type' => $validated['ticket_type'] ?? null,
                'ticket_nature_of_support' => $validated['ticket_nature_of_support'] ?? null,
                'ticket_support_type' => $validated['ticket_support_type'] ?? null,
                'ticket_related_type' => $validated['ticket_related_type'] ?? null,
                'ticket_assisted_by' => $validated['ticket_assisted_by'] ?? null,
                'ticket_actions_taken' => $validated['ticket_actions_taken'] ?? null,
                'ticket_remarks' => $validated['ticket_remarks'] ?? null,
                'date_completed' => now(),
            ], fn ($v) => ! is_null($v));

            $detail->update($updateData);

            TicketActivityLog::create([
                'client_referenceNo' => $detail->client_referenceNo,
                'activity_name' => 'Ticket Completed',
                'activity_date' => now(),
                'activity_user' => auth()->user()?->name ?? 'System',
            ]);
        });

        $detail = TicketDetail::findOrFail($id);
        $ref = $detail->client_referenceNo;

        $client = TicketClientDetail::where('client_referenceNo', $ref)->first();
        $clientEmail = trim($client->client_email ?? '');
        $clientName = $client->client_name ?? 'Customer';
        $empCode = $client->client_empID ?? null;

        if ($clientEmail !== '') {
            $rateUrl = "http://ticketing2.stii.local/client/rate/{$ref}";

            try {
                $mailable = new \App\Mail\TicketCompletedMail(
                    refNo: $ref,
                    clientName: $clientName,
                    problem: $detail->ticket_problem_that_needed_support,
                    employeeCode: $empCode,
                    rateUrl: $rateUrl
                );

                if (config('queue.default') === 'sync') {
                    Mail::to($clientEmail)->send($mailable);
                } else {
                    Mail::to($clientEmail)->queue($mailable);
                }
            } catch (\Throwable $e) {
                \Log::error('TicketCompletedMail failed', [
                    'ref' => $ref,
                    'to' => $clientEmail,
                    'err' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'Ticket completed successfully!');
    }

    /** Cancel ticket (Terminal state with mandatory reason) */
    public function cancel(Request $request, int|string $id): RedirectResponse
    {
        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:255',
            'ticket_remarks' => 'nullable|string|max:2000',
        ]);

        $detail = TicketDetail::findOrFail($id);

        if (in_array($detail->ticket_status, TicketStatus::terminalValues(), true)) {
            abort(422, 'Ticket is already in a terminal state ('.$detail->ticket_status.') and cannot be cancelled.');
        }

        DB::transaction(function () use ($detail, $validated): void {
            $reason = trim($validated['cancel_reason']);
            $remarks = trim($validated['ticket_remarks'] ?? '');
            $entry = $remarks !== '' ? "Cancelled: [{$reason}] - {$remarks}" : "Cancelled: [{$reason}]";

            $detail->ticket_status = TicketStatus::Cancelled->value;
            $detail->ticket_remarks = $detail->ticket_remarks
                ? $detail->ticket_remarks."\n".$entry
                : $entry;
            $detail->save();

            TicketActivityLog::create([
                'client_referenceNo' => $detail->client_referenceNo,
                'activity_name' => 'Ticket Cancelled',
                'activity_date' => now(),
                'activity_user' => auth()->user()?->name ?? 'System',
            ]);
        });

        return back()->with('success', 'Ticket has been cancelled successfully.');
    }

    /** Formally close ticket (Completed -> Closed) */
    public function close(Request $request, int|string $id): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_remarks' => 'nullable|string|max:2000',
        ]);

        $detail = TicketDetail::findOrFail($id);

        if ($detail->ticket_status !== TicketStatus::Completed->value) {
            abort(422, 'Only completed tickets can be formally closed.');
        }

        DB::transaction(function () use ($detail, $validated): void {
            $remarks = trim($validated['ticket_remarks'] ?? '');

            $detail->ticket_status = TicketStatus::Closed->value;
            if ($remarks !== '') {
                $entry = "Closed: {$remarks}";
                $detail->ticket_remarks = $detail->ticket_remarks
                    ? $detail->ticket_remarks."\n".$entry
                    : $entry;
            }
            $detail->save();

            TicketActivityLog::create([
                'client_referenceNo' => $detail->client_referenceNo,
                'activity_name' => 'Ticket Closed',
                'activity_date' => now(),
                'activity_user' => auth()->user()?->name ?? 'System',
            ]);
        });

        return back()->with('success', 'Ticket has been closed successfully.');
    }

    public function getActivityLogs($reference_code)
    {
        $logs = TicketActivityLog::where('client_referenceNo', $reference_code)
            ->orderBy('activity_date', 'desc')
            ->get();

        return response()->json($logs);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'ticket_type' => [
                'sometimes', 'nullable', 'string', 'max:255',
                Rule::in(['Technical Support', 'Livestream', 'Events']),
            ],

            'ticket_assigned_staff' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ticket_attended_by' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ticket_nature_of_support' => [
                'sometimes', 'nullable',
                Rule::in(SupportNature::values()),
            ],
            'ticket_support_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ticket_remarks' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'ticket_problem_that_needed_support' => ['sometimes', 'nullable', 'string'],
            'ticket_actions_taken' => ['sometimes', 'nullable', 'string'],
            'ticket_status' => [
                'sometimes', 'nullable',
                Rule::in(TicketStatus::values()),
            ],
            'ticket_related_type' => [
                'sometimes', 'nullable',
                Rule::in([
                    'Installation related', 'Livestream related', 'Network related', 'PC Setup & Troubleshooting', 'Printer related', 'STARBOOKS related', 'Website and System related', 'Events related', 'Others',
                ]),
            ],

            // Events fields (nullable here; we’ll require them conditionally)
            'event_start_date' => ['sometimes', 'nullable', 'date'],
            'event_end_date' => ['sometimes', 'nullable', 'date'],
            'event_venue' => ['nullable', 'string', 'max:255'],
            'event_remarks' => ['sometimes', 'nullable', 'string'],
        ]);

        $fields = [
            'ticket_type',
            'ticket_assigned_staff',
            'ticket_attended_by',
            'ticket_nature_of_support',
            'ticket_support_type',
            'ticket_related_type',
            'ticket_remarks',
            'ticket_status',
            'ticket_problem_that_needed_support',
            'ticket_actions_taken',
        ];

        $updateData = [];
        foreach ($fields as $field) {
            if ($request->exists($field)) {
                $value = $validated[$field] ?? null; // if explicitly null, store null
                if (is_string($value)) {
                    $value = trim($value);
                }
                $updateData[$field] = $value;
            }
        }

        $detail = TicketDetail::findOrFail($id); // id = v2_ticket_details.ticket_id

        $incomingStatus = $updateData['ticket_status'] ?? null;
        $effectiveStatus = $incomingStatus ?: $detail->ticket_status;
        $incomingAssigned = $updateData['ticket_assigned_staff'] ?? null;
        $currentAssigned = $detail->ticket_assigned_staff;

        if (strcasecmp($effectiveStatus, 'In Progress') === 0) {
            $updateData['ticket_attended_by'] = $request->exists('ticket_assigned_staff')
                ? $incomingAssigned
                : $currentAssigned;
        }

        if ($request->exists('ticket_assigned_staff')) {
            $user = auth()->user();
            if (! $user || ! in_array($user->role, ['Admin', 'Super Admin'], true)) {
                abort(403, 'Only administrators can assign or reassign tickets.');
            }
            $updateData['ticket_assigned_by'] = $user?->name ?? 'System';
            $updateData['ticket_assigned_by_id'] = $user?->id ?? null; // if column exists
        }

        // --- Start transaction to keep ticket + event consistent ---
        DB::transaction(function () use ($request, $detail, $updateData) {
            $detail->fill($updateData);
            $dirty = $detail->getDirty();
            $originalStaff = $detail->getOriginal('ticket_assigned_staff');

            // 1) Update main ticket record
            $detail->save();

            // Record activity log if attributes were modified
            if (! empty($dirty)) {
                $activityName = 'Ticket Details Updated';
                if (array_key_exists('ticket_assigned_staff', $dirty)) {
                    $activityName = $originalStaff ? 'Staff Reassigned' : 'Staff Assigned';
                }
                TicketActivityLog::create([
                    'client_referenceNo' => $detail->client_referenceNo,
                    'activity_name' => $activityName,
                    'activity_date' => now(),
                    'activity_user' => auth()->user()?->name ?? 'System',
                ]);
            }

            // 2) If ticket_type is Events, validate & upsert v2_ticket_events
            $isEvents = false;
            if (array_key_exists('ticket_type', $updateData) && $updateData['ticket_type']) {
                $isEvents = (strcasecmp($updateData['ticket_type'], 'Events') === 0);
            } else {
                // If type wasn’t sent this time, look at current (post-save) value
                $isEvents = (strcasecmp($detail->ticket_type ?? '', 'Events') === 0);
            }

            if ($isEvents) {
                // Require start_date and venue; end_date optional but must be >= start if present
                $eventData = $request->validate([
                    'event_start_date' => ['required', 'date'],
                    'event_end_date' => ['nullable', 'date'],
                    'event_venue' => ['nullable', 'string', 'max:255'],
                    'event_remarks' => ['sometimes', 'nullable', 'string'],
                ]);

                // If end provided, ensure end >= start
                if (! empty($eventData['event_end_date'])) {
                    if (strtotime($eventData['event_end_date']) < strtotime($eventData['event_start_date'])) {
                        abort(422, 'Event end date must be the same day or after the start date.');
                    }
                }

                $clientRef = $detail->client_referenceNo; // source of truth

                // One-event-per-ticket (update if exists else create)
                TicketEvent::updateOrCreate(
                    ['client_referenceNo' => $clientRef],
                    [
                        'event_start_date' => $eventData['event_start_date'],
                        'event_end_date' => $eventData['event_end_date'] ?? null,
                        'event_venue' => trim($eventData['event_venue']),
                        'event_remarks' => $eventData['event_remarks'] ?? null,
                    ]
                );
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully.',
            ]);
        }

        return back()->with('success', 'Ticket updated successfully.');
    }

    public function attend(Request $request, int|string $id): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_status' => ['nullable', Rule::in(TicketStatus::values())],
        ]);

        // Find ticket detail
        $detail = TicketDetail::findOrFail($id);

        // Build the update payload
        $update = [];

        // ✅ Update the status
        if (! empty($validated['ticket_status'])) {
            $detail->update([
                'ticket_status' => $validated['ticket_status'],
            ]);
        }

        // ✅ Authoritative server-side set
        $update['ticket_attended_by'] = auth()->user()->name;   // or ->id / ->username
        // (optional) if you track timestamp:
        // $update['ticket_attended_at'] = now();

        $detail->update($update);

        // ✅ Create an activity log
        TicketActivityLog::create([
            'client_referenceNo' => $detail->client_referenceNo,
            'activity_name' => 'Ticket Attended',
            'activity_date' => Carbon::now(), // instead of Carbon::today()
            'activity_user' => auth()->user()->name, // or username field
        ]);

        return back()->with('success', 'Ticket attended successfully and now in progress!');

    }

    public function storeNew(Request $request)
    {
        $validated = $request->validate([
            // Client
            'client_type' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'client_empID' => 'nullable|string|max:255',
            'client_sex' => 'nullable|string|max:50',
            'client_division_agency' => 'nullable|string|max:255',
            // Ticket
            'ticket_type' => 'required|string|max:255',
            'ticket_problem_that_needed_support' => 'required|string',
        ]);

        $actorName = auth()->user()->name ?? 'System';

        $refNo = DB::transaction(function () use ($validated, $actorName) {
            // 1) Generate new reference number (YYYYMMDD-###)
            $refNo = $this->generateClientReferenceNo();

            // 2) Client details
            TicketClientDetail::create([
                'client_type' => $validated['client_type'],
                'client_name' => $validated['client_name'],
                'client_empID' => $validated['client_empID'] ?? null,
                'client_sex' => $validated['client_sex'] ?? null,
                'client_division_agency' => $validated['client_division_agency'] ?? null,
                'client_referenceNo' => $refNo,
            ]);

            // 3) Ticket details (status = In Progress; attended_by = logged-in user)
            TicketDetail::create([
                'client_referenceNo' => $refNo,
                'ticket_type' => $validated['ticket_type'],
                'ticket_assigned_by' => 'Lloyd Mandapat',
                'ticket_assigned_staff' => $actorName,
                'ticket_attended_by' => $actorName,
                'ticket_status' => 'In Progress',
                'ticket_problem_that_needed_support' => $validated['ticket_problem_that_needed_support'],
                'date_requested' => Carbon::now(), // instead of Carbon::today()
            ]);

            // 4) Activity logs (Created, Assigned, Attended)
            $now = Carbon::now();
            TicketActivityLog::insert([
                [
                    'client_referenceNo' => $refNo,
                    'activity_name' => 'Ticket Created',
                    'activity_date' => $now,
                    'activity_user' => $actorName,
                ],
                [
                    'client_referenceNo' => $refNo,
                    'activity_name' => 'Ticket Assigned',
                    'activity_date' => $now,
                    'activity_user' => $actorName,
                ],
                [
                    'client_referenceNo' => $refNo,
                    'activity_name' => 'Ticket Attended',
                    'activity_date' => $now,
                    'activity_user' => $actorName,
                ],
            ]);

            return $refNo;
        });

        // Flash refNo so you can display it without extra queries
        return back()->with([
            'success' => 'New ticket created (In Progress) with activity logs.',
            'refNo' => $refNo,
        ]);
    }

    /** Generate a daily sequence client reference number like 20250819-001 */
    protected function generateClientReferenceNo(): string
    {
        $prefix = Carbon::today()->format('Ymd');

        // Get last sequence for today
        $last = DB::table('v2_ticket_client_details')
            ->select('client_referenceNo')
            ->where('client_referenceNo', 'like', "{$prefix}-%")
            ->orderBy('client_referenceNo', 'desc')
            ->lockForUpdate()
            ->first();

        $nextSeq = 1;
        if ($last && preg_match('/^'.$prefix.'-(\d{3})$/', $last->client_referenceNo, $m)) {
            $nextSeq = (int) $m[1] + 1;
        }

        return sprintf('%s-%03d', $prefix, $nextSeq);
    }

    public function dashboardStats(Request $request)
    {
        $month = $request->month;
        $year = $request->year;

        $query = TicketStatusModel::query();

        // Parse varchar date manually
        if ($month && $year) {
            $query->whereRaw("MONTH(STR_TO_DATE(entry_date, '%M %d %Y')) = ?", [$month])
                ->whereRaw("YEAR(STR_TO_DATE(entry_date, '%M %d %Y')) = ?", [$year]);
        }

        $stats = [
            'unassigned' => (clone $query)->where('status', 'Unassigned')->count(),
            'pending' => (clone $query)->where('status', 'Pending')->count(),
            'in_progress' => (clone $query)->where('status', 'In Progress')->count(),
            'completed' => (clone $query)->where('status', 'Completed')->count(),
        ];

        return Inertia::render('Dashboard', [
            'ticketStats' => $stats,
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }
}
