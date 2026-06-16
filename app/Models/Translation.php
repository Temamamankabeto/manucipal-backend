<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $fillable = [
        'language',
        'translation_key',
        'translation_value',
    ];
}
