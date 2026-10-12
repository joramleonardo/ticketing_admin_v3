<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketEvent extends Model
{
    protected $table = 'v2_ticket_events';

    protected $primaryKey = 'event_id';

    public $timestamps = true;

    protected $fillable = [
        'client_referenceNo',
        'event_start_date',
        'event_end_date',
        'event_venue',
        'event_remarks',
    ];

    // format directly for <input type="date">
    protected $casts = [
        'event_start_date' => 'date:Y-m-d',
        'event_end_date' => 'date:Y-m-d',
    ];
}
