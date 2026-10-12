<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketRatingDetail extends Model
{
    protected $table = 'v2_ticket_rating_details';

    protected $primaryKey = 'rating_id';

    protected $guarded = []; // or list fillable

    // Relations
    public function client()
    {
        return $this->belongsTo(TicketClientDetail::class, 'client_referenceNo', 'client_referenceNo');
    }
}
