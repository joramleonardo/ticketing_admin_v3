<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketActivityLog extends Model
{
    protected $table = 'v2_ticket_activity_logs';

    protected $primaryKey = 'activity_id';

    public $timestamps = false; // if you're only using `activity_date` manually

    protected $fillable = [
        'client_referenceNo',
        'activity_name',
        'activity_date',
        'activity_user',
    ];
}
