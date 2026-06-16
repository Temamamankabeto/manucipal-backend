<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'bi_code',
        'reporting_unit',
        'month_year',
        'bank_account_code',
        'source_of_finance',
        'budget_type',
        'budget_code',
        'account_name',
        'fiscal_year',
        'allocated_amount',
        'used_amount',
        'remaining_amount',
        'status',
        'description',
        'created_by',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'used_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BudgetTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }
}
