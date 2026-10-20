<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResinUsageItem extends Model
{
    protected $fillable = [
        'material_id',
        'opening_stock',
        'used_quantity',
        'replenished_quantity',
        'calculated_stock',
        'actual_stock',
        'adjustment_quantity',
        'adjustment_reason',
        'reconciled_by',
        'closing_stock',
    ];

    public function record()
    {
        return $this->belongsTo(ResinUsageRecord::class, 'resin_usage_record_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
