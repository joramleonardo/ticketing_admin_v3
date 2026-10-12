<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NyTicketController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->month;
        $year = $request->year;

        $query = TicketStatus::query();

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

        return Inertia::render('MyTickets', [
            'ticketStats' => $stats,
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
        ]);

    }
}
