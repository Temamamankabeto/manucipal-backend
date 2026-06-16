<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_request_id',
        'uploaded_by',
        'document_type',
        'original_name',
        'stored_path',
        'mime_type',
        'size_bytes',
    ];

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
