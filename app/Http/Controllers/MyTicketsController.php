<?php

namespace App\Http\Controllers;

use App\Models\TicketClientDetail;
use App\Models\TicketDetail;
use App\Services\CsmSheetChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MyTicketsController extends Controller
{
    /**
     * Convert "john doe" -> "+john* +doe*" for BOOLEAN MODE
     */
    private function toBooleanQuery(string $q): string
    {
        $tokens = preg_split('/\s+/', trim($q)) ?: [];
        $tokens = array_filter($tokens, fn ($t) => mb_strlen($t) >= 3);
        if (empty($tokens)) {
            return '';
        }

        return collect($tokens)->map(fn ($t) => '+'.$t.'*')->implode(' ');
    }

    /**
     * List tickets assigned to the logged-in user (ticket_assigned_staff).
     */
    public function index(Request $request, CsmSheetChecker $checker)
    {
        $perPage = (int) $request->input('perPage', 10);
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status'); // New | Pending | In Progress | Completed | null
        $division = trim((string) $request->input('division', ''));
        $preset = trim((string) $request->input('preset', ''));

        $datePreset = trim((string) $request->input('date_preset', ''));
        $rawDateFrom = trim((string) $request->input('date_from', ''));
        $rawDateTo = trim((string) $request->input('date_to', ''));
        $dateFrom = $rawDateFrom;
        $dateTo = $rawDateTo;

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
                $d->whereIn(DB::raw('MONTH(date_requested)'), $monthsArr);
            } elseif ($quarter !== '') {
                $ranges = ['Q1' => [1, 3], 'Q2' => [4, 6], 'Q3' => [7, 9], 'Q4' => [10, 12]];
                if (isset($ranges[$quarter])) {
                    [$m1, $m2] = $ranges[$quarter];
                    $d->whereBetween(DB::raw('MONTH(date_requested)'), [$m1, $m2]);
                }
            } elseif ($month) {
                $d->whereMonth('date_requested', $month);
            }
        };

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

        // IMPORTANT: Adjust this if you store staff by user_id instead of name
        $assignedValue = auth()->user()->name; // change to auth()->id() if the column stores IDs

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
                    'client_referenceNo', // keep this so the relation can resolve
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
                ])
                    ->with(['event' => function ($e) {
                        // keep columns minimal
                        $e->select([
                            'event_id',
                            'client_referenceNo',
                            'event_start_date',
                            'event_end_date',
                            'event_venue',
                            'event_remarks',
                            'other_request',
                            'created_at',
                            'updated_at',
                        ]);
                    }]);
            },

                'rating' => function ($q) {
                    $q->select([
                        'rating_id',
                        'client_referenceNo',
                        'rating_status',
                        'rating_result',
                        'rating_comment_suggestion',
                        'created_at',
                    ]);
                },
            ])

            // ✅ Only tickets assigned to the logged-in user
            ->whereHas('detail', function ($q) use ($assignedValue) {
                $q->where('ticket_assigned_staff', $assignedValue);
                // If the column stores user_id instead of name, use:
                // $q->where('ticket_assigned_staff', auth()->id());
            })

            // fast status filter
            ->when($status, function ($q) use ($status) {
                $q->whereHas('detail', fn ($d) => $d->where('ticket_status', $status));
            })

            // smart workflow presets
            ->when($preset === 'needs_attention', function ($q) {
                $cutoff = now()->subHours(48)->toDateTimeString();
                $q->whereHas('detail', function ($d) use ($cutoff) {
                    $d->whereIn('ticket_status', ['Pending', 'In Progress'])
                        ->where(function ($sub) use ($cutoff) {
                            $sub->where('date_requested', '<=', $cutoff)
                                ->orWhere(function ($s2) use ($cutoff) {
                                    $s2->whereNull('date_requested')->where('created_at', '<=', $cutoff);
                                });
                        });
                });
            })
            ->when($preset === 'overdue', function ($q) {
                $cutoff = now()->subHours(72)->toDateTimeString();
                $q->whereHas('detail', function ($d) use ($cutoff) {
                    $d->whereIn('ticket_status', ['Pending', 'In Progress'])
                        ->where(function ($sub) use ($cutoff) {
                            $sub->where('date_requested', '<=', $cutoff)
                                ->orWhere(function ($s2) use ($cutoff) {
                                    $s2->whereNull('date_requested')->where('created_at', '<=', $cutoff);
                                });
                        });
                });
            })
            ->when($preset === 'awaiting_rating', function ($q) {
                $q->whereHas('detail', fn ($d) => $d->where('ticket_status', 'Completed'))
                    ->where(function ($sub) {
                        $sub->whereDoesntHave('rating')
                            ->orWhereHas('rating', fn ($r) => $r->where('rating_status', '<>', 'Received')->orWhereNull('rating_result'));
                    });
            })
            ->when($preset === 'completed_today', function ($q) {
                $q->whereHas('detail', fn ($d) => $d->where('ticket_status', 'Completed')->whereDate('date_completed', now()->toDateString()));
            })

            // division filter (supports numeric code or label)
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

            // date filters
            ->when($dateFrom || $dateTo || $year || $month || $quarter || ! empty($monthsArr), function ($q) use ($applyDateFiltersRel) {
                $q->whereHas('detail', $applyDateFiltersRel);
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
            ->latest('created_at'); // index-friendly

        $tickets = $query->paginate($perPage)->withQueryString();

        // ---------- simple per-status stats for header cards ----------
        $rawCounts = TicketDetail::query()
            ->selectRaw('ticket_status, COUNT(*) as cnt')
            ->where('ticket_assigned_staff', $assignedValue)
            ->groupBy('ticket_status')
            ->pluck('cnt', 'ticket_status');

        $ticketStats = [
            'total' => (int) $rawCounts->sum(),
            'new' => (int) ($rawCounts['New'] ?? 0),
            'pending' => (int) ($rawCounts['Pending'] ?? 0),
            'in_progress' => (int) ($rawCounts['In Progress'] ?? 0),
            'completed' => (int) ($rawCounts['Completed'] ?? 0),
            'cancelled' => (int) ($rawCounts['Cancelled'] ?? 0),
            'closed' => (int) ($rawCounts['Closed'] ?? 0),
        ];
        // --------------------------------------------------------------

        $latestRating = DB::table('v2_ticket_rating_details as r1')
            ->select('r1.client_referenceNo', DB::raw('MAX(r1.created_at) as max_created'))
            ->groupBy('r1.client_referenceNo');

        $traySelectColumns = [
            'd.ticket_id',
            'd.client_referenceNo',
            'c.client_id',
            'c.client_name',
            'c.client_division_agency',
            'c.client_type',
            'c.client_empID',
            'c.client_email',
            'd.ticket_type',
            'd.ticket_nature_of_support',
            'd.ticket_support_type',
            'd.ticket_related_type',
            'd.ticket_attended_by',
            'd.ticket_assigned_by',
            'd.ticket_assigned_staff',
            'd.ticket_status',
            'd.ticket_problem_that_needed_support',
            'd.ticket_actions_taken',
            'd.ticket_remarks',
            'd.date_requested',
            'd.date_completed',
            'd.created_at',
            'd.updated_at',
            DB::raw('DATE_FORMAT(e.event_start_date, "%Y-%m-%d") as event_start_date'),
            DB::raw('DATE_FORMAT(e.event_end_date,   "%Y-%m-%d") as event_end_date'),
            'e.event_venue',
            'e.event_remarks',
        ];

        $formatTicketRow = function ($t) {
            $t->detail = (object) [
                'ticket_id' => $t->ticket_id ?? null,
                'client_referenceNo' => $t->client_referenceNo ?? '',
                'ticket_type' => $t->ticket_type ?? null,
                'ticket_nature_of_support' => $t->ticket_nature_of_support ?? null,
                'ticket_support_type' => $t->ticket_support_type ?? null,
                'ticket_related_type' => $t->ticket_related_type ?? null,
                'ticket_attended_by' => $t->ticket_attended_by ?? null,
                'ticket_assigned_by' => $t->ticket_assigned_by ?? null,
                'ticket_assigned_staff' => $t->ticket_assigned_staff ?? null,
                'ticket_status' => $t->ticket_status ?? null,
                'ticket_problem_that_needed_support' => $t->ticket_problem_that_needed_support ?? null,
                'ticket_actions_taken' => $t->ticket_actions_taken ?? null,
                'ticket_remarks' => $t->ticket_remarks ?? null,
                'date_requested' => $t->date_requested ?? null,
                'date_completed' => $t->date_completed ?? null,
                'created_at' => $t->created_at ?? null,
                'updated_at' => $t->updated_at ?? null,
                'client_division_agency' => $t->client_division_agency ?? null,
                'client_type' => $t->client_type ?? null,
                'client_name' => $t->client_name ?? null,
                'client_email' => $t->client_email ?? null,
            ];
            if (isset($t->event_start_date) || isset($t->event_venue) || isset($t->event_remarks)) {
                $t->event = (object) [
                    'event_start_date' => $t->event_start_date ?? null,
                    'event_end_date' => $t->event_end_date ?? null,
                    'event_venue' => $t->event_venue ?? null,
                    'event_remarks' => $t->event_remarks ?? null,
                ];
            }

            return $t;
        };

        $pendingTickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->leftJoin('v2_ticket_events as e', 'e.client_referenceNo', '=', 'd.client_referenceNo')
            ->joinSub($latestRating, 'lr', function ($join) {
                $join->on('lr.client_referenceNo', '=', 'd.client_referenceNo');
            })
            ->join('v2_ticket_rating_details as r', function ($join) {
                $join->on('r.client_referenceNo', '=', 'd.client_referenceNo')
                    ->on('r.created_at', '=', 'lr.max_created');
            })
            ->where('r.rating_status', 'Pending')
            ->where('d.ticket_assigned_staff', $assignedValue) // only my tickets
            ->orderBy('d.created_at', 'desc')
            ->select($traySelectColumns)
            ->get()
            ->map($formatTicketRow);

        $myNewTickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->leftJoin('v2_ticket_events as e', 'e.client_referenceNo', '=', 'd.client_referenceNo')
            ->where('d.ticket_assigned_staff', $assignedValue)
            ->where('d.ticket_status', 'Pending')
            ->orderBy('d.date_requested', 'desc')
            ->select($traySelectColumns)
            ->get()
            ->map($formatTicketRow);

        $myInProgTickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->leftJoin('v2_ticket_events as e', 'e.client_referenceNo', '=', 'd.client_referenceNo')
            ->where('d.ticket_assigned_staff', $assignedValue)
            ->where('d.ticket_status', 'In Progress')
            ->orderBy('d.date_requested', 'desc')
            ->select($traySelectColumns)
            ->get()
            ->map($formatTicketRow);

        $myCompletedTickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->leftJoin('v2_ticket_events as e', 'e.client_referenceNo', '=', 'd.client_referenceNo')
            ->where('d.ticket_assigned_staff', $assignedValue)
            ->where('d.ticket_status', 'Completed')
            ->orderBy('d.date_completed', 'desc')
            ->select($traySelectColumns)
            ->get()
            ->map($formatTicketRow);

        $noRatingTickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->leftJoin('v2_ticket_rating_details as r', 'r.client_referenceNo', '=', 'd.client_referenceNo')
            ->leftJoin('v2_ticket_events as e', 'e.client_referenceNo', '=', 'd.client_referenceNo')
            ->where('d.ticket_assigned_staff', $assignedValue)
            ->where('d.ticket_status', 'Completed')
            ->whereNull('r.client_referenceNo') // anti-join → no rating row yet
            ->orderBy('d.date_completed', 'desc')
            ->select($traySelectColumns)
            ->get()
            ->map($formatTicketRow);

        // Build one hash set of all references present in the Google Sheet
        $inSheet = $checker->referenceSet(); // ['20240119-001' => true, ...]

        // Keep only tickets whose ref is NOT in the sheet
        $noCsmTickets = $myCompletedTickets->filter(function ($t) use ($inSheet) {
            return ! isset($inSheet[$t->client_referenceNo]);
        })->values();

        $warningCutoff = now()->subHours(48)->toDateTimeString();
        $breachCutoff = now()->subHours(72)->toDateTimeString();
        $todayDate = now()->toDateString();

        $needsAttentionCount = DB::table('v2_ticket_details')
            ->where('ticket_assigned_staff', $assignedValue)
            ->whereIn('ticket_status', ['Pending', 'In Progress'])
            ->where(function ($sub) use ($warningCutoff) {
                $sub->where('date_requested', '<=', $warningCutoff)
                    ->orWhere(function ($s2) use ($warningCutoff) {
                        $s2->whereNull('date_requested')->where('created_at', '<=', $warningCutoff);
                    });
            })
            ->count();

        $overdueCount = DB::table('v2_ticket_details')
            ->where('ticket_assigned_staff', $assignedValue)
            ->whereIn('ticket_status', ['Pending', 'In Progress'])
            ->where(function ($sub) use ($breachCutoff) {
                $sub->where('date_requested', '<=', $breachCutoff)
                    ->orWhere(function ($s2) use ($breachCutoff) {
                        $s2->whereNull('date_requested')->where('created_at', '<=', $breachCutoff);
                    });
            })
            ->count();

        $completedTodayCount = DB::table('v2_ticket_details')
            ->where('ticket_assigned_staff', $assignedValue)
            ->where('ticket_status', 'Completed')
            ->whereDate('date_completed', $todayDate)
            ->count();

        $ticketStats['presets'] = [
            'needs_attention' => $needsAttentionCount,
            'overdue' => $overdueCount,
            'awaiting_rating' => (int) $noRatingTickets->count(),
            'completed_today' => $completedTodayCount,
        ];

        return Inertia::render('TicketsV2/MyTicketsV2', [
            'tickets' => $tickets,
            'ticketStats' => $ticketStats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'division' => $division,
                'preset' => $preset,
                'perPage' => $perPage,
                'date_preset' => $datePreset,
                'date_from' => $rawDateFrom,
                'date_to' => $rawDateTo,
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
                'months' => $monthsCsv,
            ],

            'pendingTickets' => $pendingTickets,
            'myNewTickets' => $myNewTickets,
            'myInProgTickets' => $myInProgTickets,
            'noCsmTickets' => $noCsmTickets,
            'noRatingTickets' => $noRatingTickets,
        ]);
    }

    /**
     * Lightweight heartbeat check for newly assigned tickets and status changes.
     */
    public function checkUpdates(Request $request)
    {
        $assignedValue = auth()->user()->name;

        $pendingTickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->where('d.ticket_assigned_staff', $assignedValue)
            ->where('d.ticket_status', 'Pending')
            ->orderBy('d.created_at', 'desc')
            ->select([
                'd.ticket_id',
                'd.client_referenceNo',
                'c.client_name',
                'c.client_division_agency',
                'd.ticket_problem_that_needed_support',
                'd.ticket_nature_of_support',
                'd.created_at',
            ])
            ->get();

        $inProgressCount = DB::table('v2_ticket_details')
            ->where('ticket_assigned_staff', $assignedValue)
            ->where('ticket_status', 'In Progress')
            ->count();

        $completedCount = DB::table('v2_ticket_details')
            ->where('ticket_assigned_staff', $assignedValue)
            ->where('ticket_status', 'Completed')
            ->count();

        return response()->json([
            'pending_count' => $pendingTickets->count(),
            'in_progress_count' => $inProgressCount,
            'completed_count' => $completedCount,
            'pending_tickets' => $pendingTickets,
        ]);
    }

    /**
     * Export technician's assigned tickets to CSV accomplishment report.
     */
    public function export(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $division = trim((string) $request->input('division', ''));
        $preset = trim((string) $request->input('preset', ''));

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
                $d->whereIn(DB::raw('MONTH(date_requested)'), $monthsArr);
            } elseif ($quarter !== '') {
                $ranges = ['Q1' => [1, 3], 'Q2' => [4, 6], 'Q3' => [7, 9], 'Q4' => [10, 12]];
                if (isset($ranges[$quarter])) {
                    [$m1, $m2] = $ranges[$quarter];
                    $d->whereBetween(DB::raw('MONTH(date_requested)'), [$m1, $m2]);
                }
            } elseif ($month) {
                $d->whereMonth('date_requested', $month);
            }
        };

        $searchLen = mb_strlen($search);
        $booleanQ = $this->toBooleanQuery($search);

        $DIVISION_MAP = [
            '1' => 'FAD',
            '2' => 'IRAD',
            '3' => 'CRPD',
            '4' => 'OD-MISPS',
        ];
        $KNOWN_DIVISIONS = array_merge(array_keys($DIVISION_MAP), array_values($DIVISION_MAP));
        $assignedValue = auth()->user()->name;

        $query = TicketClientDetail::query()
            ->select([
                'client_id',
                'client_referenceNo',
                'client_name',
                'client_type',
                'client_division_agency',
                'created_at',
            ])
            ->with([
                'detail',
                'rating',
            ])
            ->whereHas('detail', function ($q) use ($assignedValue) {
                $q->where('ticket_assigned_staff', $assignedValue);
            })
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
            ->when($preset === 'needs_attention', function ($q) {
                $cutoff = now()->subHours(48)->toDateTimeString();
                $q->whereHas('detail', function ($d) use ($cutoff) {
                    $d->whereIn('ticket_status', ['Pending', 'In Progress'])
                        ->where(function ($sub) use ($cutoff) {
                            $sub->where('date_requested', '<=', $cutoff)
                                ->orWhere(function ($s2) use ($cutoff) {
                                    $s2->whereNull('date_requested')->where('created_at', '<=', $cutoff);
                                });
                        });
                });
            })
            ->when($preset === 'overdue', function ($q) {
                $cutoff = now()->subHours(72)->toDateTimeString();
                $q->whereHas('detail', function ($d) use ($cutoff) {
                    $d->whereIn('ticket_status', ['Pending', 'In Progress'])
                        ->where(function ($sub) use ($cutoff) {
                            $sub->where('date_requested', '<=', $cutoff)
                                ->orWhere(function ($s2) use ($cutoff) {
                                    $s2->whereNull('date_requested')->where('created_at', '<=', $cutoff);
                                });
                        });
                });
            })
            ->when($preset === 'awaiting_rating', function ($q) {
                $q->whereHas('detail', fn ($d) => $d->where('ticket_status', 'Completed'))
                    ->where(function ($sub) {
                        $sub->whereDoesntHave('rating')
                            ->orWhereHas('rating', fn ($r) => $r->where('rating_status', '<>', 'Received')->orWhereNull('rating_result'));
                    });
            })
            ->when($preset === 'completed_today', function ($q) {
                $q->whereHas('detail', fn ($d) => $d->where('ticket_status', 'Completed')->whereDate('date_completed', now()->toDateString()));
            })
            ->when($dateFrom || $dateTo || $year || $month || $quarter || ! empty($monthsArr), function ($q) use ($applyDateFiltersRel) {
                $q->whereHas('detail', $applyDateFiltersRel);
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
            ->latest('created_at');

        $tickets = $query->get();

        $filename = 'technician_accomplishment_report_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($tickets) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, [
                'Reference Code',
                'Client Name',
                'Division/Agency',
                'Client Type',
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
                    $ticket->client_type ?? '',
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
}
