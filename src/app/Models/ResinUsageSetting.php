<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResinUsageSetting extends Model
{
    protected $fillable = [
        'material_one_id',
        'material_two_id',
        'material_one_initial_stock',
        'material_two_initial_stock',
        'updated_by',
    ];

    public function materialOne()
    {
        return $this->belongsTo(Material::class, 'material_one_id');
    }

    public function materialTwo()
    {
        return $this->belongsTo(Material::class, 'material_two_id');
    }
}
