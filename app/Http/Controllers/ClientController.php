<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\TicketActivityLog;
use App\Models\TicketAttachmentDetail;
use App\Models\TicketClientDetail;
use App\Models\TicketDetail;
use App\Models\TicketEmployee;
use App\Services\TicketNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

// v2_ticket_rating_details (if you use it)

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $hasTrackParam = $request->query('tab') === 'track' || $request->filled('track') || $request->filled('ref');

        return Inertia::render('TicketsV2/ClientMain', [
            'initialView' => $hasTrackParam ? 'track' : 'create',
            'initialCode' => $request->query('track') ?? $request->query('ref'),
            'autoOpen' => $request->boolean('open', false),
        ]);
    }

    public function store(Request $request)
    {
        $isMinimal = $request->filled('employee_code') && ! $request->has('client_type');

        $DEFAULT_NATURE = 'Hardware';
        $DEFAULT_SUPPORT = 'Others';

        if ($isMinimal) {
            $data = $request->validate([
                'employee_code' => ['required', 'string', 'max:100'],
                'ticket_type' => ['required', 'in:Technical Support,Livestream'],
                'ticket_problem_that_needed_support' => ['required', 'string', 'min:5'],
                'attachments' => ['sometimes', 'array'],
                'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:10240'],
            ]);

            $emp = TicketEmployee::query()
                ->where('employee_code', $data['employee_code'])
                ->orWhere('id', $data['employee_code'])
                ->first();

            if (! $emp) {
                return back()->withErrors(['employee_code' => 'Employee ID not found.'])->withInput();
            }

            $ref = null;

            DB::transaction(function () use ($data, $emp, $DEFAULT_NATURE, $DEFAULT_SUPPORT, &$ref) {
                $ref = $this->generateClientReferenceNo();

                TicketDetail::create([
                    'client_referenceNo' => $ref,
                    'ticket_status' => 'New',
                    'ticket_type' => $data['ticket_type'],
                    'ticket_nature_of_support' => $DEFAULT_NATURE,
                    'ticket_support_type' => $DEFAULT_SUPPORT,
                    'ticket_problem_that_needed_support' => $data['ticket_problem_that_needed_support'],
                    'ticket_actions_taken' => '',
                    'ticket_remarks' => '',
                    'date_requested' => now(),
                ]);

                TicketClientDetail::create([
                    'client_type' => 'Internal',
                    'client_name' => $this->formatEmpName($emp),
                    'client_division_agency' => $emp->division_id ?? null,
                    'client_empID' => $emp->employee_code,
                    'client_sex' => $emp->sex ?? null,
                    'client_referenceNo' => $ref,
                ]);

                TicketActivityLog::insert([[
                    'client_referenceNo' => $ref,
                    'activity_name' => 'Ticket Created',
                    'activity_date' => now(),
                    'activity_user' => $this->formatEmpName($emp),
                ]]);
            });

            // ⬇️ save attachments using the reference number
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if (! $file->isValid()) {
                        continue;
                    }

                    $path = $file->store("ticket_attachments/{$ref}", 'public');

                    TicketAttachmentDetail::create([
                        'client_referenceNo' => $ref,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_path' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'size_bytes' => $file->getSize(),
                        'is_image' => str_starts_with($file->getClientMimeType(), 'image/'),
                        'uploaded_by' => optional($request->user())->id,
                    ]);
                }
            }

            app(TicketNotificationService::class)->sendTicketCreatedNotification($ref);

            return back()->with('success', 'Ticket created')->with('refNo', $ref);
        }

        // ===== Full mode (modal path) =====
        $data = $request->validate([
            'client_type' => 'required|in:Internal,External',
            'client_name' => 'required|string|max:255',
            'client_empID' => 'nullable|string|max:100',
            'client_sex' => 'nullable|string|max:30',
            'client_division_agency' => 'nullable|string|max:255',
            'ticket_type' => 'required|in:Technical Support,Livestream',
            'ticket_problem_that_needed_support' => 'required|string|min:5',
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:10240'],
        ]);

        $ref = null;

        DB::transaction(function () use ($data, $DEFAULT_NATURE, $DEFAULT_SUPPORT, &$ref) {
            $ref = $this->generateClientReferenceNo();

            TicketDetail::create([
                'client_referenceNo' => $ref,
                'ticket_status' => 'New',
                'ticket_type' => $data['ticket_type'],
                'ticket_nature_of_support' => $data['ticket_nature_of_support'] ?? $DEFAULT_NATURE,
                'ticket_support_type' => $data['ticket_support_type'] ?? $DEFAULT_SUPPORT,
                'ticket_problem_that_needed_support' => $data['ticket_problem_that_needed_support'],
                'ticket_actions_taken' => '',
                'ticket_remarks' => '',
                'date_requested' => now(),
            ]);

            TicketClientDetail::create([
                'client_type' => $data['client_type'],
                'client_name' => $data['client_name'],
                'client_division_agency' => $data['client_division_agency'] ?? null,
                'client_empID' => $data['client_empID'] ?? null,
                'client_sex' => $data['client_sex'] ?? null,
                'client_referenceNo' => $ref,
            ]);

            TicketActivityLog::insert([[
                'client_referenceNo' => $ref,
                'activity_name' => 'Ticket Created',
                'activity_date' => now(),
                'activity_user' => $data['client_name'],
            ]]);
        });

        // ⬇️ save attachments using the reference number
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if (! $file->isValid()) {
                    continue;
                }

                $path = $file->store("ticket_attachments/{$ref}", 'public');

                TicketAttachmentDetail::create([
                    'client_referenceNo' => $ref,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                    'is_image' => str_starts_with($file->getClientMimeType(), 'image/'),
                    'uploaded_by' => optional($request->user())->id,
                ]);
            }
        }

        app(TicketNotificationService::class)->sendTicketCreatedNotification($ref);

        return back()->with('success', 'Ticket created')->with('refNo', $ref);

    }

    private function formatEmpName(TicketEmployee $e): string
    {
        $first = trim($e->fname ?? '');
        $last = trim($e->lname ?? '');

        return trim($first.' '.$last);
    }

    /** Generate a daily sequence client reference number like 20250819-001 */
    protected function generateClientReferenceNo(): string
    {
        $prefix = Carbon::today()->format('Ymd');

        // Must be called inside a transaction for lockForUpdate() to work
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

    public function listAttachments(string $ref)
    {
        $items = TicketAttachmentDetail::where('client_referenceNo', $ref)
            ->latest('id')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'url' => Storage::disk('public')->url($a->stored_path),
                'mime' => $a->mime_type,
                'size' => (int) $a->size_bytes,
                'is_image' => (bool) $a->is_image,
                'created_at' => optional($a->created_at)->toDateTimeString(),
            ]);

        return response()->json($items);
    }

    public function downloadAttachment(string $ref, TicketAttachmentDetail $attachment)
    {
        // simple guard: be sure this attachment belongs to the ticket ref
        abort_unless($attachment->client_referenceNo === $ref, 403);

        return Storage::disk('public')->download(
            $attachment->stored_path,
            $attachment->original_name
        );
    }

    public function track(Request $request, string $employee_code)
    {
        if (! $request->wantsJson() || $request->header('X-Inertia')) {
            return redirect()->route('client.main', [
                'track' => $employee_code,
                'open' => '1',
            ]);
        }

        $query = trim($employee_code);

        // 1) Direct reference number lookup
        $refTickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->where('d.client_referenceNo', $query)
            ->orWhere('c.client_referenceNo', $query)
            ->select([
                'd.client_referenceNo',
                'd.ticket_status',
                'd.ticket_type',
                'd.ticket_nature_of_support',
                'd.ticket_support_type',
                'd.ticket_problem_that_needed_support',
                'd.ticket_actions_taken',
                'd.ticket_remarks',
                'd.date_requested',
                'd.date_completed',
                'd.ticket_assigned_staff',
                'd.ticket_attended_by',
                'c.client_name',
                'c.client_division_agency',
                'c.client_empID',
            ])
            ->get();

        if ($refTickets->isNotEmpty()) {
            $first = $refTickets->first();
            $emp = null;
            if (! empty($first->client_empID) && $first->client_empID !== 'None') {
                $emp = TicketEmployee::query()
                    ->where('employee_code', $first->client_empID)
                    ->orWhere('id', $first->client_empID)
                    ->first();
            }

            return response()->json([
                'search_type' => 'reference',
                'employee' => $emp ? [
                    'id' => $emp->id,
                    'name' => $this->formatEmpName($emp),
                    'code' => $emp->employee_code,
                ] : [
                    'id' => null,
                    'name' => $first->client_name,
                    'code' => $first->client_empID ?? $first->client_referenceNo,
                ],
                'tickets' => $refTickets,
            ]);
        }

        // 2) Employee code / ID lookup
        $emp = TicketEmployee::query()
            ->where('employee_code', $query)
            ->orWhere('id', $query)
            ->first();

        $empCode = $emp ? $emp->employee_code : $query;

        $tickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->where(function ($q) use ($empCode, $query) {
                $q->where('c.client_empID', $empCode)
                    ->orWhere('c.client_empID', $query);
            })
            ->orderByDesc('d.date_requested')
            ->select([
                'd.client_referenceNo',
                'd.ticket_status',
                'd.ticket_type',
                'd.ticket_nature_of_support',
                'd.ticket_support_type',
                'd.ticket_problem_that_needed_support',
                'd.ticket_actions_taken',
                'd.ticket_remarks',
                'd.date_requested',
                'd.date_completed',
                'd.ticket_assigned_staff',
                'd.ticket_attended_by',
                'c.client_name',
                'c.client_division_agency',
                'c.client_empID',
            ])
            ->get();

        return response()->json([
            'search_type' => 'employee',
            'employee' => $emp ? [
                'id' => $emp->id,
                'name' => $this->formatEmpName($emp),
                'code' => $emp->employee_code,
            ] : null,
            'tickets' => $tickets,
        ]);
    }

    public function ticketShow(string $ref)
    {
        // Basic ticket + client
        $d = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->where('d.client_referenceNo', $ref)
            ->select([
                'd.client_referenceNo',
                'd.ticket_status',
                'd.ticket_type',
                'd.ticket_nature_of_support',
                'd.ticket_support_type',
                'd.ticket_problem_that_needed_support',
                'd.date_requested',
                'c.client_name',
                'c.client_email',
                'c.client_empID',
                'c.client_sex',
            ])->first();

        if (! $d) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Dates from activity logs (if you use them)
        $attended = TicketActivityLog::where('client_referenceNo', $ref)
            ->where('activity_name', 'Ticket Attended')
            ->orderByDesc('activity_date')->value('activity_date');

        $completed = TicketActivityLog::where('client_referenceNo', $ref)
            ->where('activity_name', 'Ticket Completed')
            ->orderByDesc('activity_date')->value('activity_date');

        return response()->json([
            'client_referenceNo' => $d->client_referenceNo,
            'ticket_status' => $d->ticket_status,
            'ticket_type' => $d->ticket_type,
            'ticket_nature_of_support' => $d->ticket_nature_of_support,
            'ticket_support_type' => $d->ticket_support_type,
            'ticket_problem_that_needed_support' => $d->ticket_problem_that_needed_support,
            'date_requested' => optional($d->date_requested)->toDateTimeString() ?? (string) $d->date_requested,
            'date_attended' => $attended ? Carbon::parse($attended)->toDateTimeString() : null,
            'date_completed' => $completed ? Carbon::parse($completed)->toDateTimeString() : null,
            'client_name' => $d->client_name,
            'client_email' => $d->client_email ?? null,
            'client_empID' => $d->client_empID,
            'client_sex' => $d->client_sex,
        ]);
    }

    public function ticketMessages(string $ref)
    {
        // If you use v2_ticket_messages
        $rows = DB::table('v2_ticket_messages')
            ->where('client_referenceNo', $ref)
            ->orderBy('id')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'sender_type' => $r->sender_type, // 'client' or 'staff'
                    'sender_name' => $r->sender_name,
                    'message' => $r->message,
                    'created_at' => optional($r->created_at)->toDateTimeString(),
                ];
            });

        return response()->json($rows);
    }

    public function postTicketMessage(Request $request, string $ref)
    {
        $data = $request->validate([
            'message' => 'required|string|min:1|max:2000',
            'sender_type' => 'required|in:client,staff',
            'sender_name' => 'sometimes|nullable|string|max:255',
        ]);

        // Optional: guard that ticket exists
        $exists = DB::table('v2_ticket_details')->where('client_referenceNo', $ref)->exists();
        abort_unless($exists, 404, 'Ticket not found');

        $senderName = $data['sender_name'] ?? null;
        if (! $senderName) {
            if ($data['sender_type'] === 'client') {
                $senderName = DB::table('v2_ticket_client_details')
                    ->where('client_referenceNo', $ref)
                    ->value('client_name') ?? 'Client';
            } else {
                $senderName = 'Staff';
            }
        }

        DB::table('v2_ticket_messages')->insert([
            'client_referenceNo' => $ref,
            'sender_type' => $data['sender_type'],
            'sender_name' => $senderName,
            'message' => $data['message'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Log to activity log (optional)
        TicketActivityLog::insert([[
            'client_referenceNo' => $ref,
            'activity_name' => $data['sender_type'] === 'client' ? 'Client Message' : 'Staff Message',
            'activity_date' => now(),
            'activity_user' => $senderName,
        ]]);

        return response()->json(['ok' => true]);
    }

    public function ratingShow(string $ref)
    {
        $row = DB::table('v2_ticket_rating_details')
            ->where('client_referenceNo', $ref)
            ->first();

        if (! $row) {
            return response()->json(null);
        }

        return response()->json([
            'rating_id' => $row->rating_id ?? null,
            'client_referenceNo' => $row->client_referenceNo,
            'rating_status' => $row->rating_status,
            'rating_result' => $row->rating_result,
            'rating_comment_suggestion' => $row->rating_comment_suggestion,
            'created_at' => optional($row->created_at)->toDateTimeString(),
            'updated_at' => optional($row->updated_at)->toDateTimeString(),
        ]);
    }

    public function ratingStore(Request $request, string $ref)
    {
        // guard ticket existence
        abort_unless(DB::table('v2_ticket_details')->where('client_referenceNo', $ref)->exists(), 404, 'Ticket not found');

        $valid = $request->validate([
            'rating_result' => 'required|in:Excellent,Very Good,Good,Poor,Very Poor',
            'rating_comment_suggestion' => 'nullable|string|max:2000',
        ]);

        $now = now();
        $existing = DB::table('v2_ticket_rating_details')->where('client_referenceNo', $ref)->first();

        if ($existing) {
            DB::table('v2_ticket_rating_details')
                ->where('client_referenceNo', $ref)
                ->update([
                    'rating_status' => 'Received',
                    'rating_result' => $valid['rating_result'],
                    'rating_comment_suggestion' => $valid['rating_comment_suggestion'] ?? null,
                    'updated_at' => $now,
                ]);
        } else {
            DB::table('v2_ticket_rating_details')->insert([
                'client_referenceNo' => $ref,
                'rating_status' => 'Received',
                'rating_result' => $valid['rating_result'],
                'rating_comment_suggestion' => $valid['rating_comment_suggestion'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Optional: log as activity
        TicketActivityLog::insert([[
            'client_referenceNo' => $ref,
            'activity_name' => 'Client Rating Submitted',
            'activity_date' => $now,
            'activity_user' => 'Client',
        ]]);

        $ticket = TicketDetail::where('client_referenceNo', $ref)->first();
        if ($ticket && $ticket->ticket_status === TicketStatus::Completed->value) {
            $ticket->ticket_status = TicketStatus::Closed->value;
            $ticket->save();

            TicketActivityLog::create([
                'client_referenceNo' => $ref,
                'activity_name' => 'Ticket Closed',
                'activity_date' => $now,
                'activity_user' => 'System (Client Rated)',
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function ratePage(string $ref)
    {
        abort_unless(
            DB::table('v2_ticket_details')->where('client_referenceNo', $ref)->exists(),
            404
        );

        return Inertia::render('TicketsV2/ClientComponents/RateTicketPage', [
            'refNo' => $ref,
        ]);
    }
}
