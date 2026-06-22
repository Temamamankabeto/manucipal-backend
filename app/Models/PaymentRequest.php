<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentRequest extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_MANAGER_REVIEW = 'manager_review';
    public const STATUS_BUDGET_TL_REVIEW = 'budget_tl_review';
    public const STATUS_BUDGET_EXPERT_PROCESSING = 'budget_expert_processing';
    public const STATUS_BUDGET_TL_FINAL_REVIEW = 'budget_tl_final_review';
    public const STATUS_MANAGER_FINAL_REVIEW = 'manager_final_review';
    public const STATUS_RECORDS_PROCESSING = 'records_processing';
    public const STATUS_SENT_TO_FINANCE = 'sent_to_finance';
    public const STATUS_PAYMENT_COMPLETED = 'payment_completed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'request_no',
        'payment_no',
        'payee_name',
        'requested_by',
        'requester_type',
        'requesting_entity',
        'request_type',
        'payment_category_id',
        'payment_type_id',
        'budget_id',
        'payment_category',
        'title',
        'description',
        'amount',
        'budget_code',
        'office_code',
        'budget_year',
        'funding_source',
        'reference_no',
        'document_no',
        'official_date',
        'status',
        'current_handler_id',
        'department_id',
        'assigned_team_leader_id',
        'assigned_expert_id',
        'manager_signed_by',
        'budget_tl_signed_by',
        'budget_expert_signed_by',
        'budget_tl_final_signed_by',
        'manager_final_signed_by',
        'records_signed_by',
        'finance_signed_by',
        'paid_by',
        'paid_amount',
        'paid_date',
        'voucher_no',
        'finance_remark',
        'attachment_to',
        'attachment_address',
        'attachment_case',
        'attachment_body',
        'attachment_gg',
        'attachment_drafted_by',
        'attachment_drafted_at',
        'attachment_reference_no',
        'attachment_official_date',
        'records_attachment_drafted_by',
        'records_attachment_drafted_at',
        'submitted_at',
        'completed_at',
        'request_note',
        'print_status',
        'printed_at',
        'printed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'official_date' => 'date',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'paid_amount' => 'decimal:2',
        'paid_date' => 'date',
        'attachment_gg' => 'array',
        'attachment_drafted_at' => 'datetime',
        'attachment_official_date' => 'date',
        'records_attachment_drafted_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function currentHandler(): BelongsTo { return $this->belongsTo(User::class, 'current_handler_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function assignedTeamLeader(): BelongsTo { return $this->belongsTo(User::class, 'assigned_team_leader_id'); }
    public function assignedExpert(): BelongsTo { return $this->belongsTo(User::class, 'assigned_expert_id'); }
    public function paymentCategory(): BelongsTo { return $this->belongsTo(PaymentCategory::class, 'payment_category_id'); }
    public function paymentType(): BelongsTo { return $this->belongsTo(PaymentType::class, 'payment_type_id'); }
    public function budget(): BelongsTo { return $this->belongsTo(Budget::class); }
    public function items(): HasMany { return $this->hasMany(PaymentItem::class); }
    public function attachments(): HasMany { return $this->hasMany(PaymentAttachment::class); }
    public function histories(): HasMany { return $this->hasMany(PaymentHistory::class)->latest(); }
    public function perDiem() { return $this->hasOne(PaymentPerDiem::class); }

    public function managerSigner(): BelongsTo { return $this->belongsTo(User::class, 'manager_signed_by'); }
    public function budgetTlSigner(): BelongsTo { return $this->belongsTo(User::class, 'budget_tl_signed_by'); }
    public function budgetExpertSigner(): BelongsTo { return $this->belongsTo(User::class, 'budget_expert_signed_by'); }
    public function budgetTlFinalSigner(): BelongsTo { return $this->belongsTo(User::class, 'budget_tl_final_signed_by'); }
    public function managerFinalSigner(): BelongsTo { return $this->belongsTo(User::class, 'manager_final_signed_by'); }
    public function recordsSigner(): BelongsTo { return $this->belongsTo(User::class, 'records_signed_by'); }
    public function financeSigner(): BelongsTo { return $this->belongsTo(User::class, 'finance_signed_by'); }
    public function attachmentDrafter(): BelongsTo { return $this->belongsTo(User::class, 'attachment_drafted_by'); }
    public function recordsAttachmentDrafter(): BelongsTo { return $this->belongsTo(User::class, 'records_attachment_drafted_by'); }
    public function paidBy(): BelongsTo { return $this->belongsTo(User::class, 'paid_by'); }
}
