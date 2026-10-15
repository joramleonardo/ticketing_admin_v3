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
        $view = trim((string) $request->input('view', 'month'));
        if (! in_array($view, ['month', 'week', 'agenda'], true)) {
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
                'view' => $view,
            ],
            'activeStaff' => app(StaffService::class)->getActiveStaff(),
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
            $query->whereHas('detail', function ($d) use ($staff): void {
                $d->where('ticket_assigned_staff', $staff)
                    ->orWhere('ticket_attended_by', $staff);
            });
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
     * Transform raw tickets into rich calendar event payloads.
     */
    protected function transformEvents($tickets)
    {
        $DIVISION_MAP = [
            '1' => 'FAD',
            '2' => 'IRAD',
            '3' => 'CRPD',
            '4' => 'OD-MISPS',
        ];

        return $tickets->map(function ($client) use ($DIVISION_MAP) {
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
                $startDateStr = Carbon::today()->format('Y-m-d');
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

            $today = Carbon::today()->format('Y-m-d');
            $isOngoing = ($startDateStr <= $today && $endDateStr >= $today);
            $isUpcoming = ($startDateStr > $today);
            $isPast = ($endDateStr < $today);

            $status = $detail?->ticket_status ?: 'New';

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
                'ticket_assigned_staff' => $detail?->ticket_assigned_staff ?: null,
                'ticket_attended_by' => $detail?->ticket_attended_by ?: null,
                'ticket_assisted_by' => $detail?->ticket_assisted_by ?: null,
                'ticket_remarks' => $detail?->ticket_remarks ?: '',
                'ticket_problem_that_needed_support' => $detail?->ticket_problem_that_needed_support ?: '',
                'date_requested' => $detail?->date_requested ? Carbon::parse($detail->date_requested)->format('Y-m-d H:i:s') : null,
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
                'messages_count' => (int) ($client->messages_count ?? 0),
                // Nested models for ModalTicketDetails compatibility
                'detail' => $detail,
                'event' => $event,
                'rating' => $client->rating,
            ];
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
