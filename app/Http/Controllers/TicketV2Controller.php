<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketActivityLog;
use App\Models\TicketClientDetail;
use App\Models\TicketDetail;
use App\Models\TicketEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TicketV2Controller extends Controller
{
    /** Convert "john doe" -> "+john* +doe*" for BOOLEAN MODE */
    private function toBooleanQuery(string $q): string
    {
        $tokens = preg_split('/\s+/', trim($q)) ?: [];
        $tokens = array_filter($tokens, fn ($t) => mb_strlen($t) >= 3);
        if (empty($tokens)) {
            return '';
        }

        return collect($tokens)->map(fn ($t) => '+'.$t.'*')->implode(' ');
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('perPage', 10);
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status'); // New | Pending | In Progress | Completed | Cancelled | Closed | null
        $allowedStatuses = TicketStatus::values();
        if (! in_array($status, $allowedStatuses, true)) {
            $status = null;
        }
        $division = trim((string) $request->input('division', '')); // "1","2","3","4","FAD","OTHERS",""
        $preset = trim((string) $request->input('preset', '')); // "assigned_to_me", "overdue", "awaiting_rating", "unassigned"
        $assignedStaff = trim((string) $request->input('assigned_staff', ''));
        $currentUserName = Auth::user()?->name;

        // NEW: Date filters & Quick ranges
        $datePreset = trim((string) $request->input('date_preset', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        if ($datePreset === 'today') {
            $dateFrom = now()->toDateString();
            $dateTo = now()->toDateString();
        } elseif ($datePreset === 'yesterday') {
            $dateFrom = now()->subDay()->toDateString();
            $dateTo = now()->subDay()->toDateString();
        } elseif ($datePreset === 'last_7_days') {
            $dateFrom = now()->subDays(6)->toDateString();
            $dateTo = now()->toDateString();
        } elseif ($datePreset === 'this_month') {
            $dateFrom = now()->startOfMonth()->toDateString();
            $dateTo = now()->endOfMonth()->toDateString();
        }

        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $month = $request->filled('month') ? (int) $request->input('month') : null;      // 1..12
        $quarter = trim((string) $request->input('quarter', ''));                           // Q1..Q4
        $monthsCsv = trim((string) $request->input('months', ''));                            // e.g. "1,2,5"
        $monthsArr = [];
        if ($monthsCsv !== '') {
            $monthsArr = array_values(array_filter(array_map(function ($v) {
                $n = (int) trim($v);

                return ($n >= 1 && $n <= 12) ? $n : null;
            }, explode(',', $monthsCsv))));
        }

        $searchLen = mb_strlen($search);
        $booleanQ = $this->toBooleanQuery($search);

        // Map numeric codes to labels
        $DIVISION_MAP = [
            '1' => 'FAD',
            '2' => 'IRAD',
            '3' => 'CRPD',
            '4' => 'OD-MISPS',
        ];
        $KNOWN_DIVISIONS = array_merge(array_keys($DIVISION_MAP), array_values($DIVISION_MAP));

        // Table names (safer for joins)
        $clientTbl = (new TicketClientDetail)->getTable(); // e.g., v2_ticket_client_details
        $detailTbl = (new TicketDetail)->getTable();       // e.g., v2_ticket_details

        // --- Helper: apply the SAME date predicates to a joined query using alias d ---
        $applyDateFiltersJoin = function ($q) use ($year, $month, $quarter, $monthsArr, $dateFrom, $dateTo) {
            if ($dateFrom) {
                $q->whereDate('d.date_requested', '>=', $dateFrom);
            }
            if ($dateTo) {
                $q->whereDate('d.date_requested', '<=', $dateTo);
            }
            if ($year) {
                $q->whereYear('d.date_requested', $year);
            }
            if (! empty($monthsArr)) {
                $q->whereIn(\DB::raw('MONTH(d.date_requested)'), $monthsArr);
            } elseif ($quarter !== '') {
                $ranges = ['Q1' => [1, 3], 'Q2' => [4, 6], 'Q3' => [7, 9], 'Q4' => [10, 12]];
                if (isset($ranges[$quarter])) {
                    [$m1, $m2] = $ranges[$quarter];
                    $q->whereBetween(\DB::raw('MONTH(d.date_requested)'), [$m1, $m2]);
                }
            } elseif ($month) {
                $q->whereMonth('d.date_requested', $month);
            }
        };

        // --- Helper: apply the SAME date predicates to a relation ('detail') ---
        $applyDateFiltersRel = function ($d) use ($year, $month, $quarter, $monthsArr, $dateFrom, $dateTo) {
            if ($dateFrom) {
                $d->whereDate('date_requested', '>=', $dateFrom);
            }
            if ($dateTo) {
                $d->whereDate('date_requested', '<=', $dateTo);
            }
            if ($year) {
                $d->whereYear('date_requested', $year);
            }
            if (! empty($monthsArr)) {
                $d->whereIn(\DB::raw('MONTH(date_requested)'), $monthsArr);
            } elseif ($quarter !== '') {
                $ranges = ['Q1' => [1, 3], 'Q2' => [4, 6], 'Q3' => [7, 9], 'Q4' => [10, 12]];
                if (isset($ranges[$quarter])) {
                    [$m1, $m2] = $ranges[$quarter];
                    $d->whereBetween(\DB::raw('MONTH(date_requested)'), [$m1, $m2]);
                }
            } elseif ($month) {
                $d->whereMonth('date_requested', $month);
            }
        };

        // -----------------------------
        // Listing query
        // -----------------------------
        $query = TicketClientDetail::query()
            ->select([
                'client_id',
                'client_referenceNo',
                'client_name',
                'client_type',
                'client_division_agency',
                'created_at',
            ])
            ->withCount('messages')
            ->with([
                'latestMessage',
                'detail' => function ($q) {
                    $q->select([
                        'ticket_id',
                        'client_referenceNo',
                        'ticket_type',
                        'ticket_nature_of_support',
                        'ticket_support_type',
                        'ticket_assigned_by',
                        'ticket_assigned_staff',
                        'ticket_attended_by',
                        'ticket_assisted_by',
                        'ticket_status',
                        'ticket_problem_that_needed_support',
                        'ticket_actions_taken',
                        'ticket_remarks',
                        'date_requested',
                        'date_completed',
                        'created_at',
                    ]);
                },
            ])

            // fast status filter
            ->when($status, function ($q) use ($status) {
                $q->whereHas('detail', fn ($d) => $d->where('ticket_status', $status));
            })

            // division filter
            ->when($division !== '', function ($q) use ($division, $DIVISION_MAP, $KNOWN_DIVISIONS) {
                if (strcasecmp($division, 'OTHERS') === 0) {
                    $q->where(function ($qq) use ($KNOWN_DIVISIONS) {
                        $qq->whereNull('client_division_agency')
                            ->orWhere('client_division_agency', '')
                            ->orWhereNotIn('client_division_agency', $KNOWN_DIVISIONS);
                    });
                } else {
                    $label = $DIVISION_MAP[$division] ?? $division;
                    $q->where(function ($qq) use ($division, $label) {
                        $qq->where('client_division_agency', $division)
                            ->orWhere('client_division_agency', $label);
                    });
                }
            })

            // search
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.$search.'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('client_referenceNo', 'like', $term)
                        ->orWhere('client_name', 'like', $term)
                        ->orWhere('client_empID', 'like', $term)
                        ->orWhere('client_division_agency', 'like', $term)
                        ->orWhereHas('detail', function ($d) use ($term) {
                            $d->where('client_referenceNo', 'like', $term)
                                ->orWhere('ticket_support_type', 'like', $term)
                                ->orWhere('ticket_nature_of_support', 'like', $term)
                                ->orWhere('ticket_problem_that_needed_support', 'like', $term)
                                ->orWhere('ticket_assigned_staff', 'like', $term)
                                ->orWhere('ticket_remarks', 'like', $term);
                        });
                });
            })

            // NEW: date filters applied to the relation (mirrors the counts/base)
            ->when($dateFrom || $dateTo || $year || $month || $quarter || ! empty($monthsArr), function ($q) use ($applyDateFiltersRel) {
                $q->whereHas('detail', $applyDateFiltersRel);
            })

            // NEW: preset filters
            ->when($preset === 'assigned_to_me' && ! empty($currentUserName), function ($q) use ($currentUserName) {
                $q->whereHas('detail', function ($d) use ($currentUserName) {
                    $d->where('ticket_assigned_staff', $currentUserName)
                        ->orWhere('ticket_assisted_by', 'like', "%{$currentUserName}%");
                });
            })
            ->when($preset === 'overdue', function ($q) {
                $cutoff = now()->subHours(24)->toDateTimeString();
                $q->whereHas('detail', function ($d) use ($cutoff) {
                    $d->whereIn('ticket_status', ['New', 'Pending', 'In Progress'])
                        ->where(function ($sub) use ($cutoff) {
                            $sub->where('date_requested', '<=', $cutoff)
                                ->orWhere(function ($s2) use ($cutoff) {
                                    $s2->whereNull('date_requested')
                                        ->where('created_at', '<=', $cutoff);
                                });
                        });
                });
            })
            ->when($preset === 'awaiting_rating', function ($q) {
                $q->whereHas('detail', fn ($d) => $d->where('ticket_status', 'Completed'));
            })
            ->when($preset === 'unassigned', function ($q) {
                $q->whereHas('detail', function ($d) {
                    $d->where('ticket_status', 'New')
                        ->orWhereNull('ticket_assigned_staff')
                        ->orWhereRaw("TRIM(ticket_assigned_staff) = ''");
                });
            })

            // NEW: direct assigned_staff filter
            ->when($assignedStaff !== '', function ($q) use ($assignedStaff) {
                $q->whereHas('detail', function ($d) use ($assignedStaff) {
                    $d->where('ticket_assigned_staff', $assignedStaff)
                        ->orWhere('ticket_assisted_by', 'like', "%{$assignedStaff}%");
                });
            });

        // Column sorting
        $sort = trim((string) $request->input('sort', ''));
        $direction = strtolower(trim((string) $request->input('direction', 'desc'))) === 'asc' ? 'asc' : 'desc';

        if ($sort === 'client_referenceNo') {
            $query->orderBy('client_referenceNo', $direction);
        } elseif ($sort === 'client_name') {
            $query->orderBy('client_name', $direction);
        } elseif ($sort === 'client_division_agency') {
            $query->orderBy('client_division_agency', $direction);
        } elseif ($sort === 'date_requested') {
            $query->orderBy(
                TicketDetail::select('date_requested')
                    ->whereColumn('v2_ticket_details.client_referenceNo', 'v2_ticket_client_details.client_referenceNo')
                    ->limit(1),
                $direction
            );
        } elseif ($sort === 'ticket_status') {
            $query->orderBy(
                TicketDetail::select('ticket_status')
                    ->whereColumn('v2_ticket_details.client_referenceNo', 'v2_ticket_client_details.client_referenceNo')
                    ->limit(1),
                $direction
            );
        } elseif ($sort === 'client_id' || $sort === 'id') {
            $query->orderBy('client_id', $direction);
        } else {
            $query->latest('created_at');
        }

        $tickets = $query->paginate($perPage)->withQueryString();

        // -----------------------------------------
        // COUNTS: base (no status or preset filter) with SAME predicates
        // -----------------------------------------
        $base = TicketClientDetail::query()
            ->from($clientTbl.' as c')
            ->join($detailTbl.' as d', 'd.client_referenceNo', '=', 'c.client_referenceNo')

            // division filter (same as above)
            ->when($division !== '', function ($q) use ($division, $DIVISION_MAP, $KNOWN_DIVISIONS) {
                if (strcasecmp($division, 'OTHERS') === 0) {
                    $q->where(function ($qq) use ($KNOWN_DIVISIONS) {
                        $qq->whereNull('c.client_division_agency')
                            ->orWhere('c.client_division_agency', '')
                            ->orWhereNotIn('c.client_division_agency', $KNOWN_DIVISIONS);
                    });
                } else {
                    $label = $DIVISION_MAP[$division] ?? $division;
                    $q->where(function ($qq) use ($division, $label) {
                        $qq->where('c.client_division_agency', $division)
                            ->orWhere('c.client_division_agency', $label);
                    });
                }
            })

            // search (mirror logic using aliases)
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.$search.'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('c.client_referenceNo', 'like', $term)
                        ->orWhere('c.client_name', 'like', $term)
                        ->orWhere('c.client_empID', 'like', $term)
                        ->orWhere('c.client_division_agency', 'like', $term)
                        ->orWhere('d.client_referenceNo', 'like', $term)
                        ->orWhere('d.ticket_support_type', 'like', $term)
                        ->orWhere('d.ticket_nature_of_support', 'like', $term)
                        ->orWhere('d.ticket_problem_that_needed_support', 'like', $term)
                        ->orWhere('d.ticket_assigned_staff', 'like', $term)
                        ->orWhere('d.ticket_remarks', 'like', $term);
                });
            })

            ->when($assignedStaff !== '', function ($q) use ($assignedStaff) {
                $q->where(function ($sub) use ($assignedStaff) {
                    $sub->where('d.ticket_assigned_staff', $assignedStaff)
                        ->orWhere('d.ticket_assisted_by', 'like', "%{$assignedStaff}%");
                });
            });

        // NEW: date filters (same as listing), but on the JOINed query (alias d)
        $applyDateFiltersJoin($base);

        $cutoff = now()->subHours(24)->toDateTimeString();
        $safeCurrentUser = ! empty($currentUserName) ? addslashes($currentUserName) : '';

        $countsRow = (clone $base)
            ->selectRaw("
            COUNT(*) as all_count,
            SUM(CASE WHEN d.ticket_status = 'New' THEN 1 ELSE 0 END)          as new_count,
            SUM(CASE WHEN d.ticket_status = 'Pending' THEN 1 ELSE 0 END)      as pending_count,
            SUM(CASE WHEN d.ticket_status = 'In Progress' THEN 1 ELSE 0 END)  as inprogress_count,
            SUM(CASE WHEN d.ticket_status = 'Completed' THEN 1 ELSE 0 END)    as completed_count,
            SUM(CASE WHEN d.ticket_status = 'Cancelled' THEN 1 ELSE 0 END)    as cancelled_count,
            SUM(CASE WHEN d.ticket_status = 'Closed' THEN 1 ELSE 0 END)       as closed_count,
            SUM(CASE WHEN d.ticket_status IN ('New', 'Pending', 'In Progress') AND (d.date_requested <= '{$cutoff}' OR (d.date_requested IS NULL AND d.created_at <= '{$cutoff}')) THEN 1 ELSE 0 END) as overdue_count,
            SUM(CASE WHEN d.ticket_status = 'New' OR d.ticket_assigned_staff IS NULL OR TRIM(d.ticket_assigned_staff) = '' THEN 1 ELSE 0 END) as unassigned_count
            ".($safeCurrentUser ? ", SUM(CASE WHEN d.ticket_assigned_staff = '{$safeCurrentUser}' OR d.ticket_assisted_by LIKE '%{$safeCurrentUser}%' THEN 1 ELSE 0 END) as assigned_to_me_count" : ', 0 as assigned_to_me_count').'
        ')
            ->first();

        $counts = [
            'All' => (int) ($countsRow->all_count ?? 0),
            'New' => (int) ($countsRow->new_count ?? 0),
            'Pending' => (int) ($countsRow->pending_count ?? 0),
            'In Progress' => (int) ($countsRow->inprogress_count ?? 0),
            'Completed' => (int) ($countsRow->completed_count ?? 0),
            'Cancelled' => (int) ($countsRow->cancelled_count ?? 0),
            'Closed' => (int) ($countsRow->closed_count ?? 0),
            'presets' => [
                'assigned_to_me' => (int) ($countsRow->assigned_to_me_count ?? 0),
                'overdue' => (int) ($countsRow->overdue_count ?? 0),
                'awaiting_rating' => (int) ($countsRow->completed_count ?? 0),
                'unassigned' => (int) ($countsRow->unassigned_count ?? 0),
            ],
        ];

        return Inertia::render('TicketsV2/All', [ // <- ensure this matches your Vue path
            'tickets' => $tickets,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'division' => $division,
                'preset' => $preset,
                'assigned_staff' => $assignedStaff,
                'perPage' => $perPage,
                'sort' => $sort,
                'direction' => $direction,

                // Quick Date Range filters
                'date_preset' => $datePreset,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,

                // NEW: reflect date filters for UI sync
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
                'months' => implode(',', $monthsArr),
            ],
            'counts' => $counts,
        ]);
    }

    public function create()
    {
        return Inertia::render('TicketsV2/CreateTicket');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_name' => 'required|string',
            'client_type' => 'required|in:Internal,External',
            'client_division_agency' => 'required|string',
            'client_empID' => 'nullable|string',
            'client_sex' => 'required|in:Female,Male,Prefer not to say',

            // Ticket Details
            'ticket_type' => 'required|in:Technical Support,Livestream',
            'ticket_nature_of_support' => 'required|in:Hardware,Software,Hardware & Software,System Modification,Others',
            'ticket_support_type' => 'nullable|string',
            'ticket_attended_by' => 'nullable|string',
            'ticket_assisted_by' => 'nullable|string',
            'ticket_problem_that_needed_support' => 'nullable|string',
            'ticket_actions_taken' => 'nullable|string',
            'ticket_remarks' => 'nullable|string',

            // Dates
            'date_requested' => 'required|date',
            'date_completed' => 'nullable|date',
        ]);

        // Extract these early and store in variables
        $clientName = $request->input('client_name');
        $attendedBy = $request->input('ticket_attended_by');
        $dateRequested = $request->input('date_requested');
        $dateCompleted = $request->input('date_completed');

        $datePrefix = \Carbon\Carbon::parse($dateRequested)->format('Ymd');
        $countForDate = TicketDetail::where('client_referenceNo', 'like', $datePrefix.'-%')->count() + 1;
        $reference = $datePrefix.'-'.str_pad($countForDate, 3, '0', STR_PAD_LEFT);

        // Add reference number to validated data
        $validated['client_referenceNo'] = $reference;

        // Save client and ticket details
        TicketClientDetail::create($validated);

        // ✅ Force NA when nature is "Others"
        $supportType = $request->ticket_nature_of_support === 'Others'
            ? 'NA'
            : ($request->ticket_support_type ?? null);

        TicketDetail::create([
            'client_referenceNo' => $reference,
            'ticket_type' => $request->ticket_type,
            'ticket_nature_of_support' => $request->ticket_nature_of_support,
            'ticket_support_type' => $supportType,
            'ticket_assigned_staff' => $attendedBy,
            'ticket_attended_by' => $attendedBy,
            'ticket_assisted_by' => $request->ticket_assisted_by,
            'ticket_problem_that_needed_support' => $request->ticket_problem_that_needed_support,
            'ticket_actions_taken' => $request->ticket_actions_taken,
            'ticket_remarks' => $request->ticket_remarks,
            'ticket_status' => 'Completed',
            'date_requested' => $dateRequested,
            'date_completed' => $dateCompleted,
        ]);

        // Save activity logs
        $logs = [
            [
                'activity_name' => 'Ticket Created',
                'activity_date' => $dateRequested,
                'activity_user' => $clientName,
            ],
            [
                'activity_name' => 'Ticket Assigned',
                'activity_date' => $dateRequested,
                'activity_user' => $attendedBy,
            ],
            [
                'activity_name' => 'Ticket Attended',
                'activity_date' => $dateRequested,
                'activity_user' => $attendedBy,
            ],
            [
                'activity_name' => 'Ticket Completed',
                'activity_date' => $dateCompleted,
                'activity_user' => $attendedBy,
            ],
        ];

        foreach ($logs as $log) {
            TicketActivityLog::create([
                'client_referenceNo' => $reference,
                'activity_name' => $log['activity_name'],
                'activity_date' => $log['activity_date'],
                'activity_user' => $log['activity_user'],
            ]);
        }

        return redirect('/tickets-v2/data')->with('success', 'Ticket created!');
    }

    public function getEmployees()
    {
        $employees = TicketEmployee::select(
            'id',
            \DB::raw("CONCAT(lname, ', ', fname) AS full_name"),
            'employee_code',
            'division_id',
            'sex'
        )
            ->orderBy('lname')
            ->get();

        return response()->json($employees);
    }

    public function destroy($id)
    {
        if (! Auth::check() || Auth::user()->role !== 'Admin') {
            abort(403, 'Only Admin can delete tickets.');
        }

        DB::transaction(function () use ($id) {
            // Load the ticket and its reference number
            $ticket = TicketClientDetail::with('detail')->findOrFail($id);
            $ref = $ticket->client_referenceNo;

            // 1) delete logs for this reference
            TicketActivityLog::where('client_referenceNo', $ref)->delete();

            // 2) delete child ticket detail first (FK safety)
            optional($ticket->detail)->delete();

            // 3) delete parent
            $ticket->delete();
        });

        return back()->with('success', 'Ticket and related logs deleted.');
    }

    public function myTickets(Request $request)
    {
        $perPage = (int) $request->input('perPage', 10);
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $allowedStatuses = ['New', 'Pending', 'In Progress', 'Completed'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = null;
        }
        $division = trim((string) $request->input('division', ''));

        $searchLen = mb_strlen($search);
        $booleanQ = $this->toBooleanQuery($search);

        $DIVISION_MAP = [
            '1' => 'FAD',
            '2' => 'IRAD',
            '3' => 'CRPD',
            '4' => 'OD-MISPS',
        ];
        $KNOWN_DIVISIONS = array_merge(array_keys($DIVISION_MAP), array_values($DIVISION_MAP));

        $query = TicketClientDetail::query()
            ->select([
                'client_id',
                'client_referenceNo',
                'client_name',
                'client_type',
                'client_division_agency',
                'created_at',
            ])
            ->with(['detail' => function ($q) {
                $q->select([
                    'ticket_id',
                    'client_referenceNo',
                    'ticket_type',
                    'ticket_nature_of_support',
                    'ticket_support_type',
                    'ticket_assigned_by',
                    'ticket_assigned_staff',
                    'ticket_attended_by',
                    'ticket_assisted_by',
                    'ticket_status',
                    'ticket_problem_that_needed_support',
                    'ticket_actions_taken',
                    'ticket_remarks',
                    'date_requested',
                    'date_completed',
                    'created_at',
                ]);
            }])

            // ✅ Only tickets assigned to logged-in user
            ->whereHas('detail', function ($q) {
                $q->where('ticket_assigned_staff', auth()->user()->name);
                // or auth()->id() if you store staff by ID instead of name
            })
            ->whereHas('detail', fn ($q) => $q->where('ticket_status', '<>', 'Closed'))

            // status filter
            ->when($status, function ($q) use ($status) {
                $q->whereHas('detail', fn ($d) => $d->where('ticket_status', $status));
            })

            // division filter
            ->when($division !== '', function ($q) use ($division, $DIVISION_MAP, $KNOWN_DIVISIONS) {
                if (strcasecmp($division, 'OTHERS') === 0) {
                    $q->where(function ($qq) use ($KNOWN_DIVISIONS) {
                        $qq->whereNull('client_division_agency')
                            ->orWhere('client_division_agency', '')
                            ->orWhereNotIn('client_division_agency', $KNOWN_DIVISIONS);
                    });
                } else {
                    $label = $DIVISION_MAP[$division] ?? $division;
                    $q->where(function ($qq) use ($division, $label) {
                        $qq->where('client_division_agency', $division)
                            ->orWhere('client_division_agency', $label);
                    });
                }
            })

            // search filter
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.$search.'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('client_referenceNo', 'like', $term)
                        ->orWhere('client_name', 'like', $term)
                        ->orWhere('client_empID', 'like', $term)
                        ->orWhere('client_division_agency', 'like', $term)
                        ->orWhereHas('detail', function ($d) use ($term) {
                            $d->where('client_referenceNo', 'like', $term)
                                ->orWhere('ticket_support_type', 'like', $term)
                                ->orWhere('ticket_nature_of_support', 'like', $term)
                                ->orWhere('ticket_problem_that_needed_support', 'like', $term)
                                ->orWhere('ticket_assigned_staff', 'like', $term)
                                ->orWhere('ticket_remarks', 'like', $term);
                        });
                });
            })
            ->latest('created_at');

        $tickets = $query->paginate($perPage)->withQueryString();

        return Inertia::render('TicketsV2/MyTickets', [
            'tickets' => $tickets,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'division' => $division,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function postStaffMessage(Request $request, string $ref)
    {
        $validated = $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ]);

        $exists = DB::table('v2_ticket_details')->where('client_referenceNo', $ref)->exists();
        abort_unless($exists, 404, 'Ticket not found');

        $staffName = Auth::user()?->name ?? 'Staff';

        DB::table('v2_ticket_messages')->insert([
            'client_referenceNo' => $ref,
            'sender_type' => 'staff',
            'sender_name' => $staffName,
            'message' => $validated['message'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TicketActivityLog::create([
            'client_referenceNo' => $ref,
            'activity_name' => 'Staff Message',
            'activity_date' => now(),
            'activity_user' => $staffName,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Export filtered tickets to CSV.
     */
    public function export(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $allowedStatuses = TicketStatus::values();
        if (! in_array($status, $allowedStatuses, true)) {
            $status = null;
        }
        $division = trim((string) $request->input('division', ''));
        $assignedStaff = trim((string) $request->input('assigned_staff', ''));

        $datePreset = trim((string) $request->input('date_preset', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        if ($datePreset === 'today') {
            $dateFrom = now()->toDateString();
            $dateTo = now()->toDateString();
        } elseif ($datePreset === 'yesterday') {
            $dateFrom = now()->subDay()->toDateString();
            $dateTo = now()->subDay()->toDateString();
        } elseif ($datePreset === 'last_7_days') {
            $dateFrom = now()->subDays(6)->toDateString();
            $dateTo = now()->toDateString();
        } elseif ($datePreset === 'this_month') {
            $dateFrom = now()->startOfMonth()->toDateString();
            $dateTo = now()->endOfMonth()->toDateString();
        }

        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $quarter = trim((string) $request->input('quarter', ''));
        $monthsCsv = trim((string) $request->input('months', ''));
        $monthsArr = [];
        if ($monthsCsv !== '') {
            $monthsArr = array_values(array_filter(array_map(function ($v) {
                $n = (int) trim($v);

                return ($n >= 1 && $n <= 12) ? $n : null;
            }, explode(',', $monthsCsv))));
        }

        $searchLen = mb_strlen($search);
        $booleanQ = $this->toBooleanQuery($search);

        $DIVISION_MAP = [
            '1' => 'FAD',
            '2' => 'IRAD',
            '3' => 'CRPD',
            '4' => 'OD-MISPS',
        ];
        $KNOWN_DIVISIONS = array_merge(array_keys($DIVISION_MAP), array_values($DIVISION_MAP));

        $applyDateFiltersRel = function ($d) use ($year, $month, $quarter, $monthsArr, $dateFrom, $dateTo) {
            if ($dateFrom) {
                $d->whereDate('date_requested', '>=', $dateFrom);
            }
            if ($dateTo) {
                $d->whereDate('date_requested', '<=', $dateTo);
            }
            if ($year) {
                $d->whereYear('date_requested', $year);
            }
            if (! empty($monthsArr)) {
                $d->whereIn(\DB::raw('MONTH(date_requested)'), $monthsArr);
            } elseif ($quarter !== '') {
                $ranges = ['Q1' => [1, 3], 'Q2' => [4, 6], 'Q3' => [7, 9], 'Q4' => [10, 12]];
                if (isset($ranges[$quarter])) {
                    [$m1, $m2] = $ranges[$quarter];
                    $d->whereBetween(\DB::raw('MONTH(date_requested)'), [$m1, $m2]);
                }
            } elseif ($month) {
                $d->whereMonth('date_requested', $month);
            }
        };

        $query = TicketClientDetail::query()
            ->select([
                'client_id',
                'client_referenceNo',
                'client_name',
                'client_type',
                'client_division_agency',
                'created_at',
            ])
            ->with(['detail', 'rating'])
            ->when($status, function ($q) use ($status) {
                $q->whereHas('detail', fn ($d) => $d->where('ticket_status', $status));
            })
            ->when($division !== '', function ($q) use ($division, $DIVISION_MAP, $KNOWN_DIVISIONS) {
                if (strcasecmp($division, 'OTHERS') === 0) {
                    $q->where(function ($qq) use ($KNOWN_DIVISIONS) {
                        $qq->whereNull('client_division_agency')
                            ->orWhere('client_division_agency', '')
                            ->orWhereNotIn('client_division_agency', $KNOWN_DIVISIONS);
                    });
                } else {
                    $label = $DIVISION_MAP[$division] ?? $division;
                    $q->where(function ($qq) use ($division, $label) {
                        $qq->where('client_division_agency', $division)
                            ->orWhere('client_division_agency', $label);
                    });
                }
            })
            ->when($assignedStaff !== '', function ($q) use ($assignedStaff) {
                $q->whereHas('detail', function ($d) use ($assignedStaff) {
                    $d->where('ticket_assigned_staff', $assignedStaff)
                        ->orWhere('ticket_assisted_by', 'like', "%{$assignedStaff}%");
                });
            })
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.$search.'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('client_referenceNo', 'like', $term)
                        ->orWhere('client_name', 'like', $term)
                        ->orWhere('client_empID', 'like', $term)
                        ->orWhere('client_division_agency', 'like', $term)
                        ->orWhereHas('detail', function ($d) use ($term) {
                            $d->where('client_referenceNo', 'like', $term)
                                ->orWhere('ticket_support_type', 'like', $term)
                                ->orWhere('ticket_nature_of_support', 'like', $term)
                                ->orWhere('ticket_problem_that_needed_support', 'like', $term)
                                ->orWhere('ticket_assigned_staff', 'like', $term)
                                ->orWhere('ticket_remarks', 'like', $term);
                        });
                });
            })
            ->when($dateFrom || $dateTo || $year || $month || $quarter || ! empty($monthsArr), function ($q) use ($applyDateFiltersRel) {
                $q->whereHas('detail', $applyDateFiltersRel);
            });

        // Filter by selected IDs if supplied
        $ids = $request->input('ids');
        if (! empty($ids)) {
            $idsArr = is_array($ids) ? $ids : explode(',', (string) $ids);
            $query->where(function ($q) use ($idsArr) {
                $q->whereIn('client_id', $idsArr)
                    ->orWhereIn('client_referenceNo', $idsArr);
            });
        }

        $sort = trim((string) $request->input('sort', ''));
        $direction = strtolower(trim((string) $request->input('direction', 'desc'))) === 'asc' ? 'asc' : 'desc';

        if ($sort === 'client_referenceNo') {
            $query->orderBy('client_referenceNo', $direction);
        } elseif ($sort === 'client_name') {
            $query->orderBy('client_name', $direction);
        } else {
            $query->latest('created_at');
        }

        $tickets = $query->get();

        $filename = 'tickets_export_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($tickets) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, [
                'Reference Code',
                'Client Name',
                'Division/Agency',
                'Date Requested',
                'Date Completed',
                'Turnaround Time (TAT)',
                'Status',
                'Request Type',
                'Nature of Support',
                'Support Type',
                'Assigned Staff',
                'Attended By',
                'Problem that Needed Support',
                'Actions Taken',
                'Remarks',
                'Rating Result',
            ]);

            foreach ($tickets as $ticket) {
                $detail = $ticket->detail;
                $rating = $ticket->rating;

                $tat = '—';
                if ($detail?->date_requested && $detail?->date_completed) {
                    $start = \Illuminate\Support\Carbon::parse($detail->date_requested);
                    $end = \Illuminate\Support\Carbon::parse($detail->date_completed);
                    $totalHours = $start->diffInHours($end);
                    if ($totalHours < 24) {
                        $tat = $totalHours.'h';
                    } else {
                        $days = floor($totalHours / 24);
                        $remHours = $totalHours % 24;
                        $tat = "{$days}d {$remHours}h";
                    }
                }

                fputcsv($out, [
                    $ticket->client_referenceNo,
                    $ticket->client_name,
                    $ticket->client_division_agency,
                    $detail?->date_requested ? \Illuminate\Support\Carbon::parse($detail->date_requested)->format('Y-m-d H:i') : ($ticket->created_at ? $ticket->created_at->format('Y-m-d H:i') : ''),
                    $detail?->date_completed ? \Illuminate\Support\Carbon::parse($detail->date_completed)->format('Y-m-d H:i') : '',
                    $tat,
                    $detail?->ticket_status ?? 'New',
                    $detail?->ticket_type ?? '',
                    $detail?->ticket_nature_of_support ?? '',
                    $detail?->ticket_support_type ?? '',
                    $detail?->ticket_assigned_staff ?? '',
                    $detail?->ticket_attended_by ?? '',
                    $detail?->ticket_problem_that_needed_support ?? '',
                    $detail?->ticket_actions_taken ?? '',
                    $detail?->ticket_remarks ?? '',
                    $rating?->rating_result ?? ($detail?->ticket_status === 'Completed' || $detail?->ticket_status === 'Closed' ? 'Unrated' : ''),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Bulk assign staff to multiple tickets.
     */
    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'ticket_ids' => 'required|array|min:1',
            'ticket_ids.*' => 'required',
            'assigned_staff' => 'required|string|max:255',
            'ticket_type' => 'nullable|string|max:255',
        ]);

        $staff = trim($validated['assigned_staff']);
        $type = $validated['ticket_type'] ?? null;
        $assignedBy = Auth::user()?->name ?? Auth::user()?->username ?? 'Staff';

        DB::transaction(function () use ($validated, $staff, $type, $assignedBy): void {
            $details = TicketDetail::whereIn('ticket_id', $validated['ticket_ids'])
                ->orWhereIn('client_referenceNo', $validated['ticket_ids'])
                ->get();

            foreach ($details as $detail) {
                $updates = [
                    'ticket_assigned_staff' => $staff,
                    'ticket_status' => (strcasecmp((string) $detail->ticket_status, TicketStatus::New->value) === 0 || empty($detail->ticket_status))
                        ? TicketStatus::Pending->value
                        : $detail->ticket_status,
                    'ticket_assigned_by' => $assignedBy,
                ];
                if ($type) {
                    $updates['ticket_type'] = $type;
                }
                $detail->update($updates);

                TicketActivityLog::create([
                    'client_referenceNo' => $detail->client_referenceNo,
                    'activity_name' => "Assigned to {$staff} (Bulk)",
                    'activity_date' => now(),
                    'activity_user' => $assignedBy,
                ]);
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => count($validated['ticket_ids']).' tickets assigned to '.$staff.' successfully.',
            ]);
        }

        return back()->with('success', count($validated['ticket_ids']).' tickets assigned to '.$staff.' successfully.');
    }

    /**
     * Bulk close completed tickets.
     */
    public function bulkClose(Request $request)
    {
        $validated = $request->validate([
            'ticket_ids' => 'required|array|min:1',
            'ticket_ids.*' => 'required',
            'ticket_remarks' => 'nullable|string|max:2000',
        ]);

        $remarks = trim($validated['ticket_remarks'] ?? '');
        $user = Auth::user()?->name ?? Auth::user()?->username ?? 'Staff';

        $closedCount = 0;
        DB::transaction(function () use ($validated, $remarks, $user, &$closedCount): void {
            $details = TicketDetail::where(function ($q) use ($validated) {
                $q->whereIn('ticket_id', $validated['ticket_ids'])
                    ->orWhereIn('client_referenceNo', $validated['ticket_ids']);
            })
                ->where('ticket_status', TicketStatus::Completed->value)
                ->get();

            foreach ($details as $detail) {
                $detail->ticket_status = TicketStatus::Closed->value;
                if ($remarks !== '') {
                    $entry = "Closed (Bulk): {$remarks}";
                    $detail->ticket_remarks = $detail->ticket_remarks
                        ? $detail->ticket_remarks."\n".$entry
                        : $entry;
                }
                $detail->save();

                TicketActivityLog::create([
                    'client_referenceNo' => $detail->client_referenceNo,
                    'activity_name' => 'Ticket Closed (Bulk)',
                    'activity_date' => now(),
                    'activity_user' => $user,
                ]);

                $closedCount++;
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'closed_count' => $closedCount,
                'message' => "{$closedCount} completed tickets have been formally closed.",
            ]);
        }

        return back()->with('success', "{$closedCount} completed tickets have been formally closed.");
    }

    /**
     * Bulk delete tickets (Admin only).
     */
    public function bulkDelete(Request $request)
    {
        if (! Auth::user() || Auth::user()->role !== 'Admin') {
            abort(403, 'Only Admin can delete tickets.');
        }

        $validated = $request->validate([
            'ticket_ids' => 'required|array|min:1',
            'ticket_ids.*' => 'required',
        ]);

        $deletedCount = 0;
        DB::transaction(function () use ($validated, &$deletedCount): void {
            $tickets = TicketClientDetail::with('detail')
                ->where(function ($q) use ($validated) {
                    $q->whereIn('client_id', $validated['ticket_ids'])
                        ->orWhereIn('client_referenceNo', $validated['ticket_ids']);
                })
                ->get();

            foreach ($tickets as $ticket) {
                $ref = $ticket->client_referenceNo;

                // 1) Delete related activity logs
                TicketActivityLog::where('client_referenceNo', $ref)->delete();

                // 2) Delete child detail
                optional($ticket->detail)->delete();

                // 3) Delete parent client record
                $ticket->delete();

                $deletedCount++;
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "{$deletedCount} tickets and related records deleted successfully.",
            ]);
        }

        return back()->with('success', "{$deletedCount} tickets and related records deleted successfully.");
    }

    /**
     * Lightweight heartbeat check for newly arrived tickets and messages.
     * Used by the Dispatch Live Monitor to trigger instant audio/visual notifications.
     */
    public function checkUpdates(Request $request)
    {
        $isInit = $request->boolean('init');
        $hasLastId = $request->has('last_id');
        $hasLastMessageId = $request->has('last_message_id');
        $lastId = $hasLastId ? (int) $request->input('last_id') : 0;
        $lastMessageId = $hasLastMessageId ? (int) $request->input('last_message_id') : 0;
        $currentUserName = Auth::user()?->name;

        // Current max client_id and max message_id
        $latestId = (int) (TicketClientDetail::max('client_id') ?? 0);
        $latestMessageId = (int) (\App\Models\TicketMessage::max('id') ?? 0);

        $newTickets = [];
        if (! $isInit && $hasLastId && $latestId > $lastId) {
            $newTickets = TicketClientDetail::query()
                ->with(['detail', 'latestMessage'])
                ->withCount('messages')
                ->where('client_id', '>', $lastId)
                ->orderBy('client_id', 'desc')
                ->limit(20)
                ->get();
        }

        $newMessages = [];
        if (! $isInit && $hasLastMessageId && $latestMessageId > $lastMessageId) {
            $newMessages = \App\Models\TicketMessage::query()
                ->where('id', '>', $lastMessageId)
                ->where('sender_type', 'client')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get();
        }

        // Fast counts recalculation if updates found or requested
        $counts = null;
        if (! empty($newTickets) || $request->boolean('include_counts')) {
            $detailTbl = (new TicketDetail)->getTable();
            $clientTbl = (new TicketClientDetail)->getTable();
            $cutoff = now()->subHours(24)->toDateTimeString();
            $safeCurrentUser = ! empty($currentUserName) ? addslashes($currentUserName) : '';

            $countsRow = TicketClientDetail::query()
                ->from($clientTbl.' as c')
                ->join($detailTbl.' as d', 'd.client_referenceNo', '=', 'c.client_referenceNo')
                ->selectRaw("
                    COUNT(*) as all_count,
                    SUM(CASE WHEN d.ticket_status = 'New' THEN 1 ELSE 0 END)          as new_count,
                    SUM(CASE WHEN d.ticket_status = 'Pending' THEN 1 ELSE 0 END)      as pending_count,
                    SUM(CASE WHEN d.ticket_status = 'In Progress' THEN 1 ELSE 0 END)  as inprogress_count,
                    SUM(CASE WHEN d.ticket_status = 'Completed' THEN 1 ELSE 0 END)    as completed_count,
                    SUM(CASE WHEN d.ticket_status = 'Cancelled' THEN 1 ELSE 0 END)    as cancelled_count,
                    SUM(CASE WHEN d.ticket_status = 'Closed' THEN 1 ELSE 0 END)       as closed_count,
                    SUM(CASE WHEN d.ticket_status IN ('New', 'Pending', 'In Progress') AND (d.date_requested <= '{$cutoff}' OR (d.date_requested IS NULL AND d.created_at <= '{$cutoff}')) THEN 1 ELSE 0 END) as overdue_count,
                    SUM(CASE WHEN d.ticket_status = 'New' OR d.ticket_assigned_staff IS NULL OR TRIM(d.ticket_assigned_staff) = '' THEN 1 ELSE 0 END) as unassigned_count
                    ".($safeCurrentUser ? ", SUM(CASE WHEN d.ticket_assigned_staff = '{$safeCurrentUser}' OR d.ticket_assisted_by LIKE '%{$safeCurrentUser}%' THEN 1 ELSE 0 END) as assigned_to_me_count" : ', 0 as assigned_to_me_count').'
                ')
                ->first();

            $counts = [
                'All' => (int) ($countsRow->all_count ?? 0),
                'New' => (int) ($countsRow->new_count ?? 0),
                'Pending' => (int) ($countsRow->pending_count ?? 0),
                'In Progress' => (int) ($countsRow->inprogress_count ?? 0),
                'Completed' => (int) ($countsRow->completed_count ?? 0),
                'Cancelled' => (int) ($countsRow->cancelled_count ?? 0),
                'Closed' => (int) ($countsRow->closed_count ?? 0),
                'presets' => [
                    'assigned_to_me' => (int) ($countsRow->assigned_to_me_count ?? 0),
                    'overdue' => (int) ($countsRow->overdue_count ?? 0),
                    'awaiting_rating' => (int) ($countsRow->completed_count ?? 0),
                    'unassigned' => (int) ($countsRow->unassigned_count ?? 0),
                ],
            ];
        }

        return response()->json([
            'latest_id' => $latestId,
            'latest_message_id' => $latestMessageId,
            'has_new_tickets' => ! empty($newTickets) && count($newTickets) > 0,
            'new_tickets' => $newTickets,
            'has_new_messages' => ! empty($newMessages) && count($newMessages) > 0,
            'new_messages' => $newMessages,
            'counts' => $counts,
        ]);
    }
}
