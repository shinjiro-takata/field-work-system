<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipe_id',
        'weighed_at',
        'notes',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function editLogs()
    {
        return $this->hasMany(ProductionLogEditLog::class);
    }

    public function materials()
    {
        return $this->belongsToMany(Material::class, 'production_log_materials')->withPivot('actual_quantity');
    }
}
