<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $guarded = [];

    public function status(): HasOne
    {
        return $this->hasOne(TicketStatus::class, 'reference_code', 'reference_code');
    }
}
