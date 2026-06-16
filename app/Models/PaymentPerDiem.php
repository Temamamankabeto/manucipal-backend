<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentPerDiem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_request_id',
        'program',
        'purpose',
        'pi_code',
        'budget_code',
        'office_name',
        'departure_location',
        'destination',
        'departure_date',
        'return_date',
        'transport_allowance',
        'daily_per_diem_rate',
        'approved_budget',
        'total_per_diem',
        'total_transport',
        'total_fuel',
        'total_other',
        'grand_total',
        'metadata',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
        'transport_allowance' => 'decimal:2',
        'daily_per_diem_rate' => 'decimal:2',
        'approved_budget' => 'decimal:2',
        'total_per_diem' => 'decimal:2',
        'total_transport' => 'decimal:2',
        'total_fuel' => 'decimal:2',
        'total_other' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(PaymentPerDiemEmployee::class)->orderBy('id');
    }
}
