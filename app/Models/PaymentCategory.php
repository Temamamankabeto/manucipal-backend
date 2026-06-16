<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function types(): HasMany
    {
        return $this->hasMany(PaymentType::class, 'category_id');
    }
}
