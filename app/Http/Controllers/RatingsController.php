<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RatingsController extends Controller
{
    public function pending()
    {
        $latestRating = DB::table('v2_ticket_rating_details as r1')
            ->select('r1.client_referenceNo', DB::raw('MAX(r1.created_at) as max_created'))
            ->groupBy('r1.client_referenceNo');

        $tickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->joinSub($latestRating, 'lr', function ($join) {
                $join->on('lr.client_referenceNo', '=', 'd.client_referenceNo');
            })
            ->join('v2_ticket_rating_details as r', function ($join) {
                $join->on('r.client_referenceNo', '=', 'd.client_referenceNo')
                    ->on('r.created_at', '=', 'lr.max_created');
            })
            ->where('r.rating_status', 'Pending')
            ->orderBy('d.created_at', 'desc')
            ->select([
                'd.client_referenceNo',
                'c.client_name',
                'c.client_type',
                'd.ticket_type',
                'd.ticket_nature_of_support',
                'd.ticket_support_type',
                'd.ticket_attended_by',
                'd.ticket_assigned_by',
                'd.ticket_assigned_staff',
                'd.ticket_status',
                'd.ticket_problem_that_needed_support',
                'd.ticket_actions_taken',
                'd.ticket_remarks',
                'd.date_requested',
                'd.date_completed',
            ])
            ->get();

        return Inertia::render('TicketsV2/MyTicketComponents/NoRatingTickets', [
            'pendingTickets' => $tickets,
        ]);
    }
}
