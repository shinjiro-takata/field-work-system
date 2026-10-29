<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 材料マスタ（材料名と単位）を表すモデル。テーブル: materials
 */
class Material extends Model
{
    use HasFactory;

    // まとめて保存してよい項目
    protected $fillable = [
        'name',
        'unit',
    ];

    // 配列・JSON に変換するとき、下の getFormattedQuantityAttribute の値（formatted_quantity）も含める
    protected $appends = ['formatted_quantity'];

    /** この材料を使うレシピ（多対多。中間テーブル recipe_materials に基準量・許容範囲を持つ）。 */
    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'recipe_materials')->withPivot('quantity', 'tolerance');
    }

    /**
     * 基準量を小数第3位までの文字列にして返す（例: 1.500）。
     * レシピ経由で取得した材料（pivot がある状態）でのみ使える。
     */
    public function getFormattedQuantityAttribute()
    {
        return number_format($this->pivot->quantity, 3);
    }
}
