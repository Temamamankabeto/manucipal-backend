<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentPerDiemEmployee extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_per_diem_id',
        'employee_name',
        'salary_level',
        'salary_amount',
        'transportation_type',
        'departure_location',
        'destination',
        'departure_date',
        'departure_time',
        'return_date',
        'return_time',
        'number_of_days',
        'breakfast_deduction',
        'lunch_deduction',
        'dinner_deduction',
        'accommodation_deduction',
        'transport_cost',
        'fuel_cost',
        'other_cost',
        'daily_rate',
        'calculated_per_diem',
        'total_payable',
        'work_description',
        'is_selected',
        'metadata',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
        'number_of_days' => 'decimal:2',
        'salary_amount' => 'decimal:2',
        'breakfast_deduction' => 'decimal:2',
        'lunch_deduction' => 'decimal:2',
        'dinner_deduction' => 'decimal:2',
        'accommodation_deduction' => 'decimal:2',
        'transport_cost' => 'decimal:2',
        'fuel_cost' => 'decimal:2',
        'other_cost' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'calculated_per_diem' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'is_selected' => 'boolean',
        'metadata' => 'array',
    ];

    public function perDiem(): BelongsTo
    {
        return $this->belongsTo(PaymentPerDiem::class, 'payment_per_diem_id');
    }
}
