<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementRequest extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_MANAGER_REVIEW = 'manager_review';
    public const STATUS_ASSET_TEAM_REVIEW = 'asset_team_review';
    public const STATUS_BUDGET_TL_REVIEW = 'budget_tl_review';
    public const STATUS_BUDGET_EXPERT_PROCESSING = 'budget_expert_processing';
    public const STATUS_FINAL_MANAGER_REVIEW = 'final_manager_review';
    public const STATUS_RECORDS_PROCESSING = 'records_processing';
    public const STATUS_SENT_TO_FINANCE = 'sent_to_finance';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'request_no', 'requested_by', 'requester_type', 'category_id', 'procurement_type_id', 'title', 'description',
        'submission_method', 'budget_code', 'reference_no', 'official_date', 'official_date_ec',
        'status', 'current_handler_id',
        'manager_signed_by', 'asset_signed_by', 'budget_expert_signed_by',
        'budget_tl_signed_by', 'final_manager_signed_by', 'records_signed_by',
        'finance_signed_by',
        'submitted_at', 'completed_at',
    ];

    protected $casts = [
        'official_date' => 'date',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function category(): BelongsTo { return $this->belongsTo(ProcurementCategory::class, 'category_id'); }
    public function procurementType(): BelongsTo { return $this->belongsTo(ProcurementType::class, 'procurement_type_id'); }
    public function currentHandler(): BelongsTo { return $this->belongsTo(User::class, 'current_handler_id'); }
    public function managerSigner(): BelongsTo { return $this->belongsTo(User::class, 'manager_signed_by'); }
    public function assetSigner(): BelongsTo { return $this->belongsTo(User::class, 'asset_signed_by'); }
    public function budgetExpertSigner(): BelongsTo { return $this->belongsTo(User::class, 'budget_expert_signed_by'); }
    public function budgetTlSigner(): BelongsTo { return $this->belongsTo(User::class, 'budget_tl_signed_by'); }
    public function finalManagerSigner(): BelongsTo { return $this->belongsTo(User::class, 'final_manager_signed_by'); }
    public function recordsSigner(): BelongsTo { return $this->belongsTo(User::class, 'records_signed_by'); }
    public function financeSigner(): BelongsTo { return $this->belongsTo(User::class, 'finance_signed_by'); }
    public function items(): HasMany { return $this->hasMany(ProcurementItem::class); }
    public function attachments(): HasMany { return $this->hasMany(ProcurementAttachment::class); }
    public function histories(): HasMany { return $this->hasMany(ProcurementHistory::class)->latest(); }
}
