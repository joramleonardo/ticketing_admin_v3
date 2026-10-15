<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) ($request->input('year') ?: now()->year);
        $monthsArr = $this->monthsFromRequest($request);
        $assignedStaff = $this->assignedStaffFromRequest($request);

        $base = $this->applyDashboardFilters(
            DB::table('v2_ticket_details as td'),
            $year,
            $monthsArr,
            $assignedStaff,
        );

        $stats = [
            'unassigned' => (clone $base)->where('td.ticket_status', 'New')->count(),
            'pending' => (clone $base)->where('td.ticket_status', 'Pending')->count(),
            'in_progress' => (clone $base)->where('td.ticket_status', 'In Progress')->count(),
            'completed' => (clone $base)->where('td.ticket_status', 'Completed')->count(),
            'cancelled' => (clone $base)->where('td.ticket_status', 'Cancelled')->count(),
            'closed' => (clone $base)->where('td.ticket_status', 'Closed')->count(),
            'total' => (clone $base)->count(),
        ];

        $divisionCounts = $this->applyDashboardFilters(
            DB::table('v2_ticket_details as td')
                ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'td.client_referenceNo'),
            $year,
            $monthsArr,
            $assignedStaff,
        )
            ->selectRaw("
                CASE
                WHEN c.client_division_agency IN ('1','FAD') THEN 'FAD'
                WHEN c.client_division_agency IN ('2','IRAD') THEN 'IRAD'
                WHEN c.client_division_agency IN ('3','CRPD') THEN 'CRPD'
                WHEN c.client_division_agency IN ('4','OD-MISPS') THEN 'OD-MISPS'
                ELSE NULLIF(TRIM(c.client_division_agency),'')
                END AS division,
                COUNT(*) AS total
            ")
            ->groupBy('division')
            ->orderBy('division')
            ->get();

        $latestRatings = DB::table('v2_ticket_rating_details as r1')
            ->select('r1.client_referenceNo', 'r1.rating_result', 'r1.created_at')
            ->join(DB::raw('
                (SELECT client_referenceNo, MAX(created_at) AS max_created
                FROM v2_ticket_rating_details
                GROUP BY client_referenceNo) rmax
            '), function ($join): void {
                $join->on('rmax.client_referenceNo', '=', 'r1.client_referenceNo')
                    ->on('rmax.max_created', '=', 'r1.created_at');
            });

        $ratingCounts = $this->applyDashboardFilters(
            DB::table('v2_ticket_details as td')
                ->leftJoinSub($latestRatings, 'rr', function ($join): void {
                    $join->on('rr.client_referenceNo', '=', 'td.client_referenceNo');
                }),
            $year,
            $monthsArr,
            $assignedStaff,
        )
            ->whereIn('td.ticket_status', ['Completed', 'Closed'])
            ->whereNotNull('rr.rating_result')
            ->selectRaw('rr.rating_result as rating, COUNT(*) as total')
            ->groupBy('rr.rating_result')
            ->get();

        $ratingSummary = $this->applyDashboardFilters(
            DB::table('v2_ticket_details as td')
                ->leftJoinSub($latestRatings, 'rr', function ($join): void {
                    $join->on('rr.client_referenceNo', '=', 'td.client_referenceNo');
                }),
            $year,
            $monthsArr,
            $assignedStaff,
        )
            ->whereIn('td.ticket_status', ['Completed', 'Closed'])
            ->selectRaw('
                COUNT(*) AS total_tickets,
                SUM(CASE WHEN rr.rating_result IS NULL THEN 1 ELSE 0 END) AS unrated_total,
                SUM(CASE WHEN rr.rating_result IS NOT NULL THEN 1 ELSE 0 END) AS rated_total
            ')
            ->first();

        $allowedNatures = [
            'Events',
            'Hardware',
            'Software',
            'Hardware & Software',
            'System Modification',
            'Livestream',
            'Others',
        ];

        $natureCounts = $this->applyDashboardFilters(
            DB::table('v2_ticket_details as td'),
            $year,
            $monthsArr,
            $assignedStaff,
        )
            ->whereIn('td.ticket_nature_of_support', $allowedNatures)
            ->selectRaw("
                COALESCE(NULLIF(TRIM(td.ticket_nature_of_support), ''), 'Unspecified') AS nature,
                COUNT(*) AS total
            ")
            ->groupBy('nature')
            ->orderByDesc('total')
            ->get();

        $allowedSupportTypes = [
            'Troubleshooting',
            'Evaluation',
            'Network',
            'Testing',
            'Consultation',
            'Assistance',
            'Others',
            'Installation',
            'Data Retrieval',
            'Virus/Malwares',
            'Data Conversion/Uploading/Processing',
            'Reformat',
            'Download',
        ];

        $supportTypeCounts = $this->applyDashboardFilters(
            DB::table('v2_ticket_details as td'),
            $year,
            $monthsArr,
            $assignedStaff,
        )
            ->whereIn('td.ticket_nature_of_support', $allowedNatures)
            ->whereIn('td.ticket_support_type', $allowedSupportTypes)
            ->selectRaw('TRIM(td.ticket_support_type) AS support_type, COUNT(*) AS total')
            ->groupBy('support_type')
            ->orderByDesc('total')
            ->get();

        $assignedStaffOptions = DB::table('v2_ticket_details')
            ->selectRaw('TRIM(ticket_assigned_staff) AS name')
            ->whereNotNull('ticket_assigned_staff')
            ->whereRaw("TRIM(ticket_assigned_staff) <> ''")
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->merge(
                DB::table('v2_ticket_details')
                    ->whereNotNull('ticket_assisted_by')
                    ->whereRaw("TRIM(ticket_assisted_by) <> ''")
                    ->pluck('ticket_assisted_by')
                    ->flatMap(fn (string $staffList): array => array_map(
                        fn (string $name): string => trim($name),
                        explode(',', $staffList),
                    ))
                    ->filter()
            )
            ->unique()
            ->sort()
            ->values();

        // SLA & Turnaround Time (TAT) metrics
        $slaRecords = $this->applyDashboardFilters(
            DB::table('v2_ticket_details as td'),
            $year,
            $monthsArr,
            $assignedStaff,
        )
            ->select('td.ticket_status', 'td.date_requested', 'td.date_completed')
            ->get();

        $resolvedTotal = 0;
        $resolvedWithinSla = 0;
        $resolvedOverdue = 0;
        $totalResolvedMinutes = 0;
        $activeOnTrack = 0;
        $activeWarning = 0;
        $activeBreached = 0;
        $now = now();

        foreach ($slaRecords as $rec) {
            $status = trim((string) $rec->ticket_status);
            $reqDate = $rec->date_requested ? Carbon::parse($rec->date_requested) : null;
            $compDate = $rec->date_completed ? Carbon::parse($rec->date_completed) : null;

            if (in_array($status, ['Completed', 'Closed'], true)) {
                if ($reqDate) {
                    $resolvedTotal++;
                    $end = $compDate ?: $now;
                    if ($end->lessThan($reqDate)) {
                        $end = $reqDate->copy();
                    }
                    $mins = $reqDate->diffInMinutes($end);
                    $totalResolvedMinutes += $mins;

                    if ($mins <= 24 * 60) {
                        $resolvedWithinSla++;
                    } else {
                        $resolvedOverdue++;
                    }
                }
            } elseif (in_array($status, ['New', 'Pending', 'In Progress'], true)) {
                if ($reqDate) {
                    $mins = $reqDate->diffInMinutes($now);
                    if ($mins > 48 * 60) {
                        $activeBreached++;
                    } elseif ($mins > 24 * 60) {
                        $activeWarning++;
                    } else {
                        $activeOnTrack++;
                    }
                } else {
                    $activeOnTrack++;
                }
            }
        }

        $complianceRate = $resolvedTotal > 0 ? round(($resolvedWithinSla / $resolvedTotal) * 100, 1) : 100.0;
        $avgTatHours = $resolvedTotal > 0 ? round(($totalResolvedMinutes / $resolvedTotal) / 60, 1) : 0.0;
        $avgTatFormatted = $avgTatHours >= 24
            ? round($avgTatHours / 24, 1).' days'
            : ($avgTatHours > 0 ? $avgTatHours.' hrs' : '< 1 hr');

        $slaStats = [
            'compliance_rate' => $complianceRate,
            'avg_tat_formatted' => $avgTatFormatted,
            'avg_tat_hours' => $avgTatHours,
            'resolved_total' => $resolvedTotal,
            'resolved_within_sla' => $resolvedWithinSla,
            'resolved_overdue' => $resolvedOverdue,
            'active_total' => $activeOnTrack + $activeWarning + $activeBreached,
            'active_on_track' => $activeOnTrack,
            'active_warning' => $activeWarning,
            'active_breached' => $activeBreached,
        ];

        // Staff Workload Metrics
        $staffWorkload = $this->applyDashboardFilters(
            DB::table('v2_ticket_details as td'),
            $year,
            $monthsArr,
            $assignedStaff,
        )
            ->whereNotNull('td.ticket_assigned_staff')
            ->whereRaw("TRIM(td.ticket_assigned_staff) <> ''")
            ->selectRaw("
                TRIM(td.ticket_assigned_staff) AS name,
                SUM(CASE WHEN td.ticket_status IN ('New', 'Pending', 'In Progress') THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN td.ticket_status IN ('Completed', 'Closed') THEN 1 ELSE 0 END) AS resolved_count,
                COUNT(*) AS total_assigned
            ")
            ->groupBy(DB::raw('TRIM(td.ticket_assigned_staff)'))
            ->orderByDesc('active_count')
            ->orderByDesc('total_assigned')
            ->limit(10)
            ->get()
            ->map(function ($row): array {
                $active = (int) $row->active_count;
                $status = match (true) {
                    $active === 0 => 'Available',
                    $active <= 3 => 'Normal',
                    $active <= 6 => 'Busy',
                    default => 'Overloaded',
                };

                return [
                    'name' => (string) $row->name,
                    'active_count' => $active,
                    'resolved_count' => (int) $row->resolved_count,
                    'total_assigned' => (int) $row->total_assigned,
                    'capacity_status' => $status,
                ];
            });

        // Recent Client Feedback & Commendations Stream
        $recentFeedback = DB::table('v2_ticket_rating_details as r')
            ->leftJoin('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'r.client_referenceNo')
            ->leftJoin('v2_ticket_details as td', 'td.client_referenceNo', '=', 'r.client_referenceNo')
            ->select([
                'r.client_referenceNo',
                'r.rating_result',
                'r.rating_comment_suggestion',
                'r.created_at',
                'c.client_name',
                'c.client_division_agency',
                'td.ticket_assigned_staff',
                'td.ticket_nature_of_support',
            ])
            ->whereNotNull('r.rating_result')
            ->orderByDesc('r.created_at')
            ->limit(6)
            ->get()
            ->map(function ($fb): array {
                $score = match (trim((string) $fb->rating_result)) {
                    '5', 'Excellent' => 5,
                    '4', 'Very Good' => 4,
                    '3', 'Good' => 3,
                    '2', 'Fair', 'Poor' => 2,
                    '1', 'Very Poor' => 1,
                    default => 5,
                };

                return [
                    'client_referenceNo' => (string) $fb->client_referenceNo,
                    'rating_result' => (string) $fb->rating_result,
                    'stars' => $score,
                    'comment' => $fb->rating_comment_suggestion ? trim((string) $fb->rating_comment_suggestion) : null,
                    'created_at' => $fb->created_at ? Carbon::parse($fb->created_at)->diffForHumans() : 'Recently',
                    'client_name' => (string) ($fb->client_name ?: 'Anonymous Client'),
                    'division' => (string) ($fb->client_division_agency ?: 'STII'),
                    'assigned_staff' => (string) ($fb->ticket_assigned_staff ?: 'Unassigned'),
                    'nature' => (string) ($fb->ticket_nature_of_support ?: 'Technical Support'),
                ];
            });

        // Trend Comparison Badges (Month-over-Month or Year-over-Year)
        if (count($monthsArr) === 1) {
            $singleM = $monthsArr[0];
            if ($singleM === 1) {
                $prevYear = $year - 1;
                $prevMonths = [12];
            } else {
                $prevYear = $year;
                $prevMonths = [$singleM - 1];
            }
            $periodLabel = 'vs last month';
        } else {
            $prevYear = $year - 1;
            $prevMonths = $monthsArr;
            $periodLabel = 'vs last year';
        }

        $prevBase = $this->applyDashboardFilters(
            DB::table('v2_ticket_details as td'),
            $prevYear,
            $prevMonths,
            $assignedStaff,
        );

        $currTotal = (int) $stats['total'];
        $prevTotal = (int) (clone $prevBase)->count();
        $currResolved = (int) ($stats['completed'] + $stats['closed']);
        $prevResolved = (int) (clone $prevBase)->whereIn('td.ticket_status', ['Completed', 'Closed'])->count();

        $calcDelta = function (int $curr, int $prev): array {
            if ($prev === 0) {
                $diffPct = $curr > 0 ? 100 : 0;
            } else {
                $diffPct = (int) round((($curr - $prev) / $prev) * 100);
            }
            $direction = $diffPct > 0 ? 'up' : ($diffPct < 0 ? 'down' : 'neutral');
            $formatted = ($diffPct > 0 ? '+'.$diffPct : (string) $diffPct).'%';

            return [
                'pct' => $diffPct,
                'formatted' => $formatted,
                'direction' => $direction,
            ];
        };

        $totalDelta = $calcDelta($currTotal, $prevTotal);
        $resolvedDelta = $calcDelta($currResolved, $prevResolved);

        $trends = [
            'period_label' => $periodLabel,
            'total_diff_pct' => $totalDelta['formatted'],
            'total_direction' => $totalDelta['direction'],
            'resolved_diff_pct' => $resolvedDelta['formatted'],
            'resolved_direction' => $resolvedDelta['direction'],
            'prev_total' => $prevTotal,
            'prev_resolved' => $prevResolved,
        ];

        $user = Auth::user();
        $userRole = $user?->role ?? 'Staff';
        $staffPersonal = $this->getStaffPersonalData($user?->name ?? '', $year, $monthsArr);
        $adminQueue = $this->getAdminQueueData();

        return Inertia::render('Dashboard', [
            'userRole' => $userRole,
            'staffPersonal' => $staffPersonal,
            'adminQueue' => $adminQueue,
            'ticketStats' => $stats,
            'slaStats' => $slaStats,
            'trends' => $trends,
            'staffWorkload' => $staffWorkload,
            'recentFeedback' => $recentFeedback,
            'divisionCounts' => $divisionCounts,
            'ratingCounts' => $ratingCounts,
            'ratingUnratedTotal' => (int) ($ratingSummary->unrated_total ?? 0),
            'natureCounts' => $natureCounts,
            'supportTypeCounts' => $supportTypeCounts,
            'assignedStaffOptions' => $assignedStaffOptions,
            'staffMembers' => $assignedStaffOptions,
            'filters' => [
                'year' => $year,
                'months' => implode(',', $monthsArr),
                'assigned_staff' => $assignedStaff,
            ],
        ]);
    }

    private function getStaffPersonalData(string $staffName, int $year, array $monthsArr): array
    {
        $base = $this->applyDashboardFilters(
            DB::table('v2_ticket_details as td'),
            $year,
            $monthsArr,
            $staffName,
        );

        $pending = (clone $base)->where('td.ticket_status', 'Pending')->count();
        $inProgress = (clone $base)->where('td.ticket_status', 'In Progress')->count();
        $completed = (clone $base)->whereIn('td.ticket_status', ['Completed', 'Closed'])->count();
        $total = (clone $base)->count();

        // Urgent tickets assigned to me that need attention (Pending or In Progress)
        $urgentTickets = DB::table('v2_ticket_details as td')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'td.client_referenceNo')
            ->where(function ($q) use ($staffName): void {
                $q->whereRaw("TRIM(COALESCE(td.ticket_assigned_staff, '')) = ?", [$staffName])
                    ->orWhereRaw("CONCAT(',', REPLACE(COALESCE(td.ticket_assisted_by, ''), ', ', ','), ',') LIKE ?", ["%,{$staffName},%"]);
            })
            ->whereIn('td.ticket_status', ['New', 'Pending', 'In Progress'])
            ->select([
                'td.ticket_id',
                'c.client_id',
                'c.client_referenceNo',
                'c.client_name',
                'c.client_division_agency',
                'td.ticket_status',
                'td.ticket_type',
                'td.ticket_support_type',
                'td.ticket_nature_of_support',
                'td.ticket_problem_that_needed_support',
                'td.date_requested',
                'td.created_at',
            ])
            ->orderBy('td.date_requested', 'asc')
            ->limit(8)
            ->get();

        // Personal Ratings for tickets attended by me
        $personalRatings = DB::table('v2_ticket_rating_details as r')
            ->join('v2_ticket_details as td', 'td.client_referenceNo', '=', 'r.client_referenceNo')
            ->where(function ($q) use ($staffName): void {
                $q->whereRaw("TRIM(COALESCE(td.ticket_assigned_staff, '')) = ?", [$staffName])
                    ->orWhereRaw("CONCAT(',', REPLACE(COALESCE(td.ticket_assisted_by, ''), ', ', ','), ',') LIKE ?", ["%,{$staffName},%"]);
            })
            ->whereNotNull('r.rating_result')
            ->select('r.rating_result', 'r.rating_comment_suggestion', 'r.created_at', 'td.client_referenceNo')
            ->orderByDesc('r.created_at')
            ->get();

        $totalRatings = $personalRatings->count();
        $sumStars = 0;
        foreach ($personalRatings as $pr) {
            $val = match (trim((string) $pr->rating_result)) {
                '5', 'Excellent' => 5,
                '4', 'Very Good' => 4,
                '3', 'Good' => 3,
                '2', 'Fair', 'Poor' => 2,
                '1', 'Very Poor' => 1,
                default => 5,
            };
            $sumStars += $val;
        }
        $avgScore = $totalRatings > 0 ? round($sumStars / $totalRatings, 1) : 5.0;

        $recentMyFeedback = $personalRatings->take(5)->map(function ($fb): array {
            $score = match (trim((string) $fb->rating_result)) {
                '5', 'Excellent' => 5,
                '4', 'Very Good' => 4,
                '3', 'Good' => 3,
                '2', 'Fair', 'Poor' => 2,
                '1', 'Very Poor' => 1,
                default => 5,
            };

            return [
                'client_referenceNo' => (string) $fb->client_referenceNo,
                'stars' => $score,
                'rating_result' => (string) $fb->rating_result,
                'comment' => $fb->rating_comment_suggestion ? trim((string) $fb->rating_comment_suggestion) : null,
                'created_at' => $fb->created_at ? Carbon::parse($fb->created_at)->diffForHumans() : 'Recently',
            ];
        });

        return [
            'stats' => [
                'pending' => $pending,
                'in_progress' => $inProgress,
                'completed' => $completed,
                'total' => $total,
            ],
            'urgent_tickets' => $urgentTickets,
            'avg_rating' => $avgScore,
            'total_ratings' => $totalRatings,
            'recent_feedback' => $recentMyFeedback,
        ];
    }

    private function getAdminQueueData(): array
    {
        // Unassigned tickets needing dispatch
        $unassignedTickets = DB::table('v2_ticket_details as td')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'td.client_referenceNo')
            ->leftJoin('v2_ticket_events as te', 'te.client_referenceNo', '=', 'td.client_referenceNo')
            ->where(function ($q): void {
                $q->where('td.ticket_status', 'New')
                    ->orWhereNull('td.ticket_assigned_staff')
                    ->orWhereRaw("TRIM(td.ticket_assigned_staff) = ''");
            })
            ->whereNotIn('td.ticket_status', ['Completed', 'Closed', 'Cancelled'])
            ->select([
                'td.ticket_id',
                'c.client_id',
                'c.client_referenceNo',
                'c.client_name',
                'c.client_division_agency',
                'c.client_email',
                'td.ticket_status',
                'td.ticket_type',
                'td.ticket_support_type',
                'td.ticket_nature_of_support',
                'td.ticket_problem_that_needed_support',
                'td.date_requested',
                'td.created_at',
                'te.event_start_date',
                'te.event_end_date',
                'te.event_venue',
                'te.event_remarks',
                'te.other_request',
            ])
            ->orderBy('td.date_requested', 'desc')
            ->limit(6)
            ->get();

        // Overdue tickets (> 24h active)
        $overdueTickets = DB::table('v2_ticket_details as td')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'td.client_referenceNo')
            ->leftJoin('v2_ticket_events as te', 'te.client_referenceNo', '=', 'td.client_referenceNo')
            ->whereIn('td.ticket_status', ['New', 'Pending', 'In Progress'])
            ->where('td.date_requested', '<=', now()->subHours(24))
            ->select([
                'td.ticket_id',
                'c.client_id',
                'c.client_referenceNo',
                'c.client_name',
                'c.client_division_agency',
                'c.client_email',
                'td.ticket_assigned_staff',
                'td.ticket_status',
                'td.ticket_type',
                'td.ticket_support_type',
                'td.ticket_nature_of_support',
                'td.ticket_problem_that_needed_support',
                'td.date_requested',
                'te.event_start_date',
                'te.event_end_date',
                'te.event_venue',
                'te.event_remarks',
                'te.other_request',
            ])
            ->orderBy('td.date_requested', 'asc')
            ->limit(6)
            ->get();

        return [
            'unassigned_tickets' => $unassignedTickets,
            'overdue_tickets' => $overdueTickets,
        ];
    }

    public function export(Request $request): StreamedResponse
    {
        $year = (int) ($request->input('year') ?: now()->year);
        $monthsArr = $this->monthsFromRequest($request);
        $assignedStaff = $this->assignedStaffFromRequest($request);

        $latestRatings = DB::table('v2_ticket_rating_details as r1')
            ->select('r1.client_referenceNo', 'r1.rating_result', 'r1.created_at')
            ->join(DB::raw('
                (SELECT client_referenceNo, MAX(created_at) AS max_created
                FROM v2_ticket_rating_details
                GROUP BY client_referenceNo) rmax
            '), function ($join): void {
                $join->on('rmax.client_referenceNo', '=', 'r1.client_referenceNo')
                    ->on('rmax.max_created', '=', 'r1.created_at');
            });

        $requestedDates = DB::table('v2_ticket_activity_logs')
            ->select('client_referenceNo', DB::raw('MIN(activity_date) AS requested_date'))
            ->where('activity_name', 'Ticket Created')
            ->groupBy('client_referenceNo');

        $approvedDates = DB::table('v2_ticket_activity_logs')
            ->select('client_referenceNo', DB::raw('MIN(activity_date) AS approved_date'))
            ->where('activity_name', 'Ticket Assigned')
            ->groupBy('client_referenceNo');

        $accomplishedDates = DB::table('v2_ticket_activity_logs')
            ->select('client_referenceNo', DB::raw('MAX(activity_date) AS accomplished_date'))
            ->where('activity_name', 'Ticket Completed')
            ->groupBy('client_referenceNo');

        $eventDates = DB::table('v2_ticket_events')
            ->select('client_referenceNo', DB::raw('MIN(event_start_date) AS event_start_date'))
            ->groupBy('client_referenceNo');

        $rows = DB::table('v2_ticket_details as td')
            ->leftJoin('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'td.client_referenceNo')
            ->leftJoinSub($latestRatings, 'rr', function ($join): void {
                $join->on('rr.client_referenceNo', '=', 'td.client_referenceNo');
            })
            ->leftJoinSub($requestedDates, 'req', function ($join): void {
                $join->on('req.client_referenceNo', '=', 'td.client_referenceNo');
            })
            ->leftJoinSub($approvedDates, 'appr', function ($join): void {
                $join->on('appr.client_referenceNo', '=', 'td.client_referenceNo');
            })
            ->leftJoinSub($accomplishedDates, 'done', function ($join): void {
                $join->on('done.client_referenceNo', '=', 'td.client_referenceNo');
            })
            ->leftJoinSub($eventDates, 've', function ($join): void {
                $join->on('ve.client_referenceNo', '=', 'td.client_referenceNo');
            })
            ->where(function (Builder $query) use ($year, $monthsArr): void {
                $query
                    ->where(function (Builder $query) use ($year, $monthsArr): void {
                        $query->where('td.ticket_type', '<>', 'Events')
                            ->where(function (Builder $query) use ($year, $monthsArr): void {
                                $query->where(function (Builder $query) use ($year, $monthsArr): void {
                                    $query->whereNotNull('req.requested_date')
                                        ->whereYear('req.requested_date', $year)
                                        ->whereIn(DB::raw('MONTH(req.requested_date)'), $monthsArr);
                                })->orWhere(function (Builder $query) use ($year, $monthsArr): void {
                                    $query->whereNull('req.requested_date')
                                        ->whereYear('td.created_at', $year)
                                        ->whereIn(DB::raw('MONTH(td.created_at)'), $monthsArr);
                                });
                            });
                    })
                    ->orWhere(function (Builder $query) use ($year, $monthsArr): void {
                        $query->where('td.ticket_type', 'Events')
                            ->whereNotNull('ve.event_start_date')
                            ->whereYear('ve.event_start_date', $year)
                            ->whereIn(DB::raw('MONTH(ve.event_start_date)'), $monthsArr);
                    });
            })
            ->when($assignedStaff !== null, function (Builder $query) use ($assignedStaff): void {
                $query->where(function (Builder $query) use ($assignedStaff): void {
                    $query->whereRaw("TRIM(COALESCE(td.ticket_assigned_staff, '')) = ?", [$assignedStaff])
                        ->orWhereRaw("CONCAT(',', REPLACE(COALESCE(td.ticket_assisted_by, ''), ', ', ','), ',') LIKE ?", ["%,{$assignedStaff},%"]);
                });
            })
            ->selectRaw('
                td.client_referenceNo AS `Reference Code`,
                req.requested_date AS `Requested Date`,
                appr.approved_date AS `Approved Date`,
                done.accomplished_date AS `Accomplished Date`,
                td.ticket_type AS `Ticket Type`,
                td.ticket_status AS `Status`,
                c.client_name AS `Client Name`,
                c.client_division_agency AS `Division/Agency`,
                c.client_sex AS `Sex`,
                c.client_type AS `Client Type`,
                td.ticket_nature_of_support AS `Support Type`,
                CASE WHEN td.ticket_nature_of_support LIKE "%Hardware%" THEN td.ticket_support_type END AS `Hardware`,
                CASE WHEN td.ticket_nature_of_support LIKE "%Software%" THEN td.ticket_support_type END AS `Software`,
                td.ticket_assigned_staff AS `Attended By`,
                td.ticket_assisted_by AS `Assisted By`,
                rr.rating_result AS `Rating`,
                td.ticket_problem_that_needed_support AS `Problem that needed support`,
                td.ticket_actions_taken AS `Actions Taken`,
                td.ticket_remarks AS `Remarks`,
                td.ticket_related_type AS `Type`
            ')
            ->orderByRaw('COALESCE(req.requested_date, td.created_at, ve.event_start_date)')
            ->orderBy('td.client_referenceNo')
            ->get();

        $mm = implode('_', array_map(fn (int $month): string => str_pad((string) $month, 2, '0', STR_PAD_LEFT), $monthsArr));
        $filename = sprintf('ticket_report_%04d-%s.csv', $year, $mm);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            $headers = [
                'Reference Code',
                'Requested Date',
                'Approved Date',
                'Accomplished Date',
                'Ticket Type',
                'Status',
                'Client Name',
                'Division/Agency',
                'Sex',
                'Client Type',
                'Support Type',
                'Hardware',
                'Software',
                'Attended By',
                'Assisted By',
                'Rating',
                'Problem that needed support',
                'Actions Taken',
                'Remarks',
                'Type',
            ];
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->{'Reference Code'},
                    $this->formatCsvDate($row->{'Requested Date'}),
                    $this->formatCsvDate($row->{'Approved Date'}),
                    $this->formatCsvDate($row->{'Accomplished Date'}),
                    $row->{'Ticket Type'},
                    $row->{'Status'},
                    $row->{'Client Name'},
                    $row->{'Division/Agency'},
                    $row->{'Sex'},
                    $row->{'Client Type'},
                    $row->{'Support Type'},
                    $row->{'Hardware'},
                    $row->{'Software'},
                    $row->{'Attended By'},
                    $row->{'Assisted By'},
                    $row->{'Rating'},
                    $row->{'Problem that needed support'},
                    $row->{'Actions Taken'},
                    $row->{'Remarks'},
                    $row->{'Type'},
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function formatCsvDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return \Illuminate\Support\Carbon::parse((string) $value)->format('Y-m-d');
    }

    /**
     * @return array<int, int>
     */
    private function monthsFromRequest(Request $request): array
    {
        $monthsCsv = trim((string) $request->input('months', ''));
        $monthsArr = $monthsCsv !== ''
            ? collect(explode(',', $monthsCsv))
                ->map(fn ($month): int => (int) $month)
                ->filter(fn (int $month): bool => $month >= 1 && $month <= 12)
                ->unique()
                ->values()
                ->all()
            : [];

        if (empty($monthsArr)) {
            $single = (int) ($request->input('month') ?: now()->month);
            $monthsArr = [$single >= 1 && $single <= 12 ? $single : now()->month];
        }

        return $monthsArr;
    }

    private function assignedStaffFromRequest(Request $request): ?string
    {
        $assignedStaff = trim((string) $request->input('assigned_staff', ''));

        return $assignedStaff !== '' ? $assignedStaff : null;
    }

    /**
     * @param  array<int, int>  $monthsArr
     */
    private function applyDashboardFilters(Builder $query, int $year, array $monthsArr, ?string $assignedStaff): Builder
    {
        return $query
            ->where(function (Builder $query) use ($year, $monthsArr): void {
                $query->whereYear('td.date_requested', $year)
                    ->whereIn(DB::raw('MONTH(td.date_requested)'), $monthsArr)
                    ->orWhere(function (Builder $query) use ($year, $monthsArr): void {
                        $query->whereNull('td.date_requested')
                            ->whereYear('td.created_at', $year)
                            ->whereIn(DB::raw('MONTH(td.created_at)'), $monthsArr);
                    });
            })
            ->when($assignedStaff !== null, function (Builder $query) use ($assignedStaff): void {
                $query->where(function (Builder $query) use ($assignedStaff): void {
                    $query->whereRaw("TRIM(COALESCE(td.ticket_assigned_staff, '')) = ?", [$assignedStaff])
                        ->orWhereRaw("CONCAT(',', REPLACE(COALESCE(td.ticket_assisted_by, ''), ', ', ','), ',') LIKE ?", ["%,{$assignedStaff},%"]);
                });
            });
    }
}
