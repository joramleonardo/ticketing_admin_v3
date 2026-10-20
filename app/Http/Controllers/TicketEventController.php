<?php

namespace App\Http\Controllers;

use App\Models\TicketClientDetail;
use App\Services\StaffService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketEventController extends Controller
{
    public const STANDARD_VENUES = [
        'DOST-STII Atrium',
        'Cast Room',
        'Training Room',
        'Conference Room',
        'Mini Theater',
        'Zoom / Online',
    ];

    /**
     * Display the events calendar monitoring workspace.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $month = trim((string) $request->input('month', ''));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = Carbon::today()->format('Y-m');
        }

        $status = trim((string) $request->input('status', 'All'));
        $division = trim((string) $request->input('division', 'All'));
        $search = trim((string) $request->input('search', ''));
        $staff = trim((string) $request->input('staff', 'All'));
        $venue = trim((string) $request->input('venue', 'All'));
        $customVenue = trim((string) $request->input('custom_venue', ''));
        $view = trim((string) $request->input('view', 'month'));
        if (! in_array($view, ['month', 'week', 'agenda', 'venues'], true)) {
            $view = 'month';
        }

        $query = $this->getEventsQuery($request);
        $tickets = $query->orderByDesc('client_id')->get();
        $events = $this->transformEvents($tickets);

        $kpis = $this->computeKpis($events, $month);
        $statusCounts = $this->computeStatusCounts($tickets);

        $payload = [
            'events' => $events->values(),
            'kpis' => $kpis,
            'statusCounts' => $statusCounts,
            'filters' => [
                'month' => $month,
                'status' => $status,
                'division' => $division,
                'search' => $search,
                'staff' => $staff,
                'venue' => $venue,
                'custom_venue' => $customVenue,
                'view' => $view,
            ],
            'activeStaff' => app(StaffService::class)->getActiveStaffNames(),
        ];

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json($payload);
        }

        return Inertia::render('TicketsV2/EventsCalendar', $payload);
    }

    /**
     * JSON data endpoint for asynchronous month/filter transitions.
     */
    public function data(Request $request): JsonResponse
    {
        $month = trim((string) $request->input('month', ''));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = Carbon::today()->format('Y-m');
        }

        $query = $this->getEventsQuery($request);
        $tickets = $query->orderByDesc('client_id')->get();
        $events = $this->transformEvents($tickets);
        $kpis = $this->computeKpis($events, $month);
        $statusCounts = $this->computeStatusCounts($tickets);

        return response()->json([
            'events' => $events->values(),
            'kpis' => $kpis,
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * Build the query for event-related tickets.
     */
    protected function getEventsQuery(Request $request)
    {
        $status = trim((string) $request->input('status', ''));
        $division = trim((string) $request->input('division', ''));
        $search = trim((string) $request->input('search', ''));
        $staff = trim((string) $request->input('staff', ''));
        $venue = trim((string) $request->input('venue', ''));
        $customVenue = trim((string) $request->input('custom_venue', ''));

        $DIVISION_MAP = [
            '1' => 'FAD',
            '2' => 'IRAD',
            '3' => 'CRPD',
            '4' => 'OD-MISPS',
        ];
        $KNOWN_DIVISIONS = array_merge(array_keys($DIVISION_MAP), array_values($DIVISION_MAP));

        $query = TicketClientDetail::query()
            ->where(function ($q): void {
                $q->whereHas('event')
                    ->orWhereHas('detail', function ($sub): void {
                        $sub->where('ticket_type', 'Events')
                            ->orWhere('ticket_nature_of_support', 'Events')
                            ->orWhere('ticket_support_type', 'Events');
                    });
            })
            ->whereHas('detail', function ($d): void {
                $d->whereNull('deleted_at');
            })
            ->with([
                'detail',
                'event',
                'rating',
            ])
            ->withCount('messages');

        // Status filter
        if ($status !== '' && strcasecmp($status, 'All') !== 0) {
            $query->whereHas('detail', function ($d) use ($status): void {
                $d->where('ticket_status', $status);
            });
        }

        // Staff filter
        if ($staff !== '' && strcasecmp($staff, 'All') !== 0) {
            if (strcasecmp($staff, 'Unassigned') === 0) {
                $query->whereHas('detail', function ($d): void {
                    $d->where(function ($sub): void {
                        $sub->whereNull('ticket_assigned_staff')
                            ->orWhere('ticket_assigned_staff', '');
                    })->where(function ($sub): void {
                        $sub->whereNull('ticket_attended_by')
                            ->orWhere('ticket_attended_by', '');
                    });
                });
            } else {
                $query->whereHas('detail', function ($d) use ($staff): void {
                    $d->where('ticket_assigned_staff', $staff)
                        ->orWhere('ticket_attended_by', $staff);
                });
            }
        }

        // Division filter
        if ($division !== '' && strcasecmp($division, 'All') !== 0) {
            if (strcasecmp($division, 'OTHERS') === 0) {
                $query->where(function ($q) use ($KNOWN_DIVISIONS): void {
                    $q->whereNull('client_division_agency')
                        ->orWhere('client_division_agency', '')
                        ->orWhereNotIn('client_division_agency', $KNOWN_DIVISIONS);
                });
            } else {
                $label = $DIVISION_MAP[$division] ?? $division;
                $query->where(function ($q) use ($division, $label): void {
                    $q->where('client_division_agency', $division)
                        ->orWhere('client_division_agency', $label);
                });
            }
        }

        // Venue filter (Supports Standard Venues + 'Others' with optional custom_venue search)
        if ($venue !== '' && strcasecmp($venue, 'All') !== 0) {
            if (strcasecmp($venue, 'Others') === 0) {
                if ($customVenue !== '') {
                    $venueTerm = '%'.$customVenue.'%';
                    $query->whereHas('event', function ($eq) use ($venueTerm): void {
                        $eq->where('event_venue', 'like', $venueTerm);
                    });
                } else {
                    $query->whereHas('event', function ($eq): void {
                        $eq->whereNotNull('event_venue')
                            ->where('event_venue', '!=', '')
                            ->whereNotIn('event_venue', self::STANDARD_VENUES);
                    });
                }
            } else {
                $query->whereHas('event', function ($eq) use ($venue): void {
                    $eq->where('event_venue', $venue);
                });
            }
        }

        // Search filter
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($sub) use ($term): void {
                $sub->where('client_referenceNo', 'like', $term)
                    ->orWhere('client_name', 'like', $term)
                    ->orWhere('client_email', 'like', $term)
                    ->orWhere('client_empID', 'like', $term)
                    ->orWhereHas('event', function ($eq) use ($term): void {
                        $eq->where('event_venue', 'like', $term)
                            ->orWhere('event_remarks', 'like', $term)
                            ->orWhere('other_request', 'like', $term);
                    })
                    ->orWhereHas('detail', function ($dq) use ($term): void {
                        $dq->where('ticket_assigned_staff', 'like', $term)
                            ->orWhere('ticket_remarks', 'like', $term)
                            ->orWhere('ticket_problem_that_needed_support', 'like', $term);
                    });
            });
        }

        return $query;
    }

    /**
     * Transform raw tickets into rich calendar event payloads with Venue & Staff conflict detection.
     */
    protected function transformEvents($tickets)
    {
        $DIVISION_MAP = [
            '1' => 'FAD',
            '2' => 'IRAD',
            '3' => 'CRPD',
            '4' => 'OD-MISPS',
        ];

        $today = Carbon::today()->format('Y-m-d');
        $threeDaysFromNow = Carbon::today()->addDays(3)->format('Y-m-d');

        $baseEvents = $tickets->map(function ($client) use ($DIVISION_MAP, $today, $threeDaysFromNow) {
            $detail = $client->detail;
            $event = $client->event;

            $rawDiv = trim((string) ($client->client_division_agency ?? ''));
            $division = $DIVISION_MAP[$rawDiv] ?? ($rawDiv !== '' ? strtoupper($rawDiv) : 'OTHERS');

            // Determine Start Date
            $startDateStr = null;
            if (! empty($event?->event_start_date)) {
                $startDateStr = Carbon::parse($event->event_start_date)->format('Y-m-d');
            } elseif (! empty($detail?->date_requested)) {
                $startDateStr = Carbon::parse($detail->date_requested)->format('Y-m-d');
            } elseif (! empty($client->created_at)) {
                $startDateStr = Carbon::parse($client->created_at)->format('Y-m-d');
            } else {
                $startDateStr = $today;
            }

            // Determine End Date
            $endDateStr = null;
            if (! empty($event?->event_end_date)) {
                $endDateStr = Carbon::parse($event->event_end_date)->format('Y-m-d');
            } else {
                $endDateStr = $startDateStr;
            }

            // Guard against invalid end date earlier than start date
            if ($endDateStr < $startDateStr) {
                $endDateStr = $startDateStr;
            }

            $startCarbon = Carbon::parse($startDateStr);
            $endCarbon = Carbon::parse($endDateStr);
            $durationDays = (int) max(1, $startCarbon->diffInDays($endCarbon) + 1);

            $isOngoing = ($startDateStr <= $today && $endDateStr >= $today);
            $isUpcoming = ($startDateStr > $today);
            $isPast = ($endDateStr < $today);

            $status = $detail?->ticket_status ?: 'New';
            $assignedStaff = trim((string) ($detail?->ticket_assigned_staff ?? ''));
            $attendedBy = trim((string) ($detail?->ticket_attended_by ?? ''));

            $isTerminal = in_array(strtolower($status), ['completed', 'cancelled', 'closed'], true);
            $isUnassigned = ! $isTerminal && $assignedStaff === '' && $attendedBy === '';
            $isUrgentUnassigned = $isUnassigned && ($isOngoing || ($startDateStr >= $today && $startDateStr <= $threeDaysFromNow));
            $daysUntilStart = $isUpcoming ? (int) Carbon::today()->diffInDays($startCarbon) : 0;

            $daysElapsed = 0;
            if ($isOngoing) {
                $daysElapsed = (int) max(1, Carbon::parse($startDateStr)->diffInDays(Carbon::today()) + 1);
            } elseif ($isPast) {
                $daysElapsed = (int) $durationDays;
            }

            return [
                'id' => $client->client_id,
                'client_id' => $client->client_id,
                'client_referenceNo' => $client->client_referenceNo,
                'client_name' => $client->client_name ?: 'Unnamed Client',
                'client_division_agency' => $client->client_division_agency,
                'client_division_label' => $division,
                'client_email' => $client->client_email ?: '',
                'client_empID' => $client->client_empID ?: '',
                'client_sex' => $client->client_sex ?: '',
                'client_type' => $client->client_type ?: 'Internal',
                'ticket_id' => $detail?->ticket_id,
                'ticket_status' => $status,
                'ticket_type' => $detail?->ticket_type ?: 'Events',
                'ticket_nature_of_support' => $detail?->ticket_nature_of_support ?: 'Events',
                'ticket_support_type' => $detail?->ticket_support_type ?: 'Events',
                'ticket_assigned_staff' => $assignedStaff !== '' ? $assignedStaff : null,
                'ticket_attended_by' => $attendedBy !== '' ? $attendedBy : null,
                'ticket_assisted_by' => $detail?->ticket_assisted_by ?: null,
                'ticket_remarks' => $detail?->ticket_remarks ?: '',
                'ticket_problem_that_needed_support' => $detail?->ticket_problem_that_needed_support ?: '',
                'date_requested' => $detail?->date_requested ? Carbon::parse($detail->date_requested)->format('Y-m-d H:i:s') : null,
                'date_assigned' => $detail?->date_assigned ? Carbon::parse($detail->date_assigned)->format('Y-m-d H:i:s') : null,
                'date_attended' => $detail?->date_attended ? Carbon::parse($detail->date_attended)->format('Y-m-d H:i:s') : null,
                'date_completed' => $detail?->date_completed ? Carbon::parse($detail->date_completed)->format('Y-m-d H:i:s') : null,
                'created_at' => $client->created_at ? Carbon::parse($client->created_at)->format('Y-m-d H:i:s') : null,
                // Event specific fields
                'event_id' => $event?->event_id,
                'event_start_date' => $startDateStr,
                'event_end_date' => $endDateStr,
                'event_venue' => $event?->event_venue ?: 'Venue not specified',
                'event_remarks' => $event?->event_remarks ?: ($detail?->ticket_remarks ?: ''),
                'other_request' => $event?->other_request ?: '',
                // Duration & temporal state
                'duration_days' => $durationDays,
                'is_multi_day' => $durationDays > 1,
                'is_ongoing' => $isOngoing,
                'is_upcoming' => $isUpcoming,
                'is_past' => $isPast,
                'days_elapsed' => $daysElapsed,
                'days_until_start' => $daysUntilStart,
                'is_unassigned' => $isUnassigned,
                'is_urgent_unassigned' => $isUrgentUnassigned,
                'messages_count' => (int) ($client->messages_count ?? 0),
                // Nested models for ModalTicketDetails compatibility
                'detail' => $detail,
                'event' => $event,
                'rating' => $client->rating,
            ];
        })->values();

        // Second pass: Detect Venue & Staff Double-Booking Conflicts among non-cancelled events
        $ignoredVenues = ['', 'venue not specified', 'n/a', 'none', 'tba', 'zoom / online', 'zoom', 'online'];

        return $baseEvents->map(function (array $ev) use ($baseEvents, $ignoredVenues): array {
            $statusA = strtolower((string) ($ev['ticket_status'] ?? ''));
            $isInactiveA = in_array($statusA, ['cancelled', 'closed'], true);

            $venueConflicts = [];
            $staffConflicts = [];

            if (! $isInactiveA) {
                $venueA = strtolower(trim((string) ($ev['event_venue'] ?? '')));
                $hasPhysicalVenueA = ! in_array($venueA, $ignoredVenues, true);
                $staffA = strtolower(trim((string) ($ev['ticket_assigned_staff'] ?: $ev['ticket_attended_by'] ?: '')));

                foreach ($baseEvents as $other) {
                    if ($other['client_referenceNo'] === $ev['client_referenceNo']) {
                        continue;
                    }
                    $statusB = strtolower((string) ($other['ticket_status'] ?? ''));
                    if (in_array($statusB, ['cancelled', 'closed'], true)) {
                        continue;
                    }

                    // Check date overlap
                    $overlaps = ($ev['event_start_date'] <= $other['event_end_date'])
                        && ($ev['event_end_date'] >= $other['event_start_date']);

                    if (! $overlaps) {
                        continue;
                    }

                    // 1. Check Venue Conflict
                    $venueB = strtolower(trim((string) ($other['event_venue'] ?? '')));
                    if ($hasPhysicalVenueA && $venueA === $venueB) {
                        $venueConflicts[] = [
                            'client_referenceNo' => $other['client_referenceNo'],
                            'client_name' => $other['client_name'],
                            'event_venue' => $other['event_venue'],
                            'event_start_date' => $other['event_start_date'],
                            'event_end_date' => $other['event_end_date'],
                            'ticket_assigned_staff' => $other['ticket_assigned_staff'],
                            'ticket_status' => $other['ticket_status'],
                        ];
                    }

                    // 2. Check Staff Conflict
                    $staffB = strtolower(trim((string) ($other['ticket_assigned_staff'] ?: $other['ticket_attended_by'] ?: '')));
                    if ($staffA !== '' && $staffA === $staffB) {
                        $staffConflicts[] = [
                            'client_referenceNo' => $other['client_referenceNo'],
                            'client_name' => $other['client_name'],
                            'event_venue' => $other['event_venue'],
                            'event_start_date' => $other['event_start_date'],
                            'event_end_date' => $other['event_end_date'],
                            'staff_name' => $other['ticket_assigned_staff'] ?: $other['ticket_attended_by'],
                            'ticket_status' => $other['ticket_status'],
                        ];
                    }
                }
            }

            $ev['venue_conflicts'] = $venueConflicts;
            $ev['staff_conflicts'] = $staffConflicts;
            $ev['has_venue_conflict'] = count($venueConflicts) > 0;
            $ev['has_staff_conflict'] = count($staffConflicts) > 0;
            $ev['has_conflict'] = count($venueConflicts) > 0 || count($staffConflicts) > 0;

            return $ev;
        });
    }

    /**
     * Compute KPI cards summary.
     */
    protected function computeKpis($events, string $month): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $monthStart = Carbon::parse($month)->startOfMonth()->format('Y-m-d');
        $monthEnd = Carbon::parse($month)->endOfMonth()->format('Y-m-d');
        $sevenDays = Carbon::today()->addDays(7)->format('Y-m-d');

        return [
            'total_events' => $events->count(),
            'this_month_count' => $events->filter(function ($ev) use ($monthStart, $monthEnd) {
                return $ev['event_start_date'] <= $monthEnd && $ev['event_end_date'] >= $monthStart;
            })->count(),
            'ongoing_today_count' => $events->filter(fn ($ev) => $ev['is_ongoing'])->count(),
            'upcoming_count' => $events->filter(function ($ev) use ($today, $sevenDays) {
                return $ev['event_start_date'] > $today && $ev['event_start_date'] <= $sevenDays;
            })->count(),
            'multi_day_count' => $events->filter(fn ($ev) => $ev['is_multi_day'])->count(),
            'completed_count' => $events->filter(fn ($ev) => $ev['ticket_status'] === 'Completed')->count(),
            'unassigned_active_count' => $events->filter(fn ($ev) => ! empty($ev['is_unassigned']))->count(),
            'urgent_unassigned_count' => $events->filter(fn ($ev) => ! empty($ev['is_urgent_unassigned']))->count(),
            'conflict_count' => $events->filter(fn ($ev) => ! empty($ev['has_conflict']))->count(),
            'venue_conflict_count' => $events->filter(fn ($ev) => ! empty($ev['has_venue_conflict']))->count(),
            'staff_conflict_count' => $events->filter(fn ($ev) => ! empty($ev['has_staff_conflict']))->count(),
        ];
    }

    /**
     * Compute status pill counts.
     */
    protected function computeStatusCounts($tickets): array
    {
        $counts = [
            'All' => $tickets->count(),
            'New' => 0,
            'Pending' => 0,
            'In Progress' => 0,
            'Completed' => 0,
            'Cancelled' => 0,
            'Closed' => 0,
        ];

        foreach ($tickets as $t) {
            $st = $t->detail?->ticket_status ?: 'New';
            if (isset($counts[$st])) {
                $counts[$st]++;
            }
        }

        return $counts;
    }
}
