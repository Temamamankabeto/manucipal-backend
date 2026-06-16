<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementHistory extends Model
{
    protected $fillable = ['procurement_request_id','actor_id','action','from_status','to_status','note','metadata'];
    protected $casts = ['metadata' => 'array'];
    public function request(): BelongsTo { return $this->belongsTo(ProcurementRequest::class, 'procurement_request_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
