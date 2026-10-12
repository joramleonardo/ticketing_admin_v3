<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RatingReportsController extends Controller
{
    public function index(Request $request)
    {
        // Parse filters
        $monthIn = trim((string) $request->input('month', ''));
        $yearIn = trim((string) $request->input('year', ''));

        $month = ($monthIn === '' || strcasecmp($monthIn, 'all') === 0) ? null : (int) $monthIn;
        $year = ($yearIn === '' || strcasecmp($yearIn, 'all') === 0) ? null : (int) $yearIn;

        // ---------- NO RATINGS YET (anti-join) ----------
        $noRatingTicketsAll = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->leftJoin('v2_ticket_rating_details as r', 'r.client_referenceNo', '=', 'd.client_referenceNo')
            ->whereIn('d.ticket_status', ['Completed', 'Closed'])
            ->whereNull('r.client_referenceNo')
            ->when($year, fn ($q) => $q->whereYear('d.date_completed', $year))
            ->when($month, fn ($q) => $q->whereMonth('d.date_completed', $month))
            ->orderByDesc('d.date_completed')
            ->select([
                'd.ticket_id',
                'd.client_referenceNo',
                'c.client_name',
                'c.client_type',
                'c.client_division_agency',
                'd.ticket_type',
                'd.ticket_nature_of_support',
                'd.ticket_support_type',
                'd.ticket_attended_by',
                'd.ticket_assigned_by',
                'd.ticket_assigned_staff as assigned_staff',
                'd.ticket_status',
                'd.ticket_problem_that_needed_support',
                'd.ticket_actions_taken',
                'd.ticket_remarks',
                'd.date_requested',
                'd.date_completed',
                'd.created_at',
            ])
            ->get();

        // ---------- WITH RATINGS (latest rating per ticket) ----------
        // subquery to pick the latest rating row per reference
        $latestRating = DB::table('v2_ticket_rating_details as r1')
            ->select('r1.client_referenceNo', DB::raw('MAX(r1.created_at) as max_created'))
            ->groupBy('r1.client_referenceNo');

        $hasRatingTicketsAll = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->joinSub($latestRating, 'lr', function ($join) {
                $join->on('lr.client_referenceNo', '=', 'd.client_referenceNo');
            })
            ->join('v2_ticket_rating_details as r', function ($join) {
                $join->on('r.client_referenceNo', '=', 'd.client_referenceNo')
                    ->on('r.created_at', '=', 'lr.max_created');
            })
            ->whereIn('d.ticket_status', ['Completed', 'Closed'])
            ->when($year, fn ($q) => $q->whereYear('d.date_completed', $year))
            ->when($month, fn ($q) => $q->whereMonth('d.date_completed', $month))
            ->orderByDesc('d.date_completed')
            ->select([
                'd.ticket_id',
                'd.client_referenceNo',
                'c.client_name',
                'c.client_type',
                'c.client_division_agency',
                'd.ticket_type',
                'd.ticket_nature_of_support',
                'd.ticket_support_type',
                'd.ticket_attended_by',
                'd.ticket_assigned_by',
                'd.ticket_assigned_staff as assigned_staff',
                'd.ticket_status',
                'd.ticket_problem_that_needed_support',
                'd.ticket_actions_taken',
                'd.ticket_remarks',
                'd.date_requested',
                'd.date_completed',
                'd.created_at',

                // keep only columns that actually exist in rating table
                'r.rating_status',
                'r.created_at as rating_created_at',
            ])
            ->get();

        $ticketStats = [
            'no_rating_total' => $noRatingTicketsAll->count(),
            'has_rating_total' => $hasRatingTicketsAll->count(),
        ];

        return Inertia::render('TicketsV2/RatingDashboard', [
            'noRatingTicketsAll' => $noRatingTicketsAll,
            'hasRatingTicketsAll' => $hasRatingTicketsAll,
            'filters' => [
                'month' => $month ?? 'all',
                'year' => $year ?? 'all',
            ],
            'ticketStats' => $ticketStats,
        ]);
    }

    // Optional JSON endpoints if you want ajax reloads
    public function noRatingJson(Request $request)
    {
        $monthIn = trim((string) $request->input('month', ''));
        $yearIn = trim((string) $request->input('year', ''));

        $month = ($monthIn === '' || strcasecmp($monthIn, 'all') === 0) ? null : (int) $monthIn;
        $year = ($yearIn === '' || strcasecmp($yearIn, 'all') === 0) ? null : (int) $yearIn;

        $list = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->leftJoin('v2_ticket_rating_details as r', 'r.client_referenceNo', '=', 'd.client_referenceNo')
            ->whereIn('d.ticket_status', ['Completed', 'Closed'])
            ->whereNull('r.client_referenceNo')
            ->when($year, fn ($q) => $q->whereYear('d.date_completed', $year))
            ->when($month, fn ($q) => $q->whereMonth('d.date_completed', $month))
            ->orderByDesc('d.date_completed')
            ->select([
                'd.ticket_id',
                'd.client_referenceNo',
                'c.client_name',
                'c.client_type',
                'c.client_division_agency',
                'd.ticket_type',
                'd.ticket_nature_of_support',
                'd.ticket_support_type',
                'd.ticket_attended_by',
                'd.ticket_assigned_by',
                'd.ticket_assigned_staff as assigned_staff',
                'd.ticket_status',
                'd.ticket_problem_that_needed_support',
                'd.ticket_actions_taken',
                'd.ticket_remarks',
                'd.date_requested',
                'd.date_completed',
                'd.created_at',
            ])
            ->get();

        return response()->json([
            'data' => $list,
            'filters' => [
                'month' => $month ?? 'all',
                'year' => $year ?? 'all',
            ],
        ]);
    }

    public function hasRatingJson(Request $request)
    {
        $monthIn = trim((string) $request->input('month', ''));
        $yearIn = trim((string) $request->input('year', ''));

        $month = ($monthIn === '' || strcasecmp($monthIn, 'all') === 0) ? null : (int) $monthIn;
        $year = ($yearIn === '' || strcasecmp($yearIn, 'all') === 0) ? null : (int) $yearIn;

        $latestRating = DB::table('v2_ticket_rating_details as r1')
            ->select('r1.client_referenceNo', DB::raw('MAX(r1.created_at) as max_created'))
            ->groupBy('r1.client_referenceNo');

        $list = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->joinSub($latestRating, 'lr', fn ($j) => $j->on('lr.client_referenceNo', '=', 'd.client_referenceNo'))
            ->join('v2_ticket_rating_details as r', function ($j) {
                $j->on('r.client_referenceNo', '=', 'd.client_referenceNo')
                    ->on('r.created_at', '=', 'lr.max_created');
            })
            ->whereIn('d.ticket_status', ['Completed', 'Closed'])
            ->when($year, fn ($q) => $q->whereYear('d.date_completed', $year))
            ->when($month, fn ($q) => $q->whereMonth('d.date_completed', $month))
            ->orderByDesc('d.date_completed')
            ->select([
                'd.ticket_id',
                'd.client_referenceNo',
                'c.client_name',
                'd.ticket_assigned_staff as assigned_staff',
                'd.ticket_status',
                'd.date_completed',
                'r.rating_status',
                'r.created_at as rating_created_at',
            ])
            ->get();

        return response()->json([
            'data' => $list,
            'filters' => [
                'month' => $month ?? 'all',
                'year' => $year ?? 'all',
            ],
        ]);
    }
}
