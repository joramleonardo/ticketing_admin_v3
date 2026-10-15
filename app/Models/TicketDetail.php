<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketDetail extends Model
{
    use SoftDeletes;

    protected $table = 'v2_ticket_details';

    protected $primaryKey = 'ticket_id';

    protected $fillable = [
        'client_referenceNo',
        'ticket_type',
        'ticket_nature_of_support',
        'ticket_support_type',
        'ticket_related_type',
        'ticket_assigned_by',
        'ticket_assigned_staff',
        'ticket_attended_by',
        'ticket_assisted_by',
        'ticket_status',
        'ticket_problem_that_needed_support',
        'ticket_actions_taken',
        'ticket_remarks',
        'date_requested',
        'date_completed',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(TicketClientDetail::class, 'client_referenceNo', 'client_referenceNo')->withTrashed();
    }

    public function rating(): HasOne
    {
        return $this->hasOne(TicketRatingDetail::class, 'client_referenceNo', 'client_referenceNo');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachmentDetail::class, 'client_referenceNo', 'client_referenceNo');
    }

    public function event(): HasOne
    {
        return $this->hasOne(TicketEvent::class, 'client_referenceNo', 'client_referenceNo');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('ticket_status', TicketStatus::activeValues());
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('ticket_status', TicketStatus::Completed->value);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('ticket_status', TicketStatus::Cancelled->value);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('ticket_status', TicketStatus::Closed->value);
    }

    public function scopeTerminal(Builder $query): Builder
    {
        return $query->whereIn('ticket_status', TicketStatus::terminalValues());
    }
}
