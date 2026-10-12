<?php

// app/Models/TicketAttachmentDetail.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentDetail extends Model
{
    protected $table = 'v2_ticket_attachment_details';

    protected $fillable = [
        'client_referenceNo', 'original_name', 'stored_path', 'mime_type',
        'size_bytes', 'is_image', 'uploaded_by',
    ];

    // FK is client_referenceNo → v2_ticket_details.client_referenceNo
    public function ticket()
    {
        return $this->belongsTo(TicketDetail::class, 'client_referenceNo', 'client_referenceNo');
    }

    // convenient URL
    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return Storage::disk('public')->url($this->stored_path);
    }
}
