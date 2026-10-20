<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResinUsageAudit extends Model
{
    protected $fillable = [
        'action',
        'usage_date',
        'changed_by',
        'changed_by_name',
        'before_data',
        'after_data',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'before_data' => 'array',
        'after_data' => 'array',
    ];
}
