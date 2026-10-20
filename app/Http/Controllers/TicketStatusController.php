<?php

namespace App\Http\Controllers;

use App\Enums\SupportNature;
use App\Enums\TicketStatus;
use App\Mail\TicketAssignedStaffNotificationMail;
use App\Models\Ticket;
use App\Models\TicketActivityLog;
use App\Models\TicketClientDetail;
use App\Models\TicketDetail;
use App\Models\TicketEvent;
use App\Models\TicketStatus as TicketStatusModel;
use App\Models\User;
use App\Services\StaffService;
use App\Services\TicketNotificationService;
use App\Support\TicketClassification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TicketStatusController extends Controller
{
    /** Complete (In Progress -> Completed) */
    public function complete(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'ticket_status' => ['required', Rule::in([TicketStatus::Completed->value])],
            'ticket_type' => 'nullable|string|max:255',
            'ticket_nature_of_support' => ['nullable', Rule::in(['Events', 'Hardware', 'Software', 'Hardware & Software', 'System Modification', 'Livestream', 'Others'])],
            'ticket_support_type' => 'nullable|string|max:255',
            'ticket_related_type' => ['nullable', Rule::in(array_merge(TicketClassification::relatedTypes(), ['Server related']))],
            'ticket_assisted_by' => 'nullable|string',
            'ticket_actions_taken' => 'nullable|string',
            'ticket_remarks' => 'nullable|string',
            'event_start_date' => ['sometimes', 'nullable', 'date'],
            'event_end_date' => ['nullable', 'date'],
            'event_venue' => ['nullable', 'string', 'max:255'],
            'event_remarks' => ['sometimes', 'nullable', 'string'],
            'other_request' => ['sometimes', 'nullable', 'string'],
        ]);

        DB::transaction(function () use ($id, $validated, $request): void {
            $detail = TicketDetail::lockForUpdate()->findOrFail($id);

            if ($detail->ticket_status !== TicketStatus::InProgress->value) {
                abort(422, 'Only tickets In Progress can be marked as completed.');
            }

            $now = now();
            $updateData = array_filter([
                'ticket_status' => TicketStatus::Completed->value,
                'ticket_type' => $validated['ticket_type'] ?? null,
                'ticket_nature_of_support' => $validated['ticket_nature_of_support'] ?? null,
                'ticket_support_type' => $validated['ticket_support_type'] ?? null,
                'ticket_related_type' => $validated['ticket_related_type'] ?? null,
                'ticket_assisted_by' => $validated['ticket_assisted_by'] ?? null,
                'ticket_actions_taken' => $validated['ticket_actions_taken'] ?? null,
                'ticket_remarks' => $validated['ticket_remarks'] ?? null,
                'date_assigned' => $detail->date_assigned ?? $detail->date_attended ?? $detail->date_requested ?? $now,
                'date_attended' => $detail->date_attended ?? $detail->date_assigned ?? $detail->date_requested ?? $now,
                'date_completed' => $now,
            ], fn ($v) => ! is_null($v));

            if (empty($detail->ticket_attended_by) && auth()->check()) {
                $updateData['ticket_attended_by'] = auth()->user()?->name;
            }

            $detail->update($updateData);

            // Handle event details if Events type or event fields submitted
            $isEvents = (isset($updateData['ticket_type']) && strcasecmp($updateData['ticket_type'], 'Events') === 0)
                || (isset($updateData['ticket_nature_of_support']) && strcasecmp($updateData['ticket_nature_of_support'], 'Events') === 0)
                || (strcasecmp($detail->ticket_type ?? '', 'Events') === 0)
                || $request->filled('event_start_date');

            if ($isEvents && ($request->filled('event_start_date') || $request->filled('event_venue') || $request->filled('event_remarks') || $request->filled('other_request'))) {
                if (! empty($validated['event_end_date']) && ! empty($validated['event_start_date'])) {
                    if (strtotime($validated['event_end_date']) < strtotime($validated['event_start_date'])) {
                        abort(422, 'Event end date must be the same day or after the start date.');
                    }
                }

                $existingEvent = TicketEvent::where('client_referenceNo', $detail->client_referenceNo)->first();

                $eventAttrs = array_filter([
                    'event_start_date' => $validated['event_start_date'] ?? ($existingEvent?->event_start_date ?? Carbon::today()->toDateString()),
                    'event_end_date' => array_key_exists('event_end_date', $validated) ? $validated['event_end_date'] : ($existingEvent?->event_end_date),
                    'event_venue' => array_key_exists('event_venue', $validated) ? trim((string) $validated['event_venue']) : ($existingEvent?->event_venue ?? 'DOST-STII'),
                    'event_remarks' => array_key_exists('event_remarks', $validated) ? $validated['event_remarks'] : ($existingEvent?->event_remarks),
                    'other_request' => array_key_exists('other_request', $validated) ? $validated['other_request'] : ($existingEvent?->other_request),
                ], fn ($v) => ! is_null($v));

                TicketEvent::updateOrCreate(
                    ['client_referenceNo' => $detail->client_referenceNo],
                    $eventAttrs
                );
            }

            TicketActivityLog::create([
                'client_referenceNo' => $detail->client_referenceNo,
                'activity_name' => 'Ticket Completed',
                'activity_date' => now(),
                'activity_user' => auth()->user()?->name ?? 'System',
            ]);
        });

        $detail = TicketDetail::findOrFail($id);
        $ref = $detail->client_referenceNo;
        $staffName = $detail->ticket_attended_by
            ?: ($detail->ticket_assigned_staff ?: (auth()->user()?->name ?? 'IT Support Specialist'));

        $emailSent = app(TicketNotificationService::class)->sendTicketCompletedNotification($ref, $staffName);

        $successMsg = $emailSent
            ? 'Ticket completed successfully and client notified via email!'
            : 'Ticket completed successfully!';

        if ($request->boolean('json')) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
            ]);
        }

        return back()->with('success', $successMsg);
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
                Rule::in(array_merge(TicketClassification::relatedTypes(), ['Server related'])),
            ],

            // Events fields (nullable here; we’ll require them conditionally)
            'event_start_date' => ['sometimes', 'nullable', 'date'],
            'event_end_date' => ['sometimes', 'nullable', 'date'],
            'event_venue' => ['nullable', 'string', 'max:255'],
            'event_remarks' => ['sometimes', 'nullable', 'string'],
            'other_request' => ['sometimes', 'nullable', 'string'],
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
        $now = now();

        if ($request->exists('ticket_assigned_staff')) {
            $user = auth()->user();
            if (! $user || ! in_array($user->role, ['Admin', 'Super Admin'], true)) {
                abort(403, 'Only administrators can assign or reassign tickets.');
            }
            $updateData['ticket_assigned_by'] = $user?->name ?? 'System';
            $updateData['ticket_assigned_by_id'] = $user?->id ?? null; // if column exists

            if (! empty($incomingAssigned) && ($incomingAssigned !== $currentAssigned || empty($detail->date_assigned))) {
                $updateData['date_assigned'] = $now;
            }
        }

        if (strcasecmp($effectiveStatus, 'In Progress') === 0) {
            $updateData['ticket_attended_by'] = $request->exists('ticket_assigned_staff')
                ? $incomingAssigned
                : $currentAssigned;

            if (empty($detail->date_attended)) {
                $updateData['date_attended'] = $now;
            }
            if (empty($detail->date_assigned) && empty($updateData['date_assigned'])) {
                $updateData['date_assigned'] = $now;
            }
        }

        $staffAssignedChanged = false;
        $assignedStaffName = null;
        $assignedBy = auth()->user()?->name ?? 'System';

        // --- Start transaction to keep ticket + event consistent ---
        DB::transaction(function () use ($request, $detail, $updateData, &$staffAssignedChanged, &$assignedStaffName) {
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
                    if (! empty($detail->ticket_assigned_staff) && $detail->ticket_assigned_staff !== $originalStaff) {
                        $staffAssignedChanged = true;
                        $assignedStaffName = $detail->ticket_assigned_staff;
                    }
                }
                TicketActivityLog::create([
                    'client_referenceNo' => $detail->client_referenceNo,
                    'activity_name' => $activityName,
                    'activity_date' => now(),
                    'activity_user' => auth()->user()?->name ?? 'System',
                ]);
            }

            // 2) If ticket_type is Events or event data is submitted, validate & upsert v2_ticket_events
            $hasEventData = $request->has('event_start_date')
                || $request->has('event_end_date')
                || $request->has('event_venue')
                || $request->has('event_remarks')
                || $request->has('other_request');

            $isEvents = (strcasecmp($detail->ticket_type ?? '', 'Events') === 0)
                || (strcasecmp($detail->ticket_nature_of_support ?? '', 'Events') === 0)
                || (strcasecmp($detail->ticket_support_type ?? '', 'Events') === 0)
                || $hasEventData;

            if ($isEvents) {
                if (empty($detail->ticket_nature_of_support)) {
                    $detail->ticket_nature_of_support = 'Events';
                    $detail->save();
                }

                if ($hasEventData) {
                    $eventData = $request->validate([
                        'event_start_date' => ['nullable', 'date'],
                        'event_end_date' => ['nullable', 'date'],
                        'event_venue' => ['nullable', 'string', 'max:255'],
                        'event_remarks' => ['sometimes', 'nullable', 'string'],
                        'other_request' => ['sometimes', 'nullable', 'string'],
                    ]);

                    // If end provided, ensure end >= start
                    if (! empty($eventData['event_end_date']) && ! empty($eventData['event_start_date'])) {
                        if (strtotime($eventData['event_end_date']) < strtotime($eventData['event_start_date'])) {
                            abort(422, 'Event end date must be the same day or after the start date.');
                        }
                    }

                    $clientRef = $detail->client_referenceNo; // source of truth
                    $existingEvent = TicketEvent::where('client_referenceNo', $clientRef)->first();

                    $eventAttributes = array_filter([
                        'event_start_date' => $eventData['event_start_date'] ?? ($existingEvent?->event_start_date ?? Carbon::today()->toDateString()),
                        'event_end_date' => array_key_exists('event_end_date', $eventData) ? $eventData['event_end_date'] : ($existingEvent?->event_end_date),
                        'event_venue' => array_key_exists('event_venue', $eventData) ? trim((string) $eventData['event_venue']) : ($existingEvent?->event_venue ?? 'DOST-STII'),
                        'event_remarks' => array_key_exists('event_remarks', $eventData) ? $eventData['event_remarks'] : ($existingEvent?->event_remarks),
                        'other_request' => array_key_exists('other_request', $eventData) ? $eventData['other_request'] : ($existingEvent?->other_request),
                    ], fn ($v) => ! is_null($v));

                    // One-event-per-ticket (update if exists else create)
                    TicketEvent::updateOrCreate(
                        ['client_referenceNo' => $clientRef],
                        $eventAttributes
                    );

                    if (empty($dirty)) {
                        TicketActivityLog::create([
                            'client_referenceNo' => $detail->client_referenceNo,
                            'activity_name' => 'Event Schedule Updated',
                            'activity_date' => now(),
                            'activity_user' => auth()->user()?->name ?? 'System',
                        ]);
                    }
                }
            }
        });

        if ($staffAssignedChanged && $assignedStaffName) {
            // Notify client about assigned IT specialist
            app(TicketNotificationService::class)->sendTicketAssignedClientNotification(
                $detail->client_referenceNo,
                $assignedStaffName
            );

            $staffUser = User::where('name', $assignedStaffName)
                ->orWhere('username', $assignedStaffName)
                ->first();

            $staffEmail = app(StaffService::class)->resolveStaffEmail($assignedStaffName)
                ?? ($staffUser ? app(StaffService::class)->resolveStaffEmail($staffUser->name) : null);
            $staffEmail = trim((string) ($staffEmail ?? ''));
            if ($staffEmail !== '' && filter_var($staffEmail, FILTER_VALIDATE_EMAIL)) {
                $client = DB::table('v2_ticket_client_details')
                    ->where('client_referenceNo', $detail->client_referenceNo)
                    ->first();

                $adminBaseUrl = config('app.url');
                if (! $adminBaseUrl || str_contains($adminBaseUrl, 'localhost')) {
                    if (request()->getHost()) {
                        $adminBaseUrl = request()->getSchemeAndHttpHost();
                    }
                }
                $adminBaseUrl = rtrim($adminBaseUrl ?: 'http://ticketing-admin.stii.local', '/');
                $viewUrl = "{$adminBaseUrl}/admin/my-tickets?search=".rawurlencode($detail->client_referenceNo);

                try {
                    $mailable = new TicketAssignedStaffNotificationMail(
                        staffName: $staffUser?->name ?? $assignedStaffName,
                        assignedBy: $assignedBy,
                        tickets: [[
                            'refNo' => $detail->client_referenceNo,
                            'clientName' => $client?->client_name ?? 'Client',
                            'division' => $client?->client_division_agency ?? null,
                            'problem' => $detail->ticket_problem_that_needed_support ?? null,
                            'ticketType' => $detail->ticket_type ?? 'Technical Support',
                            'dateRequested' => $detail->date_requested ? Carbon::parse($detail->date_requested)->format('M d, Y h:i A') : null,
                        ]],
                        viewUrl: $viewUrl,
                    );

                    if (config('queue.default') === 'sync') {
                        Mail::to($staffEmail)->send($mailable);
                    } else {
                        Mail::to($staffEmail)->queue($mailable);
                    }
                } catch (\Throwable $e) {
                    Log::error('TicketAssignedStaffNotificationMail failed to send in update', [
                        'staff' => $assignedStaffName,
                        'to' => $staffEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully.',
            ]);
        }

        return back()->with('success', 'Ticket updated successfully.');
    }

    public function attend(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'ticket_status' => ['nullable', Rule::in(TicketStatus::values())],
        ]);

        // Find ticket detail
        $detail = TicketDetail::findOrFail($id);
        $now = Carbon::now();

        // Build the update payload
        $update = [];

        // ✅ Update the status
        if (! empty($validated['ticket_status'])) {
            $update['ticket_status'] = $validated['ticket_status'];
        }

        // ✅ Authoritative server-side set
        $staffName = auth()->user()?->name ?? 'IT Support Staff';
        $update['ticket_attended_by'] = $staffName;
        if (empty($detail->date_assigned)) {
            $update['date_assigned'] = $now;
        }
        if (empty($detail->date_attended)) {
            $update['date_attended'] = $now;
        }

        $detail->update($update);

        // ✅ Create an activity log
        TicketActivityLog::create([
            'client_referenceNo' => $detail->client_referenceNo,
            'activity_name' => 'Ticket Attended',
            'activity_date' => $now,
            'activity_user' => $staffName,
        ]);

        // Look up client details for email notification
        $ref = $detail->client_referenceNo;
        $emailSent = app(TicketNotificationService::class)->sendTicketAttendedNotification($ref, $staffName);

        $successMsg = $emailSent
            ? 'Ticket attended successfully and client notified via email!'
            : 'Ticket attended successfully and now in progress!';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
            ]);
        }

        return back()->with('success', $successMsg);
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
            'ticket_problem_that_needed_support' => 'nullable|string',
            'ticket_assigned_staff' => 'nullable|string|max:255',
            'ticket_attended_by' => 'nullable|string|max:255',
            // Events
            'event_start_date' => 'nullable|date',
            'event_end_date' => 'nullable|date',
            'event_venue' => 'nullable|string|max:255',
            'event_remarks' => 'nullable|string',
            'other_request' => 'nullable|string',
        ]);

        $isEvents = (strcasecmp($validated['ticket_type'], 'Events') === 0);

        if (! $isEvents && empty(trim((string) ($validated['ticket_problem_that_needed_support'] ?? '')))) {
            $request->validate([
                'ticket_problem_that_needed_support' => 'required|string',
            ]);
        }

        $problem = trim((string) ($validated['ticket_problem_that_needed_support'] ?? ''));
        if ($problem === '') {
            if ($isEvents) {
                $problem = $validated['other_request']
                    ?: ($validated['event_remarks']
                        ?: 'Event Support: '.($validated['event_venue'] ?: 'Event'));
            } else {
                $problem = 'No description provided';
            }
        }

        $actorName = auth()->user()->name ?? 'System';
        $assignedStaff = $request->input('ticket_assigned_staff') ?: $actorName;
        $attendedBy = $request->input('ticket_attended_by') ?: $assignedStaff;

        $refNo = DB::transaction(function () use ($validated, $actorName, $assignedStaff, $attendedBy, $isEvents, $problem, $request) {
            // 1) Generate new reference number (YYYYMMDD-###)
            $refNo = $this->generateClientReferenceNo();
            $now = Carbon::now();

            // 2) Client details
            TicketClientDetail::create([
                'client_type' => $validated['client_type'],
                'client_name' => $validated['client_name'],
                'client_empID' => $validated['client_empID'] ?? null,
                'client_sex' => $validated['client_sex'] ?? null,
                'client_division_agency' => $validated['client_division_agency'] ?? null,
                'client_referenceNo' => $refNo,
            ]);

            // 3) Ticket details (status = In Progress; attended_by = logged-in user or specified staff)
            TicketDetail::create([
                'client_referenceNo' => $refNo,
                'ticket_type' => $validated['ticket_type'],
                'ticket_nature_of_support' => $isEvents ? 'Events' : null,
                'ticket_assigned_by' => $actorName,
                'ticket_assigned_staff' => $assignedStaff,
                'ticket_attended_by' => $attendedBy,
                'ticket_status' => 'In Progress',
                'ticket_problem_that_needed_support' => $problem,
                'date_requested' => $now,
                'date_assigned' => $now,
                'date_attended' => $now,
            ]);

            // 3.5) Event details if Events
            if ($isEvents || $request->filled('event_start_date') || $request->filled('event_venue')) {
                TicketEvent::create([
                    'client_referenceNo' => $refNo,
                    'event_start_date' => $validated['event_start_date'] ?? Carbon::today()->toDateString(),
                    'event_end_date' => $validated['event_end_date'] ?? null,
                    'event_venue' => isset($validated['event_venue']) ? trim((string) $validated['event_venue']) : 'DOST-STII',
                    'event_remarks' => $validated['event_remarks'] ?? null,
                    'other_request' => $validated['other_request'] ?? null,
                ]);
            }

            // 4) Activity logs (Created, Assigned, Attended)
            $now = Carbon::now();
            TicketActivityLog::insert([
                [
                    'client_referenceNo' => $refNo,
                    'activity_name' => 'Ticket Created',
                    'activity_date' => $now,
                    'activity_user' => $validated['client_name'],
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
                    'activity_user' => $attendedBy,
                ],
            ]);

            return $refNo;
        });

        // Send initial acknowledgment email to client (Root of email thread)
        app(TicketNotificationService::class)->sendTicketCreatedNotification($refNo);

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

        $lastClient = DB::table('v2_ticket_client_details')
            ->select('client_referenceNo')
            ->where('client_referenceNo', 'like', "{$prefix}-%")
            ->orderBy('client_referenceNo', 'desc')
            ->lockForUpdate()
            ->first();

        $lastDetail = DB::table('v2_ticket_details')
            ->select('client_referenceNo')
            ->where('client_referenceNo', 'like', "{$prefix}-%")
            ->orderBy('client_referenceNo', 'desc')
            ->lockForUpdate()
            ->first();

        $nextSeq = 1;
        foreach ([$lastClient, $lastDetail] as $last) {
            if ($last && preg_match('/^'.$prefix.'-(\d+)$/', $last->client_referenceNo, $m)) {
                $seq = (int) $m[1] + 1;
                if ($seq > $nextSeq) {
                    $nextSeq = $seq;
                }
            }
        }

        do {
            $candidate = sprintf('%s-%03d', $prefix, $nextSeq);
            $exists = DB::table('v2_ticket_client_details')->where('client_referenceNo', $candidate)->exists()
                || DB::table('v2_ticket_details')->where('client_referenceNo', $candidate)->exists();
            if ($exists) {
                $nextSeq++;
            }
        } while ($exists);

        return $candidate;
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
