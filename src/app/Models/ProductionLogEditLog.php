<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionLogEditLog extends Model
{
    protected $fillable = [
        'production_log_id',
        'editor_id',
        'editor_name',
        'before_data',
        'after_data',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
    ];

    public function productionLog()
    {
        return $this->belongsTo(ProductionLog::class);
    }
}
