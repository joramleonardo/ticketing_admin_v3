<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NewTicketController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->month;
        $year = $request->year;

        // For stats only
        $statusQuery = TicketStatus::query();

        if ($month && $year) {
            $statusQuery->whereRaw("MONTH(STR_TO_DATE(entry_date, '%M %d %Y')) = ?", [$month])
                ->whereRaw("YEAR(STR_TO_DATE(entry_date, '%M %d %Y')) = ?", [$year]);
        }

        $stats = [
            'unassigned' => (clone $statusQuery)->where('status', 'Unassigned')->count(),
            'pending' => (clone $statusQuery)->where('status', 'Pending')->count(),
            'in_progress' => (clone $statusQuery)->where('status', 'In Progress')->count(),
            'completed' => (clone $statusQuery)->where('status', 'Completed')->count(),
        ];

        // ✅ Get all tickets regardless of month/year
        // $tickets = Ticket::with('status')->paginate(5);
        $tickets = Ticket::with('status')
            ->orderByDesc('created_at') // or ->latest()
            ->paginate(5);

        return Inertia::render('NewTickets', [
            'ticketStats' => $stats,
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'tickets' => $tickets, // ✅ Pass tickets to the frontend
        ]);
    }
}
