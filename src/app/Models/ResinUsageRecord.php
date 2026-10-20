<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResinUsageRecord extends Model
{
    protected $fillable = [
        'usage_date',
        'operator_name',
        'supervisor_name',
        'supervisor_confirmed_at',
    ];

    protected $dates = ['usage_date', 'supervisor_confirmed_at'];

    public function items()
    {
        return $this->hasMany(ResinUsageItem::class);
    }
}
