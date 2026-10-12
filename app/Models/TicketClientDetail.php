<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TicketClientDetail extends Model
{
    protected $table = 'v2_ticket_client_details';

    protected $primaryKey = 'client_id';

    protected $fillable = [
        'client_type',
        'client_name',
        'client_division_agency',
        'client_empID',
        'client_sex',
        'client_referenceNo',
    ];

    public function detail(): HasOne
    {
        return $this->hasOne(\App\Models\TicketDetail::class,
            'client_referenceNo',   // FK on details
            'client_referenceNo'    // local key on clients
        );
    }

    public function rating(): HasOne
    {
        return $this->hasOne(TicketRatingDetail::class, 'client_referenceNo', 'client_referenceNo');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'client_referenceNo', 'client_referenceNo');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(TicketMessage::class, 'client_referenceNo', 'client_referenceNo')->latestOfMany();
    }
}
