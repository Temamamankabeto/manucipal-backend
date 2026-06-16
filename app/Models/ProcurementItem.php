<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementItem extends Model
{
    protected $fillable = ['procurement_request_id','item_name','specification','quantity','unit','estimated_unit_cost','estimated_total_cost'];
    protected $casts = ['quantity' => 'decimal:2','estimated_unit_cost' => 'decimal:2','estimated_total_cost' => 'decimal:2'];
    public function request(): BelongsTo { return $this->belongsTo(ProcurementRequest::class, 'procurement_request_id'); }
}
