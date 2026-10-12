<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Inertia\Inertia;

class AllTicketsController extends Controller
{
    public function index()
    {
        $tickets = Ticket::with('status')->get();

        return Inertia::render('CardAllTicket', [
            'tickets' => $tickets,
        ]);
    }
}
