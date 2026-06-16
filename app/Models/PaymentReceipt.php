<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'tax_payment_id',
        'tax_bill_id',
        'citizen_id',
        'amount',
        'status',
        'generated_by',
        'verified_by',
        'generated_at',
        'verified_at',
        'verification_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'generated_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(TaxPayment::class, 'tax_payment_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(TaxBill::class, 'tax_bill_id');
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(Citizen::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
