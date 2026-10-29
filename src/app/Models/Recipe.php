<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * レシピ（製品ごとの材料の配合表）を表すモデル。テーブル: recipes
 */
class Recipe extends Model
{
    use HasFactory;

    // まとめて保存してよい項目
    protected $fillable = [
        'name',
        'created_by',
        'updated_by',
    ];

    /**
     * このレシピで使う材料（多対多）。
     * 中間テーブル（ピボットテーブル）recipe_materials に、基準量 quantity と許容範囲 tolerance を持つ。
     */
    public function materials()
    {
        return $this->belongsToMany(Material::class, 'recipe_materials')->withPivot('quantity', 'tolerance');
    }

    /** このレシピで作られた計量ログ（1対多）。 */
    public function productionLogs()
    {
        return $this->hasMany(ProductionLog::class);
    }
}
