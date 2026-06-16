<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const ROLE_SUPER_ADMIN = 'Super Admin';
    public const ROLE_ADMIN = 'Admin';

    /**
     * Procurement & Payment roles from the technical specification document.
     */
    public const ROLE_MANAGER = 'Manager';
    public const ROLE_HEAD_DEVELOPMENT_BRANCH = 'Head of Development Branch';
    public const ROLE_HEAD_SERVICE_BRANCH = 'Head of Service Branch';
    public const ROLE_PLANNING_BUDGET_TEAM_LEADER = 'Planning & Budget Team Leader';
    public const ROLE_PLANNING_BUDGET_EXPERT = 'Planning & Budget Expert';
    public const ROLE_PROCUREMENT_REQUESTER = 'Procurement Requester';
    public const ROLE_PAYMENT_REQUESTER = 'Payment Requester';
    public const ROLE_RECORDS_OFFICE = 'Records Office';
    public const ROLE_FINANCE = 'Finance';
    public const ROLE_FINANCE_ACCOUNTANT = 'Finance Accountant';
    public const ROLE_ASSET_TEAM_LEADER = 'Asset Team Leader';
    public const ROLE_MACHINERY_TEAM_LEADER = 'Machinery Team Leader';

    public const ASSET_MANAGER = 'ASSET MANAGER';

    public static function systemRoleNames(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_HEAD_DEVELOPMENT_BRANCH,
            self::ROLE_HEAD_SERVICE_BRANCH,
            self::ROLE_PLANNING_BUDGET_TEAM_LEADER,
            self::ROLE_PLANNING_BUDGET_EXPERT,
            self::ROLE_PROCUREMENT_REQUESTER,
            self::ROLE_PAYMENT_REQUESTER,
            self::ROLE_RECORDS_OFFICE,
            self::ROLE_FINANCE,
            self::ROLE_FINANCE_ACCOUNTANT,
            self::ROLE_ASSET_TEAM_LEADER,
            self::ROLE_MACHINERY_TEAM_LEADER,
        ];
    }

    public const LEVEL_CITY = 'city';
    public const LEVEL_SUBCITY = 'subcity';
    public const LEVEL_WOREDA = 'woreda';
    public const LEVEL_ZONE = 'zone';

    protected $guard_name = 'sanctum';

    protected $fillable = [
        'created_by',
        'name',
        'email',
        'phone',
        'profile_image',
        'signature_path',
        'stamp_path',
        'titer_path',
        'password',
        'is_active',
        'address',
        'office_id',
        'admin_level',
        'professional_level',
        'sub_city_id',
        'woreda_id',
        'zone_id',
        'last_login_at',
        'refresh_token',
        'refresh_token_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'refresh_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
    ];

    protected $appends = ['profile_image_url', 'signature_url', 'stamp_url', 'titer_url'];

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return $this->signature_path ? asset('storage/' . $this->signature_path) : null;
    }

    public function getStampUrlAttribute(): ?string
    {
        return $this->stamp_path ? asset('storage/' . $this->stamp_path) : null;
    }

    public function getTiterUrlAttribute(): ?string
    {
        return $this->titer_path ? asset('storage/' . $this->titer_path) : null;
    }


    public function subCity(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'sub_city_id');
    }

    public function woreda(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'woreda_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'zone_id');
    }

    public function registeredCitizens(): HasMany
    {
        return $this->hasMany(Citizen::class, 'registered_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function isBusinessManager(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_MANAGER,
            self::ROLE_HEAD_DEVELOPMENT_BRANCH,
            self::ROLE_HEAD_SERVICE_BRANCH,
        ]);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->isSuperAdmin()) {
            return $query;
        }

        if ($actor->isBusinessManager()) {
            return $query->where(function (Builder $q) use ($actor) {
                $q->whereKey($actor->id)->orWhere('created_by', $actor->id);
            });
        }

        if (! $actor->isAdmin()) {
            return $query->whereKey($actor->id);
        }

        return match ($actor->admin_level) {
            self::LEVEL_CITY => $query,
            self::LEVEL_SUBCITY => $actor->sub_city_id ? $query->where('sub_city_id', $actor->sub_city_id) : $query->whereRaw('1 = 0'),
            self::LEVEL_WOREDA => $actor->woreda_id ? $query->where('woreda_id', $actor->woreda_id) : $query->whereRaw('1 = 0'),
            self::LEVEL_ZONE => $actor->zone_id ? $query->where('zone_id', $actor->zone_id) : $query->whereRaw('1 = 0'),
            default => $query->whereKey($actor->id),
        };
    }

    public function canManageScopeOf(User $target): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isBusinessManager()) {
            return $this->id === $target->id || (int) $target->created_by === (int) $this->id;
        }

        if (! $this->isAdmin()) {
            return $this->id === $target->id;
        }

        return match ($this->admin_level) {
            self::LEVEL_CITY => true,
            self::LEVEL_SUBCITY => $this->sub_city_id && $this->sub_city_id === $target->sub_city_id,
            self::LEVEL_WOREDA => $this->woreda_id && $this->woreda_id === $target->woreda_id,
            self::LEVEL_ZONE => $this->zone_id && $this->zone_id === $target->zone_id,
            default => false,
        };
    }

    public function getProfileImageUrlAttribute(): ?string
    {
        return $this->profile_image ? asset('storage/' . $this->profile_image) : null;
    }
}
